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

    public function __construct(Configuration $configuration, string $clientName)
    {
        $this->configuration = $configuration;
        $this->clientName = $clientName;
        $this->api = new SwowNatsApi($this);
        ContextStorage::setSystemChannel('natsReceiveChannel_'.$clientName, new Channel());
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
        $context = $channel->pop($timeout*1000+5000);

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
                unset($this->handlers[$subscription['sid']]);
            }
        }

        return $this;
    }

    public function publish(string $name, mixed $payload, ?string $replyTo = null): void
    {
        $this->send(new Publish([
            'payload' => Payload::parse($payload),
            'replyTo' => $replyTo,
            'subject' => $name,
        ]));
    }

    protected function connect() {
        if ($this->socket) {
            Log::debug('already connected');
            return;
        }

        try {
            $this->socket = new Socket(Socket::TYPE_TCP);
            $this->socket->connect($this->configuration->host, $this->configuration->port, 10);
        } catch (SocketException $e) {
            Log::error('Socket error: ' . $e->getMessage(), ['exception' => $e]);
            $this->socket = null;
            throw $e;
        }
        $this->connect = new Connect($this->configuration->getOptions());

        $this->send($this->connect);
    }

    protected function send(Prototype $message): void
    {
        $this->connect();
        $line = $message->render() . "\r\n";
        Log::debug('send ' . $line);

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
                timeout: $this->configuration->timeout * 1000
            );
            $data = $buffer->toString();
            $this->innerBuffer .= $data;
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
    }

    public function pong(): void
    {
        $this->send(new Pong([]));
    }

    public function startReceiving(): void {
        $natsSystemChannel = ContextStorage::getSystemChannel('natsReceiveChannel_'.$this->clientName);
        while (true) {
            if ($natsSystemChannel->getLength() > 0) {
                $systemMessage = $natsSystemChannel->pop();
                if ($systemMessage === 'exit') {
                    Log::debug('Got nats exit. Stopping consuming');
                    return;
                }
            }

            $this->connect();

            if ($this->innerBuffer === '') {
                $this->readLinesIntoBuffer();
            }

            $line = trim($this->getLineByDelimiter("\r\n"));
            if (!$line) continue;

            switch (trim($line)) {
                case 'PING':
                    Log::debug('received ' . $line);
                    $this->pong();
                    continue 2;
                case 'PONG':
                    Log::debug('received ' . $line);
                    continue 2;

                case '+OK':
                    Log::debug('received ' . $line);
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
                        $this->send(new Publish([
                            'subject' => $message->replyTo,
                            'payload' => Payload::parse($result),
                        ]));
                    }
                    break;
            }
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
            return $result;
        }
        $line = substr($this->innerBuffer, 0, $length);
        $this->innerBuffer = substr($this->innerBuffer, $length);
        return $line;
    }

}
