<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Basis\Nats\Message\Payload;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestStartedHandling;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\Liveness\Liveness;
use FrockDev\ToolsForLaravel\Swow\Nats\NewNatsClient;
use FrockDev\ToolsForLaravel\Transport\AbstractMessage;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Swow\Channel;
use Throwable;

class NatsDriver
{
    private NewNatsClient $client;

    private string $name;
    private \Basis\Nats\Configuration $currentConfig;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->currentConfig = new \Basis\Nats\Configuration([
            'host'=>config('nats.host', env('NATS_HOST', 'nats.nats')),
            'port'=>(int)config('nats.port', env('NATS_PORT', 4222)),
            'user'=>config('nats.user', env('NATS_USER', '')),
            'pass'=>config('nats.pass', env('NATS_PASS', '')),
            'timeout'=>(float)config('nats.timeout', (float)env('NATS_TIMEOUT', 1)),
        ]);
        $this->client = new NewNatsClient($this->currentConfig, $this->name);
    }

    public function runReceiving(string $namePostfix='') {
        ContextStorage::setSystemChannel('natsReceiveChannel_'.$this->name, new Channel());
        Co::define($this->name.'_nats_receiving_'.$namePostfix)
            ->charge(function () {
            while(true) {
                try {
                    $this->client->startReceiving(ContextStorage::getSystemChannel('natsReceiveChannel_'.$this->name));
                } catch (Throwable $exception) {
                    Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                        'exception' => $exception,
                    ]);
                    Liveness::setLiveness($this->name, 500, 'Error got on receiving', Liveness::MODE_5_SEC);
                    throw $exception;
                }
            }
        })->run();
    }

    public function publishToStream(string $streamName, string $subject, string|AbstractMessage $payload)
    {
        if (!is_string($payload)) {
            $payload = $payload->toJson();
        }
        $stream = $this->client->getApi()->getStream($streamName);
        $stream->put($subject, $payload);
    }

    public function publish(string $subject, string|AbstractMessage $payload, $replyTo = null): void
    {
        if (is_string($payload)) {
            $this->client->publish($subject, $payload, $replyTo);
        } else {
            if (method_exists($payload, 'serializeViaSymfonySerializer')) {
                $this->client->publish($subject, $payload->serializeViaSymfonySerializer(), $replyTo);
            } else {
                $this->client->publish($subject, $payload->jsonSerialize(), $replyTo);
            }
        }
    }

    /**
     * @param string $subject
     * @param string|AbstractMessage $payload
     * @param string|null $decodeTo
     * @return string|AbstractMessage
     */
    public function publishSync(string $subject, string|AbstractMessage $payload, ?string $decodeTo=null): string|AbstractMessage {
        if (is_string($payload)) {
            return $this->client->dispatch($subject, $payload);
        } else {
            $result = $this->client->dispatch($subject, $payload->jsonSerialize());
            $decodedBody = json_decode($result->body, true);
            if (!!$decodeTo) {
                $decodedBody['context'] = $result->headers;
                return $decodedBody;
            }
            if (isset($decodedBody['error'])) {
                //todo what about throw new Exception instead?
                // if we throw exception, we can work with transports as with services?
                // no matter if service is local or remote
                $decodedBody['context'] = $result->headers; //todo what about throw new Exception instead?
                return $decodedBody;
            }
            /** @var AbstractMessage $response */
            $response =  $decodeTo::from($decodedBody);
            $response->context = $result->headers;
            return $response;
        }
    }

    private function runThroughKernel(string $subject, string $body, array $headers = [], ?string $queue=null, ?string $stream=null): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\Response
    {
        /** @var Kernel $kernel */
        $kernel = app()->make(Kernel::class);
        if (!$stream) {
            if (!$queue)
                $uri = $subject;
            else
                $uri = $subject. '/' . $queue;
        } else {
            $uri = $stream . '/' . $subject;
        }
        $convertedHeaders = [
            'HTTP_NATS_SUBJECT'=> $subject,
        ];
        if ($stream) $convertedHeaders['HTTP_NATS_STREAM'] = $stream;
        if ($queue) $convertedHeaders['HTTP_NATS_QUEUE'] = $queue;
        foreach ($headers as $key=> $header) {
            $convertedHeaders['HTTP_'.$key] = $header;
        }
        if (!isset($convertedHeaders['HTTP_x-trace-id'])) {
            $convertedHeaders['HTTP_x-trace-id'] = ContextStorage::get('x-trace-id');
        }
        $serverParams = array_merge([
            'REQUEST_URI'=> $uri,
            'REQUEST_METHOD'=> 'POST',
            'QUERY_STRING'=> '',
        ], $convertedHeaders);

        $laravelRequest = new Request(
            query: [],
            attributes: array_merge(['transport'=>'nats']),
            cookies: [],
            files: [],
            server: $serverParams,
            content: $body
        );
        $dispatcher = app()->make(\Illuminate\Contracts\Events\Dispatcher::class);
        $dispatcher->dispatch(new RequestStartedHandling($laravelRequest));
        Log::debug('Request got from Nats:', ['request'=>$laravelRequest]);
        $response =  $kernel->handle(
            $laravelRequest
        );
        Log::debug('Response got from Nats:', ['response'=>$response]);
        return $response;
    }

    public function subscribeToJetstreamWithEndpoint(string $subject, string $streamName, object $endpoint, $periodInMicroseconds=null, $disableSpatieValidation = false) {
        $controller = function (Request $request) use ($endpoint, $disableSpatieValidation) {
            $endpoint->setContext($request->headers->all());
            $inputType = $endpoint::ENDPOINT_INPUT_TYPE;
            /** @var AbstractMessage $dto */

            if ($disableSpatieValidation) {
                $dto = $inputType::from($request->json()->all());
            } else {
                $dto = $inputType::validateAndCreate($request->json()->all());
            }

            /** @var AbstractMessage $result */
            $result = $endpoint->__invoke($dto);
            return $result;
        };
        $callback = function(Payload $payload) use ($subject, $streamName) {
            $resultChannel = new Channel(1);
            ContextStorage::set('x-trace-id', $payload->getHeader('x-trace-id')??uuid_create());
            Co::define('subject_'.$subject.'_nats_routing_function')->charge(function($subject, $payload, $streamName) use ($resultChannel) {
                $resultChannel->push(
                    $this->runThroughKernel(subject: $subject, body: $payload->body, headers: $payload->headers, stream: $streamName)
                );
            })->args($subject, $payload, $streamName)
                ->runWithClonedDiContainer();

            return $resultChannel->pop();
        };

        try {
            Route::post($streamName.'/'.$subject, $controller);
        } catch (Throwable $exception) {
            throw $exception;
        }

        $this->runReceiving();

        $firstStart = true;
        try {
            $jetStream = $this->client->getApi()->getStream($streamName);
            $consumerName = $streamName . '-' . Str::random(4) . '-' . env('HOSTNAME') . '-' . config('app.env');
            $consumer = $jetStream->getConsumer($consumerName);
            $consumer->getConfiguration()->setSubjectFilter($subject);
        } catch (Throwable $exception) {
            Log::error('NatsDriver: error while creating consumer: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);
            throw $exception;
        }
        try {
            while (ContextStorage::getSystemChannel('natsReceiveChannel_' . $this->name)) {
                try {
                    if (!is_null($periodInMicroseconds)) {
                        $consumer->setIterations(1);
                        if (!$firstStart) {
                            usleep($periodInMicroseconds);
                        }
                    }
                    $consumer->handle($callback);
                } catch (Throwable $exception) {
                    Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                        'exception' => $exception,
                    ]);

                    throw $exception;
                } finally {
                    $firstStart = false;
                }
            }
        } finally {
            $consumer->delete();
        }
    }

    public function subscribeWithEndpoint(string $subject, object $endpoint, ?string $queue=null, bool $disableSpatieValidation = false) {
        $controller = function (Request $request) use ($endpoint, $disableSpatieValidation) {
            $endpoint->setContext($request->headers->all());
            $inputType = $endpoint::ENDPOINT_INPUT_TYPE;
            /** @var AbstractMessage $dto */
            if ($disableSpatieValidation) {
                $dto = $inputType::from($request->json()->all());
            } else {
                $dto = $inputType::validateAndCreate($request->json()->all());
            }

            /** @var AbstractMessage $result */
            $result = $endpoint->__invoke($dto);
            return $result;
        };
        $callback = function(Payload $payload) use ($subject, $queue) {
            $resultChannel = new Channel(1);
            ContextStorage::set('x-trace-id', $payload->getHeader('x-trace-id')??uuid_create());
            Co::define('subject_'.$subject.'_queue_'.($queue??'').'_nats_routing_function')
                ->charge(function($subject, $payload, $queue=null) use ($resultChannel) {
                $resultChannel->push(
                    $this->runThroughKernel(subject: $subject, body: $payload->body, headers: $payload->headers, queue: $queue)
                );
            })->args($subject, $payload, $queue)
                ->runWithClonedDiContainer();
            return $resultChannel->pop();
        };
        try {
            if (!$queue) {
                Route::post($subject, $controller);
                $this->client->subscribe($subject, $callback);
            } else {
                Route::post($subject . '/' . $queue, $controller);
                $this->client->subscribeQueue($subject, $queue, $callback);
            }
        } catch (Throwable $exception) {
            throw $exception;
        }
        $this->runReceiving();
        while (ContextStorage::getSystemChannel('natsReceiveChannel_'.$this->name)) {
            sleep(3);
        }
    }
}
