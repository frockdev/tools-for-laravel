<?php
namespace FrockDev\ToolsForLaravel\NatsJetstream;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Message\Payload;
use Closure;
use FrockDev\ToolsForLaravel\HyperfProxies\StdOutLoggerProxy;
use Google\Protobuf\Internal\Message;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Di\Container;
use Hyperf\Engine\Channel;
use Hyperf\Pool\SimplePool\Pool;
use Illuminate\Support\Facades\Log;
use Hyperf\Pool\SimplePool\PoolFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class NatsJetstreamGrpcDriver
{

    protected Pool $pool;

    private LoggerInterface $logger;

    public function __construct(Container $container, string $name)
    {
        $config = $container->get(ConfigInterface::class)->get('natsJetstream', [])[$name];
        $factory = $container->get(PoolFactory::class);
        $poolConfig = $config['pool'] ?? [];
        $poolConfig['max_idle_time'] = $this->getMaxIdleTime($config);

        $logger = $container->get(LoggerInterface::class);
        $this->logger = $logger;

        $this->pool = $factory->get('natsJetstreamGrpc' . $name, function () use ($config, $logger) {
            $client = new Client(
                new Configuration($config['options']),
                $logger
            );
            if (!array_key_exists('autoconnect', $config) || $config['autoconnect']!==false) {
                $client->connect();
            }
            return $client;
        }, $poolConfig);
    }

    protected function getMaxIdleTime(array $config = []): int
    {
        $timeout = $config['timeout'] ?? intval(ini_get('default_socket_timeout'));

        $maxIdleTime = $config['pool']['max_idle_time'];

        if ($timeout < 0) {
            return $maxIdleTime;
        }

        return (int) min($timeout, $maxIdleTime);
    }

    public function publish(string $subject, string|Message $payload, $replyTo = null): void
    {
        try {
            /** @var ConnectionInterface $connection */
            $connection = $this->pool->get();
            /** @var Client $client */
            $client = $connection->getConnection();
            if (is_string($payload)) {
                $client->publish($subject, $payload, $replyTo);
            } else {
                $client->publish($subject, $payload->serializeToJsonString(), $replyTo);
            }
        } finally {
            isset($connection) && $connection->release();
        }

    }

    public function request(string $subject, string|Message $payload, Closure $callback, string $deserializeTo): void
    {
        if (is_string($payload)) {
            $function = function (Payload $payload) use ($callback, $deserializeTo) {
                return $callback($payload->body);
            };
        } else {
            $function = function (Payload $payload) use ($callback, $deserializeTo) {
                /** @var Message $result */
                $result = new $deserializeTo();
                $result->mergeFromJsonString($payload->body);
                return $callback($result);
            };
        }


        try {
            /** @var ConnectionInterface $connection */
            $connection = $this->pool->get();
            /** @var Client $client */
            $client = $connection->getConnection();
            $client->request($subject, $payload->serializeToJsonString(), $function);
        } finally {
            isset($connection) && $connection->release();
        }
    }

    public function requestSync(string $subject, string|Message $payload, string $deserializeTo): string|Message
    {
        throw new \Exception('need to be fixed first');
        try {
            $channel = new Channel(1);
            $function = function (Payload $payload) use ($deserializeTo, $channel) {
                /** @var Message $deserializedMessage */
                $deserializedMessage = new $deserializeTo();
                $deserializedMessage->mergeFromJsonString($payload->body);
                $channel->push($deserializedMessage);
            };

            /** @var ConnectionInterface $connection */
            $connection = $this->pool->get();
            /** @var Client $client */
            $client = $connection->getConnection();
            $client->request($subject, $payload->serializeToJsonString(), $function);
            $client->process(10);
            $message = $channel->pop(0.001);
            isset($connection) && $connection->release();
            return $message;
        } catch (Throwable $exception) {
            isset($connection) && $connection->release();
            throw $exception;
        }
    }

    private function logMessageFromSubscribe(Payload $payload) {
        $headers = '';
        foreach ($payload->headers as $key=>$value) {
            $headers .= $key . ': ' . $value . ", ";
        }
        Log::debug('NatsJetstreamGrpcDriver: received message at ' . $payload->subject . ', headers: ('. $headers . '),  body:' . $payload->body);
    }

    public function subscribe(string $subject, string $queue, Closure $callback, string $deserializeTo): void
    {
        $function = function (Payload $payload) use ($callback, $deserializeTo) {
            $this->logMessageFromSubscribe($payload);
            /** @var Message $result */
            $result = new $deserializeTo();
            $result->mergeFromJsonString($payload->body);
            return $callback($result, $payload);
        };
        try {
            /** @var ConnectionInterface $connection */
            $connection = $this->pool->get();
            /** @var Client $client */
            $client = $connection->getConnection();
            if ($queue === '') {
                $client->subscribe($subject, $function);
            } else {
                $client->subscribeQueue($subject, $queue, $function);
            }
        } catch (Throwable $exception) {
            isset($connection) && $connection->release();
            throw $exception;
        } finally {
            isset($connection) && $connection->release();
        }
    }

    private function logMessageFromSubscribeToStream(Payload $payload, string $streamName) {
        $headers = '';
        foreach ($payload->headers as $key=>$value) {
            $headers .= $key . ': ' . $value . ", ";
        }
        Log::debug('NatsJetstreamGrpcDriver: received message at ' . $payload->subject . ' and stream: '.$streamName.', headers: ('. $headers . '),  body:' . $payload->body);
    }

    public function subscribeToStream(string $subject, string $streamName, Closure $callback, string $deserializeTo, ?float $period): void
    {
        $function = function (Payload $payload) use ($callback, $deserializeTo, $subject, $streamName) {
            $this->logMessageFromSubscribeToStream($payload, $streamName);
            try {
                /** @var Message $result */
                $result = new $deserializeTo();
                $result->mergeFromJsonString($payload->body);
                return $callback($result, $payload);
            } catch (\Google\Protobuf\Internal\GPBDecodeException $exception) {
                $this->logger->error('Failed to decode message at '.$subject.', '.$streamName.': ' . $exception->getMessage());
                $this->logger->error('Failed to decode message at '.$subject.', '.$streamName.': ' . $payload->body);
                return ['error'=>'true']; //here we need to throw new exception to upper level
            }

        };
        try {
            /** @var ConnectionInterface $connection */
            $connection = $this->pool->get();
            /** @var Client $client */
            $client = $connection->getConnection();
            $jetStream = $client->getApi()->getStream($streamName);
            $consumer = $jetStream->getConsumer($streamName . '-consumer-' . config('app.name') . '-' . config('app.env') . '-' . env('NATS_SPECIAL_POSTFIX', 'default'));
            $consumer->getConfiguration()->setSubjectFilter($subject);
            if (!is_null($period)) {
                $consumer->interrupt();
            }
            $consumer->handle($function);
        } finally {
            isset($connection) && $connection->release();
        }
    }

    public function process(int $timeout = 10)
    {
        try {
            /** @var ConnectionInterface $connection */
            $connection = $this->pool->get();
            /** @var Client $client */
            $client = $connection->getConnection();
            $client->process($timeout);
        } catch (Throwable $exception) {
            isset($connection) && $connection->release();
            throw $exception;
        } finally {
            isset($connection) && $connection->release();
        }
    }
}
