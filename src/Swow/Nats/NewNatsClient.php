<?php

namespace FrockDev\ToolsForLaravel\Swow\Nats;

use Basis\Nats\Configuration;
use Basis\Nats\Message\Connect;
use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Info;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Ping;
use Basis\Nats\Message\Pong;
use Basis\Nats\Message\Prototype;
use Basis\Nats\Message\Publish;
use Basis\Nats\Message\Subscribe;
use Basis\Nats\Message\Unsubscribe;
use Closure;
use Exception;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\Liveness\Liveness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Swow\Channel;
use Swow\Socket;
use Swow\SocketException;

class NewNatsClient
{
    private Connect $connect;
    private string $innerBuffer = '';
    private int $lastPingCount = 0;
    private string $clientName;

    private array $handlers = [];
    private array $subscriptions = [];
    private SwowNatsApi $api;

    public function __construct(Configuration $configuration, string $clientName)
    {
        $this->configuration = $configuration;
        $this->clientName = $clientName;
        $this->api = new SwowNatsApi($this);
        $this->connect();
    }

    protected Configuration $configuration;
    protected ?\Swow\Socket $socket = null;

    public function getApi(): SwowNatsApi
    {
        return $this->api;
    }

    public function dispatch(string $name, mixed $payload, ?float $timeout = null)
    {
        if ($timeout === null) {
            $timeout = $this->configuration->timeout;
        }

        $context = (object) [
            'processed' => false,
            'result' => null,
            'threshold' => microtime(true) + $timeout,
        ];
        $channel = new Channel(1);
        $this->request($name, $payload, function ($result) use ($context, $channel) {
            $context->processed = true;
            $context->result = $result;
            $channel->push($context);
        });
        $context = $channel->pop($timeout*1000+5000); //todo need to normalize timeout

        return $context->result;
    }

    public function api($command, array $args = [], ?Closure $callback = null): ?object
    {
        $subject = "\$JS.API.$command";
        $options = json_encode((object) $args);

        if ($callback) {
            $this->request($subject, $options, $callback);
            return null;
        }

        $result = $this->dispatch($subject, $options);

        if (!$result) {
            throw new Exception('No Result for command ' . $command);
        }

        if (property_exists($result, 'error')) {
            throw new Exception($result->error->description, $result->error->err_code);
        }

        return $result;
    }

    public function request(string $name, mixed $payload, Closure $handler): void
    {
        $replyTo = $this->configuration->inboxPrefix . '.' . bin2hex(random_bytes(16));

        $this->subscribe($replyTo, function ($response) use ($replyTo, $handler) {
            $this->unsubscribe($replyTo);
            $handler($response);
        });

        $this->publish($name, $payload, $replyTo);
    }

    public function subscribe(string $name, Closure $handler): void
    {
        $this->doSubscribe($name, null, $handler);
    }

    public function subscribeQueue(string $name, string $group, Closure $handler): void
    {
        $this->doSubscribe($name, $group, $handler);
    }

    private function doSubscribe(string $subject, ?string $group, Closure $handler): void
    {
        $sid = bin2hex(random_bytes(4));

        $this->handlers[$sid] = $handler;

        $this->send(new Subscribe([
            'sid' => $sid,
            'subject' => $subject,
            'group' => $group,
        ]));
        Log::debug('Subscribed '.$subject.' with sid '.$sid);

        $this->subscriptions[] = [
            'name' => $subject,
            'sid' => $sid,
        ];
    }

    public function unsubscribe(string $name): self
    {
        foreach ($this->subscriptions as $i => $subscription) {
            if ($subscription['name'] == $name) {
                unset($this->subscriptions[$i]);
                $this->send(new Unsubscribe(['sid' => $subscription['sid']]));
                Log::debug('Unsubscribed '.$name.' with sid '.$subscription['sid']);
                unset($this->handlers[$subscription['sid']]);
            }
        }

        return $this;
    }

    public function publish(string $name, mixed $payload, ?string $replyTo = null): void
    {
        $payloadObj = Payload::parse($payload);
        $this->send(new Publish([
            'payload' => $payloadObj,
            'replyTo' => $replyTo,
            'subject' => $name,
        ]));
        Log::debug('Published to '.$name.' '.$payloadObj->render());
    }

    protected function connect() {
        if ($this->socket) {
            return;
        }

        try {
            Log::info('Connecting to NATS', ['host' => $this->configuration->host, 'port' => $this->configuration->port]);
            $this->socket = new Socket(Socket::TYPE_TCP);
            $this->socket->connect($this->configuration->host, $this->configuration->port, 10000);
//            if ($this->configuration->tlsCertFile) {
//                $this->socket->enableCrypto([
//                    'verify_peer'=>false,
//                    'verify_peer_name'=>false,
//                    'allow_self_signed'=>true,
//                    'certificate' => $this->configuration->tlsCertFile,
//                    'certificate_key' => $this->configuration->tlsKeyFile,
//                ]);
//            }
            Log::info('Seems connected', ['host' => $this->configuration->host, 'port' => $this->configuration->port]);
        } catch (SocketException $e) {
            Log::error('Socket error: ' . $e->getMessage(), ['exception' => $e]);
            $this->socket = null;
            throw $e;
        }
        $this->connect = new Connect($this->configuration->getOptions());

        $this->send($this->connect);
        Log::debug('Connected to NATS.');
    }

    protected function send(Prototype $message): void
    {
        $this->connect();
        $line = $message->render() . "\r\n";

        try {
            $this->socket->send($line);
        } catch (\Throwable $e) {
            Log::debug('Problem with sending: ' . $e->getMessage(), ['exception' => $e, 'line' => $line]);
            throw $e;
        }
    }

    protected function readLinesIntoBuffer() {
        try {
            $buffer = new \Swow\Buffer(\Swow\Buffer::COMMON_SIZE);
            $this->socket->recv(
                buffer: $buffer,
                timeout: $this->configuration->timeout * 1000 * 1000
            );
            $data = $buffer->toString();
            $this->innerBuffer .= $data;
            if (strlen($data)===0) {
                Log::info('No data received. Sending PING.');
                $this->ping();
                sleep(1);
            }
        } catch (SocketException $e) {
            if (str_starts_with($e->getMessage(), 'Socket read wait failed, reason: Timed out for')) {
                //lets just send ping and try again
                //lets ping eveery 10th time
                if ($this->lastPingCount>9) {
                    $this->lastPingCount = 0;
                    $this->ping();
                } else {
                    $this->lastPingCount++;
                }
            }
        }
    }

    public function ping(): void
    {
        $this->send(new Ping([]));
        Liveness::setLiveness($this->clientName, 200, 'Ping sent', Liveness::MODE_5_SEC);
    }

    public function pong(): void
    {
        $this->send(new Pong([]));
        Liveness::setLiveness($this->clientName, 200, 'Pong received', Liveness::MODE_5_SEC);
    }

    public function startReceiving(Channel $systemChannel): void {
        try {
            $natsSystemChannel = $systemChannel;
            while (true) {
                if ($natsSystemChannel->getLength() > 0) {
                    $systemMessage = $natsSystemChannel->pop();
                    if ($systemMessage === 'exit') {
                        Log::debug('Got nats exit. Stopping consuming');
                        return;
                    }
                }
                Liveness::setLiveness($this->clientName, 200, 'Receiving', Liveness::MODE_5_SEC);

                $this->connect();

                if ($this->innerBuffer === '') {
                    $this->readLinesIntoBuffer();
                }

                $line = trim($this->getLineByDelimiter("\r\n"));
                if (!$line) continue;

                switch (trim($line)) {
                    case 'PING':
                        $this->pong();
                        continue 2;
                    case 'PONG':
                        Log::info('Got PONG');
                        continue 2;

                    case '+OK':
                        continue 2;
                }

                try {
                    $message = Factory::create(trim($line));
                } catch (\Throwable $exception) {
                    Log::debug($line);
                    throw $exception;
                }

                switch (get_class($message)) {
                    case Info::class:
                        Log::debug('receive ' . $line);
                        continue 2;

                    case Msg::class:
//                    $payload = $line . ' '.$this->getLineByLength($message->length);
                        $payload = $this->getLineByLength($message->length);
                        $message->parse($payload);
                        Log::debug('receive ' . $line . $payload);
                        if (!array_key_exists($message->sid, $this->handlers)) {
                            Log::info('No handler for message ' . $message->render());
                            continue 2;
                        }
                        $result = $this->handlers[$message->sid]($message->payload);
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

                            $this->send(new Publish([
                                'subject' => $message->replyTo,
                                'payload' => $payloadObj,
                            ]));
                            Log::debug('Replied to ' . $message->replyTo . ' with ' . $payloadObj->render());
                        }
                        break;
                }
            }
        } finally {
            ContextStorage::removeSystemChannel('natsReceiveChannel_' . $this->clientName);
        }
    }

    private function getLineByDelimiter(string $delimiter) {
        $delimiterPosition = strpos($this->innerBuffer, $delimiter);
        if ($delimiterPosition === false) {
            $result = $this->innerBuffer;
            $this->innerBuffer='';
            return $result;
        }
        $line = substr($this->innerBuffer, 0, $delimiterPosition);
        $this->innerBuffer = substr($this->innerBuffer, $delimiterPosition + strlen($delimiter));
        return $line;
    }
    private function getLineByLength(int $length) {
        if (strlen($this->innerBuffer) < $length) {
            $result = $this->innerBuffer;
            $this->innerBuffer='';
            $length = $length - strlen($result);
            $this->readLinesIntoBuffer();
            $result.=$this->getLineByLength($length);
            return $result;
        } else {
            $line = substr($this->innerBuffer, 0, $length);
            $this->innerBuffer = substr($this->innerBuffer, $length);
            return $line;
        }
    }

}
