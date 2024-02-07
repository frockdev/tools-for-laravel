<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Basis\Nats\Message\Payload;
use Closure;
use FrockDev\ToolsForLaravel\Swow\Liveness\Liveness;
use FrockDev\ToolsForLaravel\Swow\Nats\NewNatsClient;
use Google\Protobuf\Internal\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NatsDriver
{
    private NewNatsClient $client;

    private string $name;

    public function __construct(string $name)
    {
        $config = new \Basis\Nats\Configuration([
            'host'=>config('nats.host', env('NATS_HOST', 'nats.nats')),
            'port'=>(int)config('nats.port', env('NATS_PORT', 4222)),
            'user'=>config('nats.user', env('NATS_USER', '')),
            'pass'=>config('nats.pass', env('NATS_PASS', '')),
            'timeout'=>(float)config('nats.timeout', (float)env('NATS_TIMEOUT', 1)),
        ]);
        $this->client = new NewNatsClient($config, $name);
        $this->name = $name;
    }

    public function publish(string $subject, string|Message $payload, $replyTo = null): void
    {
        if (is_string($payload)) {
            $this->client->publish($subject, $payload, $replyTo);
        } else {
            if (method_exists($payload, 'serializeViaSymfonySerializer')) {
                $this->client->publish($subject, $payload->serializeViaSymfonySerializer(), $replyTo);
            } else {
                $this->client->publish($subject, $payload->serializeToJsonString(), $replyTo);
            }
        }
    }

    /**
     * @param string $subject
     * @param string|Message $payload
     * @param $replyTo
     * @return string
     * @deprecated
     * @internal
     * @todo this method should be tested
     */
    public function publishSync(string $subject, string|Message $payload, $replyTo = null): string {
        if (is_string($payload)) {
            return $this->client->dispatch($subject, $payload, $replyTo);
        } else {
            return $this->client->dispatch($subject, $payload->serializeToJsonString(), $replyTo);
        }
    }

    /**
     * @param Payload $payload
     * @return void
     * @deprecated
     */
    private function logMessageFromSubscribe(Payload $payload) {
        $headers = '';
        foreach ($payload->headers as $key=>$value) {
            $headers .= $key . ': ' . $value . ", ";
        }
        Log::debug('NatsDriver: received message at ' . $payload->subject . ', headers: ('. $headers . '),  body:' . $payload->body);
    }

    /**
     * @param Payload $payload
     * @param string $streamName
     * @return void
     * @deprecated
     */
    private function logMessageFromSubscribeToStream(Payload $payload, string $streamName) {
        $headers = '';
        foreach ($payload->headers as $key=>$value) {
            $headers .= $key . ': ' . $value . ", ";
        }
        Log::debug('NatsDriver: received message at ' . $payload->subject . ' and stream: '.$streamName.', headers: ('. $headers . '),  body:' . $payload->body);
    }

    public function subscribe(string $subject, string $queue, Closure $callback, string $deserializeTo): void
    {
        $wrappedFunction = function (Payload $payload) use ($callback, $deserializeTo, $subject) {
            try {
                ContextStorage::set('X-Trace-Id', $payload->getHeader('X-Trace-Id') ?? uuid_create());
                $this->logMessageFromSubscribe($payload);
                /** @var Message $result */
                $result = new $deserializeTo();
                $result->mergeFromJsonString($payload->body);
                $response = $callback($result, $payload);
                Log::info('NatsDriver: response from callback: ' . json_encode($response));
                return $response;
            } catch (\Google\Protobuf\Internal\GPBDecodeException $exception) {
                Log::error('Failed to decode message at ' . $subject . ': ' . $exception->getMessage(),
                    [
                        'payloadBody'=>$payload->body
                    ]);
                throw $exception;
            }
        };
        try {
            if ($queue === '') {
                $this->client->subscribe($subject, $wrappedFunction);
            } else {
                $this->client->subscribeQueue($subject, $queue, $wrappedFunction);
            }
        } catch (Throwable $exception) {
            throw $exception;
        }
        CoroutineManager::runSafe(function () {
            while(true) {
                try {
                    $this->client->startReceiving();
                } catch (Throwable $exception) {
                    Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                        'exception' => $exception,
                    ]);
                    throw $exception;
                }
            }
        }, $this->name.'_nats_receiving');
    }

    public function subscribeToStream(string $subject, string $streamName, Closure $callback, string $deserializeTo, ?float $period = null): void
    {
        $wrappedFunction = function (Payload $payload) use ($callback, $deserializeTo, $subject, $streamName) {
            ContextStorage::set('X-Trace-Id', $payload->getHeader('X-Trace-Id') ?? uuid_create());
            $this->logMessageFromSubscribeToStream($payload, $streamName);
            try {
                /** @var Message $input */
                $input = new $deserializeTo();
                $input->mergeFromJsonString($payload->body);
                $response = $callback($input, $payload);
                Log::info('NatsDriver: response from callback: ' . json_encode($response));
                return $response;
            } catch (\Google\Protobuf\Internal\GPBDecodeException $exception) {
                Log::error('Failed to decode message at ' . $subject . ', ' . $streamName . ': ' . $exception->getMessage(),
                    [
                        'payloadBody'=>$payload->body
                    ]);
                throw $exception;
            } catch (Throwable $exception) {
                Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                    'exception' => $exception,
                ]);
                ContextStorage::getSystemChannel('exitChannel')->push($exception->getCode()>0?$exception->getCode():700);
                throw $exception;
            }

        };
        $firstStart = true;
        CoroutineManager::runSafe(function () use ($subject, $streamName) {
            while(true) {
                $componentName = 'nats_consumer_' . $streamName . '_' . $subject;
                try {
                    $this->client->startReceiving();
                } catch (Throwable $exception) {
                    Liveness::setLiveness($componentName, 500, 'fault. '.$exception->getMessage());
                    Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                        'exception' => $exception,
                    ]);
                    throw $exception;
                }
            }
        }, $this->name.'_nats_stream_receiving');
        while (true) {
            try {
                $jetStream = $this->client->getApi()->getStream($streamName);
                $consumerName = $streamName . '-' . Str::random(4) . '-' . env('HOSTNAME') . '-' . config('app.env');
                $consumer = $jetStream->getConsumer($consumerName);
                $consumer->getConfiguration()->setSubjectFilter($subject);
                if (!is_null($period)) {
                    $consumer->setIterations(1);
                    if (!$firstStart) {
                        sleep($period);
                    }
                }
            } catch (Throwable $exception) {
                Log::error('NatsDriver: error while creating consumer: ' . $exception->getMessage(), [
                    'exception' => $exception,
                ]);

                ContextStorage::getSystemChannel('exitChannel')->push($exception->getCode()>0?$exception->getCode():700);
                throw $exception;
            }

            try {
                $consumer->handle($wrappedFunction);
            } catch (Throwable $exception) {
                Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                    'exception' => $exception,
                ]);

                ContextStorage::getSystemChannel('exitChannel')->push($exception->getCode()>0?$exception->getCode():700);
                throw $exception;
            } finally {
                $consumer->delete();
                $firstStart = false;
            }
        }
    }
}
