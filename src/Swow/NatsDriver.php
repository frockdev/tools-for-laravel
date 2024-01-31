<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use Closure;
use FrockDev\ToolsForLaravel\Swow\Nats\SwowNatsClient;
use Google\Protobuf\Internal\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Swow\Coroutine;
use Throwable;

class NatsDriver
{
    private SwowNatsClient $client;

    public function __construct()
    {
        $config = new \Basis\Nats\Configuration([
            'host'=>config('nats.host', env('NATS_HOST', 'nats.nats')),
            'port'=>(int)config('nats.port', env('NATS_PORT', 4222)),
            'user'=>config('nats.user', env('NATS_USER', '')),
            'pass'=>config('nats.pass', env('NATS_PASS', '')),
            'timeout'=>(float)config('nats.timeout', (float)env('NATS_TIMEOUT', 1)),
            'reconnect'=>(bool)config('nats.reconnect', env('NATS_RECONNECT', true)),
        ]);
        $this->client = new SwowNatsClient($config, Log::getLogger());
    }

    public function publish(string $subject, string|Message $payload, $replyTo = null): void
    {
        try {
            if (is_string($payload)) {
                $this->client->publish($subject, $payload, $replyTo);
            } else {
                $this->client->publish($subject, $payload->serializeToJsonString(), $replyTo);
            }
        } finally {

        }
    }

    private function logMessageFromSubscribe(Payload $payload) {
        $headers = '';
        foreach ($payload->headers as $key=>$value) {
            $headers .= $key . ': ' . $value . ", ";
        }
        Log::debug('NatsDriver: received message at ' . $payload->subject . ', headers: ('. $headers . '),  body:' . $payload->body);
    }

    private function logMessageFromSubscribeToStream(Payload $payload, string $streamName) {
        $headers = '';
        foreach ($payload->headers as $key=>$value) {
            $headers .= $key . ': ' . $value . ", ";
        }
        Log::debug('NatsDriver: received message at ' . $payload->subject . ' and stream: '.$streamName.', headers: ('. $headers . '),  body:' . $payload->body);
    }

    public function subscribe(string $subject, string $queue, Closure $callback, string $deserializeTo): void
    {
        $function = function (Payload $payload) use ($callback, $deserializeTo) {
            ContextStorage::set('X-Trace-Id', $payload->getHeader('X-Trace-Id') ?? uuid_create());
            $this->logMessageFromSubscribe($payload);
            /** @var Message $result */
            $result = new $deserializeTo();
            $result->mergeFromJsonString($payload->body);
            $response = $callback($result, $payload);
            Log::info('NatsDriver: response from callback: ' . json_encode($response));
            ContextStorage::clearStorage();
            return $response;
        };
        try {
            if ($queue === '') {
                $this->client->subscribe($subject, $function);
            } else {
                $this->client->subscribeQueue($subject, $queue, $function);
            }
        } catch (Throwable $exception) {
            throw $exception;
        }
        while(true) {
            try {
                $this->client->process();
            } catch (Throwable $exception) {
                Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                    'exception' => $exception,
                ]);
                throw $exception;
            }
        }
    }

    public function subscribeToStream(string $subject, string $streamName, Closure $callback, string $deserializeTo, ?float $period = null): void
    {
        $function = function (Payload $payload) use ($callback, $deserializeTo, $subject, $streamName) {
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
                throw $exception;
            } finally {
                ContextStorage::clearStorage();
            }

        };
        $firstStart = true;
        while (true) {

            try {
                $jetStream = $this->client->getApi()->getStream($streamName);
                $consumerName = $streamName . '-' . Str::random() . '-' . config('app.name') . '-' . config('app.env') . '-' . env('NATS_SPECIAL_POSTFIX', 'default');
                $consumer = $jetStream->getConsumer($consumerName);
                $consumer->getConfiguration()->setSubjectFilter($subject);
                if (!is_null($period)) {
                    $consumer->setIterations(1);
                    if (!$firstStart) {
                        sleep($period);
                    }
                }
                $consumer->handle($function);
            } catch (Throwable $exception) {
                Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                    'exception' => $exception,
                ]);
                ContextStorage::getSystemChannel('exitChannel')->push($exception->getCode()>0?$exception->getCode():700);
                throw $exception;
            } finally {
                $consumer?->delete();
                $firstStart = false;
            }
        }
    }

    public function process(int $timeout = 10)
    {
        try {
            $this->client->process($timeout);
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

}
