<?php

namespace FrockDev\ToolsForLaravel\NatsJetstream\Processes;

use Basis\Nats\Message\Payload;
use FrockDev\ToolsForLaravel\ExceptionHandlers\CommonErrorHandler;
use FrockDev\ToolsForLaravel\ExceptionHandlers\Data\ErrorData;
use FrockDev\ToolsForLaravel\NatsJetstream\Events\AfterConsume;
use FrockDev\ToolsForLaravel\NatsJetstream\Events\AfterSubscribe;
use FrockDev\ToolsForLaravel\NatsJetstream\Events\BeforeConsume;
use FrockDev\ToolsForLaravel\NatsJetstream\Events\BeforeSubscribe;
use FrockDev\ToolsForLaravel\NatsJetstream\Events\FailToConsume;
use FrockDev\ToolsForLaravel\NatsJetstream\NatsDriverFactory;
use FrockDev\ToolsForLaravel\NatsJetstream\NatsJetstreamGrpcDriver;
use Google\Protobuf\Internal\Message;
use Hyperf\Context\Context;
use Hyperf\Process\AbstractProcess;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class NatsConsumerProcess extends AbstractProcess
{
    private string $inputType;
    private string $subject;

    private object $endpoint;
    private ?string $queue;

    private NatsJetstreamGrpcDriver $driver;
    private string $poolName;

    private EventDispatcherInterface $dispatcher;
    private string $streamName;
    private ?int $period;
    /**
     * @var \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|mixed
     */
    private ?string $natsTraceIdHeaderName;
    private CommonErrorHandler $natsExceptionHandler;

    public function __construct(
        ContainerInterface $container,
        object $endpoint,
        string $subject,
        string $poolName,
        ?string $queue = '',
        string $streamName = '',
        ?int $processLag = null,
    )
    {
        parent::__construct($container);
        $this->endpoint = $endpoint;
        $this->inputType = ($endpoint)::GRPC_INPUT_TYPE;
        $this->subject = $subject;
        $this->queue = $queue;
        $this->poolName = $poolName;
        $this->streamName = $streamName;
        $this->period = $processLag;

        $this->driver = $this->container->get(NatsDriverFactory::class)
            ->get($this->poolName);

        if ($container->has(EventDispatcherInterface::class)) {
            $this->dispatcher = $container->get(EventDispatcherInterface::class);
        }

        $this->poolName = $poolName;
        $this->container = $container;
        $this->natsTraceIdHeaderName = config('frock.natsTraceIdCtxHeaderName', 'X-Trace-Id');

        $this->natsExceptionHandler = app()->make(CommonErrorHandler::class);
    }

    private function workViaStream(): void {
        $this->dispatcher?->dispatch(new AfterSubscribe([$this->subject]));
        $this->driver->subscribeToStream(
            $this->subject,
            $this->streamName,
            function (Message $data, ?Payload $payload=null) {
                $result = null;
                \Swoole\Coroutine\go(function (Message $data) use (&$result, $payload) {
                try {
                    $this->dispatcher?->dispatch(new BeforeConsume([$this->subject]));
                    $xTraceId = $payload->getHeader($this->natsTraceIdHeaderName) ?? uuid_create();
                    Context::set('X-Trace-Id', $xTraceId);
                    if (!is_null($payload)) {
                        $this->endpoint->setContext($payload->headers);
                    } else {
                        $this->endpoint->setContext([]);
                    }
                    /** @var Message $response */
                    $response = $this->endpoint->__invoke($data);
                    $this->dispatcher?->dispatch(new AfterConsume([$this->subject]));
                    $result = $response->serializeToJsonString();
                } catch (\Throwable $throwable) {
                    $this->dispatcher?->dispatch(new FailToConsume($throwable, json_decode($data->serializeToJsonString(), true)));
                    /** @var ErrorData $errorData */
                    $errorData = $this->natsExceptionHandler->handleError($throwable);
                    Log::error($throwable->getMessage(), [
                        'exception'=>$throwable,
                    ]);
                    $result = json_encode($errorData->errorData);
                }
            }, $data);
                return $result;
            },
            $this->inputType,
            $this->period
        );
    }


    public function handle(): void
    {
        $this->dispatcher?->dispatch(new BeforeSubscribe([$this->subject]));
            if ($this->streamName!='') {
                $this->workViaStream();
                if (!is_null($this->period)) {
                    \Swoole\Timer::tick($this->period, function () {
                        $this->workViaStream();
                    });
                }
            } else {
                $this->driver->subscribe(
                    $this->subject,
                    $this->queue,
                    function (Message $data, ?Payload $payload=null) {
                        $result = null;
                        \Swoole\Coroutine\go(function (Message $data) use (&$result, $payload) {
                            try {
                                $this->dispatcher?->dispatch(new BeforeConsume([$this->subject]));
                                if (!is_null($payload)) {
                                    $this->endpoint->setContext($payload->headers);
                                } else {
                                    $this->endpoint->setContext([]);
                                }
                                /** @var Message $response */
                                $response = $this->endpoint->__invoke($data);
                                $this->dispatcher?->dispatch(new AfterConsume([$this->subject]));
                                $result = $response->serializeToJsonString();
                            } catch (\Throwable $throwable) {
                                $this->dispatcher?->dispatch(new FailToConsume($throwable, json_decode($data->serializeToJsonString(), true)));
                                /** @var ErrorData $errorData */
                                $errorData = $this->natsExceptionHandler->handleError($throwable);
                                Log::error($throwable->getMessage(), [
                                    'exception'=>$throwable,
                                ]);
                                $result = json_encode($errorData->errorData);
                            }
                        }, $data);
                        return $result;
                    },
                    $this->inputType
                );
                $this->dispatcher?->dispatch(new AfterSubscribe([$this->subject]));
                if (!is_null($this->period)) {
                    \Swoole\Timer::tick($this->period, function () {
                        $this->driver->process();
                    });
                } else {
                    while (true) {
                        $this->driver->process();
                    }
                }
            }
            while (true) {
                sleep(1);
            }
    }
}
