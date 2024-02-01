<?php

namespace FrockDev\ToolsForLaravel\Swow\Nats;

use Basis\Nats\Authenticator;
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
use Illuminate\Support\Facades\Log;
use LogicException;
use Psr\Log\LoggerInterface;
use Swow\SocketException;
use Throwable;

class SwowNatsClient2
{
    public Connect $connect;
    public Info $info;
    public readonly SwowNatsApi $api;

    private readonly ?Authenticator $authenticator;

    private ?\Swow\Socket $socket = null;
    private $context;
    private array $handlers = [];
    private float $ping = 0;
    private float $pong = 0;
    private ?float $lastDataReadFailureAt = null;
    private string $name = '';
    private array $subscriptions = [];

    private bool $skipInvalidMessages = false;

    public function __construct(
        public readonly Configuration $configuration = new Configuration(),
    )
    {
        $this->api = new SwowNatsApi($this);

        $this->authenticator = Authenticator::create($this->configuration);
    }

    public function api($command, array $args = [], ?Closure $callback = null): ?object
    {
        $subject = "\$JS.API.$command";
        $options = json_encode((object)$args);

        if ($callback) {
            return $this->request($subject, $options, $callback);
        }

        $result = $this->dispatch($subject, $options);

        if (property_exists($result, 'error')) {
            throw new Exception($result->error->description, $result->error->err_code);
        }

        if (!$result) {
            return null;
        }

        return $result;
    }

    /**
     * @return $this
     * @throws Throwable
     */
    public function connect(): self
    {
        if ($this->socket) {
            Log::debug('already connected');
            return $this;
        }

        $config = $this->configuration;

        $dsn = "$config->host";

        $this->socket = new \Swow\Socket(\Swow\Socket::TYPE_TCP);
        try {
            $this->socket->connect($dsn, $config->port, 10);
        } catch (SocketException $e) {
            throw new \Exception('We have problem with connection, consuming is stopping', 633, $e);
        }

        $this->process($config->timeout);

        $this->connect = new Connect($config->getOptions());

        if ($this->name) {
            $this->connect->name = $this->name;
        }
        if (isset($this->info->nonce) && $this->authenticator) {
            $this->connect->sig = $this->authenticator->sign($this->info->nonce);
        }

        $this->send($this->connect);

        return $this;
    }

    public function dispatch(string $name, mixed $payload, ?float $timeout = null)
    {
        if ($timeout === null) {
            $timeout = $this->configuration->timeout;
        }

        $context = (object)[
            'processed' => false,
            'result' => null,
            'threshold' => microtime(true) + $timeout,
        ];

        $this->request($name, $payload, function ($result) use ($context) {
            $context->processed = true;
            $context->result = $result;
        });

        while (!$context->processed && microtime(true) < $context->threshold) {
            $this->process();
        }

        if (!$context->processed) {
            throw new LogicException("Processing timeout");
        }

        return $context->result;
    }

    public function getApi(): SwowNatsApi
    {
        return $this->api;
    }

    public function ping(): bool
    {
        $this->ping = microtime(true);
        $this->send(new Ping([]));
        $this->process($this->configuration->timeout);
        $result = $this->ping <= $this->pong;
        $this->ping = 0;

        return $result;
    }

    public function publish(string $name, mixed $payload, ?string $replyTo = null): self
    {
        return $this->send(new Publish([
            'payload' => Payload::parse($payload),
            'replyTo' => $replyTo,
            'subject' => $name,
        ]));
    }

    public function request(string $name, mixed $payload, Closure $handler): self
    {
        $replyTo = $this->configuration->inboxPrefix . '.' . bin2hex(random_bytes(16));

        $this->subscribe($replyTo, function ($response) use ($replyTo, $handler) {
            $this->unsubscribe($replyTo);
            $handler($response);
        });

        $this->publish($name, $payload, $replyTo);
//        $this->process($this->configuration->timeout);
        $this->process();

        return $this;
    }

    public function subscribe(string $name, Closure $handler): self
    {
        return $this->doSubscribe($name, null, $handler);
    }

    public function subscribeQueue(string $name, string $group, Closure $handler)
    {
        return $this->doSubscribe($name, $group, $handler);
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

    public function setDelay(float $delay, string $mode = Configuration::DELAY_CONSTANT): self
    {
        $this->configuration->setDelay($delay, $mode);
        return $this;
    }

//    public function setLogger(?LoggerInterface $logger): self
//    {
//        $this->logger = $logger;
//        return $this;
//    }

//    public function setTimeout(float $value): self
//    {
//        $this->connect();
//        $seconds = (int)floor($value);
//        $milliseconds = (int)(1000 * ($value - $seconds));
//
//        stream_set_timeout($this->socket, $seconds, $milliseconds);
//
//        return $this;
//    }

    /**
     * @throws Throwable
     */
    public function process(null|int|float $timeout = 0)
    {
        if ($this->innerBuffer === '') {
            $this->readToInnerBuffer();
        }
        Log::debug('BUFFER_BUFFER_BUFFER: '.$this->innerBuffer);

        $this->lastDataReadFailureAt = null;
        $max = microtime(true) + $timeout;
        $ping = time() + $this->configuration->pingInterval;

        $iteration = 0;
        while (true) {
            try {
                $line = $this->getMessageFromInnerBuffer("\r\n");

                if ($line && ($this->ping || trim($line) != 'PONG')) {
                    break;
                }
                if (!$line && $ping < time()) {
                    try {
                        $this->send(new Ping([]));
                        $line = $this->getMessageFromInnerBuffer("\r\n");
                        $ping = time() + $this->configuration->pingInterval;
                        if ($line && ($this->ping || trim($line) != 'PONG')) {
                            break;
                        }
                    } catch (Throwable $e) {
                        if ($this->ping) {
                            return;
                        }
                        $this->processSocketException($e);
                    }
                }
                $now = microtime(true);
                if ($now >= $max) {
                    return null;
                }
                $this->configuration->delay($iteration++);
            } catch (Throwable $e) {
                $this->processSocketException($e);
            }
        }

        switch (trim($line)) {
            case 'PING':
                Log::debug('receive ' . $line);
                $this->send(new Pong([]));
                $now = microtime(true);
                if ($now >= $max) {
                    return null;
                }
                return $this->process($max - $now);

            case 'PONG':
                Log::debug('receive ' . $line);
                return $this->pong = microtime(true);

            case '+OK':
                return Log::debug('receive ' . $line);
        }

        try {
            $message = Factory::create(trim($line));
        } catch (Throwable $exception) {
            Log::debug($line);
            throw $exception;
        }

        switch (get_class($message)) {
            case Info::class:
                Log::debug('receive ' . $line);
                $this->handleInfoMessage($message);
                return $this->info = $message;

            case Msg::class:
                $payload = $this->getMessageFromInnerBufferByLength($message->length);
                $message->parse($payload);
                Log::debug('receive ' . $line);
                if (!array_key_exists($message->sid, $this->handlers)) {
                    if ($this->skipInvalidMessages) {
                        return;
                    }
                    throw new LogicException("No handler for message $message->sid");
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

    /**
     * @throws Exception
     */
    private function handleInfoMessage(Info $info): void
    {
        if (isset($info->tls_verify) && $info->tls_verify) {
            $this->enableTls(true);
        } elseif (isset($info->tls_required) && $info->tls_required) {
            $this->enableTls(false);
        }
    }


    /**
     *
     *
     * @throws Exception
     */
    private function enableTls(bool $requireClientCert): void
    {
        throw new \Exception('TLS is not supported yet');
        if ($requireClientCert) {
            if (!empty($this->configuration->tlsKeyFile)) {
                if (!file_exists($this->configuration->tlsKeyFile)) {
                    throw new Exception("tlsKeyFile file does not exist: " . $this->configuration->tlsKeyFile);
                }
//                stream_context_set_option($this->context, 'ssl', 'local_pk', $this->configuration->tlsKeyFile);
            }
            if (!empty($this->configuration->tlsCertFile)) {
                if (!file_exists($this->configuration->tlsCertFile)) {
                    throw new Exception("tlsCertFile file does not exist: " . $this->configuration->tlsCertFile);
                }
//                stream_context_set_option($this->context, 'ssl', 'local_cert', $this->configuration->tlsCertFile);
            }
        }

        if (!empty($this->configuration->tlsCaFile)) {
            if (!file_exists($this->configuration->tlsCaFile)) {
                throw new Exception("tlsCaFile file does not exist: " . $this->configuration->tlsCaFile);
            }
//            stream_context_set_option($this->context, 'ssl', 'cafile', $this->configuration->tlsCaFile);
        }

//        if (!stream_socket_enable_crypto(
//            $this->socket,
//            true,
//            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
//        )) {
//            throw new Exception('Failed to connect: Error enabling TLS');
//        }
    }


    private function doSubscribe(string $subject, ?string $group, Closure $handler): self
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

        return $this;
    }

    private function processSocketException(Throwable $e): self
    {
        if (!$this->configuration->reconnect) {
            Log::debug($e->getMessage());
            throw $e;
        }

        $iteration = 0;

        while (true) {
            try {
                $this->socket = null;
                $this->connect();
            } catch (Throwable $e) {
                $this->configuration->delay($iteration++);
                continue;
            }
            break;
        }

        foreach ($this->subscriptions as $i => $subscription) {
            $this->send(new Subscribe([
                'sid' => $subscription['sid'],
                'subject' => $subscription['name'],
            ]));
        }
        return $this;
    }

    private function send(Prototype $message): self
    {

        $this->connect();
        $line = $message->render() . "\r\n";
        Log::debug('send ' . $line);

        try {
            $this->socket->send($line);
        } catch (Throwable $e) {
            $this->processSocketException($e);
            $line = $message->render() . "\r\n";
        }

        if ($this->configuration->verbose && $line !== "PING\r\n") {
            // get feedback
            $this->process($this->configuration->timeout);
        }

        return $this;

//        $this->connect();
//
//        $line = $message->render() . "\r\n";
//        $length = strlen($line);
//
//        Log::debug('send ' . $line);
//
//        while (strlen($line)) {
//            try {
//                $written = @fwrite($this->socket, $line, 1024);
//                if ($written === false) {
//                    throw new LogicException('Error sending data');
//                }
//                if ($written === 0) {
//                    throw new LogicException('Broken pipe or closed connection');
//                }
//                if ($length == $written) {
//                    break;
//                }
//                $line = substr($line, $written);
//            } catch (Throwable $e) {
//                $this->processSocketException($e);
//                $line = $message->render() . "\r\n";
//            }
//        }
//
//        if ($this->configuration->verbose && $line !== "PING\r\n") {
//            // get feedback
//            $this->process($this->configuration->timeout);
//        }
//
//        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function skipInvalidMessages(bool $skipInvalidMessages): self
    {
        $this->skipInvalidMessages = $skipInvalidMessages;
        return $this;
    }

    private string $innerBuffer = '';

    /**
     * @param string $source
     * @param string $delimiter
     * @return string
     * This function should find first $delimiter substring in $source and return all data before it
     * and after that should remove from $source returned data including $delimiter
     */
    private function getMessageFromInnerBuffer(string $delimiter): string
    {
        $delimiterPosition = strpos($this->innerBuffer, $delimiter);
        if (strlen($this->innerBuffer)===0) return '';
        if ($delimiterPosition === false) {
            $result =  $this->innerBuffer;
            $this->innerBuffer = '';
            return $result;
        }

        $message = substr($this->innerBuffer, 0, $delimiterPosition);
        $this->innerBuffer = substr($this->innerBuffer, $delimiterPosition + strlen($delimiter));

        return $message;
    }

    private function readToInnerBuffer()
    {
        $buffer = new \Swow\Buffer(\Swow\Buffer::COMMON_SIZE);
        $this->socket->recv(
            buffer: $buffer,
            timeout: $this->configuration->timeout * 1000
        );
        $this->innerBuffer .= $buffer->toString();
    }

    private function getMessageFromInnerBufferByLength(int $length): string
    {
        $message = substr($this->innerBuffer, 0, $length);
        $this->innerBuffer = substr($this->innerBuffer, $length);

        return $message;
    }

    /**
     * @param int $length
     * @param string $ending
     * @param bool $checkTimeout
     * @return string|bool
     * @deprecated
     */
//    private function readLine(int $length, string $ending = '', bool $checkTimeout = true): string|bool
//    {
//        $line = $this->getMessageFromInnerBuffer($this->innerBuffer, $ending);
//        if ($line || !$checkTimeout) {
//            $this->lastDataReadFailureAt = null;
//            return $line;
//        }
//
//        $now = microtime(true);
//        $this->lastDataReadFailureAt = $this->lastDataReadFailureAt ?? $now;
//        $timeWithoutDataRead = $now - $this->lastDataReadFailureAt;
//
//        if ($timeWithoutDataRead > $this->configuration->timeout) {
//            throw new LogicException('Socket read timeout');
//        }
//
//        return false;
//    }
}
