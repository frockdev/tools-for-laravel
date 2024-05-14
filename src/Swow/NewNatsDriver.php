<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Basis\Nats\Client;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Message\Payload;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestFinished;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestStartedHandling;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Transport\AbstractMessage;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use Psr\Log\LoggerInterface;
use Swow\Channel;
use Swow\Sync\WaitGroup;
use Throwable;

class NewNatsDriver implements NatsDriverInterface
{

    private Client $client;

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
            'tlsCertFile'=>config('nats.tlsCertFile', env('NATS_TLS_CERT_FILE')),
            'tlsKeyFile'=>config('nats.tlsKeyFile', env('NATS_TLS_KEY_FILE')),
            'tlsCaFile'=>config('nats.tlsCAFile', env('NATS_TLS_CA_FILE')),
        ]);
        $this->client = new Client($this->currentConfig, app()->make(LoggerInterface::class));
    }

    /**
     * @deprecated
     * @param string $namePostfix
     * @return WaitGroup
     * @throws \Exception
     */
    public function runReceiving(string $namePostfix = ''): WaitGroup
    {
        throw new \Exception('Not implemented');
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

    public function runThroughKernel(string $subject, string $body, array $headers = [], ?string $queue = null, ?string $stream = null): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\Response
    {
        try {
            if (!$stream) {
                if (!$queue)
                    $uri = $subject;
                else
                    $uri = $subject . '/' . $queue;
            } else {
                $uri = $stream . '/' . $subject;
            }
            $convertedHeaders = [
                'HTTP_NATS_SUBJECT' => $subject,
            ];
            if ($stream) $convertedHeaders['HTTP_NATS_STREAM'] = $stream;
            if ($queue) $convertedHeaders['HTTP_NATS_QUEUE'] = $queue;
            foreach ($headers as $key => $header) {
                $convertedHeaders['HTTP_' . $key] = $header;
            }
            if (!isset($convertedHeaders['HTTP_x-trace-id'])) {
                $convertedHeaders['HTTP_x-trace-id'] = ContextStorage::get('x-trace-id');
            }
            $serverParams = array_merge([
                'REQUEST_URI' => $uri,
                'REQUEST_METHOD' => 'POST',
                'QUERY_STRING' => '',
            ], $convertedHeaders);

            $laravelRequest = new Request(
                query: [],
                attributes: ['transport' => 'nats'],
                cookies: [],
                files: [],
                server: $serverParams,
                content: $body
            );
            $dispatcher = app()->make(\Illuminate\Contracts\Events\Dispatcher::class);
            $dispatcher->dispatch(new RequestStartedHandling($laravelRequest));
            Log::debug('Request got from Nats:', ['request' => $laravelRequest]);
            $kernel = app()->make(HttpKernelContract::class);
            $response = $kernel->handle($laravelRequest);
            Log::debug('Response got from Nats:', ['response' => $response]);
            return $response;
        } finally {
            /** @var \Illuminate\Events\Dispatcher $dispatcher */
            $dispatcher = ContextStorage::getApplication()->make(\Illuminate\Contracts\Events\Dispatcher::class);
            $dispatcher->dispatch(new RequestFinished(ContextStorage::getApplication()));
        }
    }

    public function subscribeToJetstreamWithEndpoint(string $subject, string $streamName, object $endpoint, $periodInMicroseconds = null, $disableSpatieValidation = false, $deliverPolicy = DeliverPolicy::NEW, $ackPolicy = AckPolicy::NONE)
    {
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

        try {
            $jetStream = $this->client->getApi()->getStream($streamName);
            $consumerName = $streamName . '-' . Str::random(4) . '-' . env('HOSTNAME') . '-' . config('app.env');
            $consumer = $jetStream->getConsumer($consumerName);
            $consumer->getConfiguration()->setSubjectFilter($subject);
            $consumer->getConfiguration()->setAckPolicy($ackPolicy);
            $consumer->getConfiguration()->setDeliverPolicy($deliverPolicy);
            $consumer->setBatching(1);
        } catch (Throwable $exception) {
            Log::error('NatsDriver: error while creating consumer: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);
            throw $exception;
        }

        try {
            while (true) {
                try {
                    if (!is_null($periodInMicroseconds)) {
                        $consumer->setIterations(1);
                    }
                    $consumer->handle(
                        messageHandler: $callback,
                        emptyHandler: function(?Payload $payload = null, ?string $replyTo = null) {
                        if ($payload) {
                            if ($payload->hasHeader('Status-Code')) {
                                if ($payload->getHeader('Status-Code') != "200") {
                                    throw new LogicException('NATS. Error code detected: ' . $payload->getHeader('Status-Code')
                                        . ' '.$payload->getHeader('Status-Message'));
                                }
                            }
                        }
                    },
                        ack: false);
                    if (!is_null($periodInMicroseconds)) {
                        usleep($periodInMicroseconds);
                    }
                } catch (LogicException $e) {
                    if (str_starts_with($e->getMessage(), 'No handler for message')) {
                        Log::info('NatsDriver: no handler for message: ' . $e->getMessage());
                    }
                }

            }

        } catch (Throwable $exception) {
            Log::error('NatsDriver: error while processing message: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            $consumer->delete();
        }
    }

    public function subscribeWithEndpoint(string $subject, object $endpoint, ?string $queueName=null, bool $disableSpatieValidation = false) {
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
        //////////
        if (!$queueName) {
            Route::post($subject, $controller);
            $queueObject = $this->client->subscribe($subject);
        } else {
            Route::post($subject . '/' . $queueName, $controller);
            $queueObject = $this->client->subscribeQueue($subject, $queueName);
        }
        //////////

        while (true) {
            if (!($message = $queueObject->fetch())) {
                usleep(200000);
                continue;
            }


            $waitGroup = new WaitGroup();
            $waitGroup->add();
            Co::define('subject_'.$subject.'_queue_'.($queueName??'').'_nats_routing_function')
                ->charge(function(string $subject, \Basis\Nats\Message\Msg $message, WaitGroup $waitGroup, ?string $queueName=null) use ($endpoint) {
                    $result = $this->runThroughKernel(subject: $subject, body: $message->payload->body, headers: $message->payload->headers, queue: $queueName);

                    $this->replyToIfNeeded($message, $result);

                    $waitGroup->done();
                })->args($subject, $message, $waitGroup, $queueName)
                ->runWithClonedDiContainer();
            $waitGroup->wait();
        }
    }

    public function replyToIfNeeded(\Basis\Nats\Message\Msg $message, \Symfony\Component\HttpFoundation\Response $result) {
        if ($message->replyTo) {
            if ($result instanceof JsonResponse) {
                $payloadObj = Payload::parse($result->getContent());
                $payloadObj->headers = array_map(function ($header) {
                    return $header[0];
                }, $result->headers->all());
            } elseif (is_string($result)) {
                $payloadObj = Payload::parse($result);
            } else {
                $payloadObj = Payload::parse(json_encode($result));
            }

            $this->client->publish(
                $message->replyTo,
                $payloadObj,
            );
            Log::info('Replied to ' . $message->replyTo . ' with ' . $payloadObj->render());
        }
    }
}