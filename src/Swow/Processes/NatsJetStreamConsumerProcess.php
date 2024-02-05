<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use Basis\Nats\Message\Payload;
use FrockDev\ToolsForLaravel\ExceptionHandlers\CommonErrorHandler;
use FrockDev\ToolsForLaravel\ExceptionHandlers\Data\ErrorData;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\NatsDriver;
use Google\Protobuf\Internal\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Swow\Coroutine;

class NatsJetStreamConsumerProcess extends AbstractProcess
{
    private object $endpoint;
    private string $subject;
    private string $streamName;
    private ?int $interval;
    private CommonErrorHandler $errorHandler;
    private NatsDriver $driver;

    public function __construct(
        object $endpoint,
        string $subject,
        string $streamName,
        ?int  $interval=null
    )
    {
        $this->endpoint = $endpoint;
        $this->subject = $subject;
        $this->streamName = $streamName;
        $this->interval = $interval;
        $this->driver = new NatsDriver($subject.'_'.$streamName.'_'.Str::random()); //todo check working with singleton, but maybe change to separated connections
        $this->errorHandler = new CommonErrorHandler();
    }

    protected function run(): void
    {
        $this->driver->subscribeToStream(
            $this->subject,
            $this->streamName,
            function (Message $data, ?Payload $payload = null) {
                $resultChannel = new \Swow\Channel(1);
                $traceId = ContextStorage::get('X-Trace-Id');
                Coroutine::run(function (Message $data, ?Payload $payload = null) use ($resultChannel, $traceId) {
                    try {
                        ContextStorage::set('X-Trace-Id', $traceId);
                        if (!is_null($payload)) {
                            $this->endpoint->setContext($payload->headers);
                        } else {
                            $this->endpoint->setContext([]);
                        }
                        /** @var Message $response */
                        $response = $this->endpoint->__invoke($data);
                        if (method_exists($response, 'serializeViaSymfonySerializer')) {
                            try {
                                $result = $response->serializeViaSymfonySerializer();
                            } catch (\Symfony\Component\Serializer\Exception\NotNormalizableValueException $e) {
                                $result = $response->serializeToJsonString();
                            }
                        } else {
                            $result = $response->serializeToJsonString();
                        }
                    } catch (\Throwable $throwable) {
                        /** @var ErrorData $errorData */
                        $errorData = $this->errorHandler->handleError($throwable);
                        Log::error($throwable->getMessage(), [
                            'exception' => $throwable,
                        ]);
                        $result = json_encode($errorData->errorData);
                    } finally {
                        $resultChannel->push($result);
                        ContextStorage::clearStorage();
                    }
                }, $data, $payload);
                $result = $resultChannel->pop(); //todo i think we need add timeout here
                unset ($resultChannel);
                return $result;
            },
            $this->endpoint::GRPC_INPUT_TYPE,
            $this->interval
        );
    }
}
