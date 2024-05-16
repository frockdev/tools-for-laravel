<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Co\Co;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;

class PrometheusHttpProcess extends AbstractProcess
{

    private CollectorRegistry $registry;

    public function __construct()
    {
        $this->registry = app()->make(CollectorRegistry::class);
    }

    private function renderMetrics() {
        $renderer = new RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }

    protected function run(): bool
    {
        $host = '0.0.0.0';
        $port = 9502;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        Co::define($this->name)->charge(function (Server $server, CollectorRegistry $registry) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    Co::define('prometheusHandler')->charge(static function () use ($connection, $registry): void {
                        try {
                            while (true) {
                                $request = null;
                                try {
                                    $request = $connection->recvHttpRequest();
                                    switch ($request->getUri()->getPath()) {
                                        case '/':
                                        case '/metrics':
                                            $renderer = new RenderTextFormat();
                                            $connection->respond($renderer->render($registry->getMetricFamilySamples()));
                                            break;
                                        default:
                                            $connection->error(\Swow\Http\Status::NOT_FOUND, 'Not Found', close: true);
                                    }
                                } catch (ProtocolException $exception) {
                                    $connection->error($exception->getCode(), $exception->getMessage(), close: true);
                                    break;
                                }
                                if (!$connection->shouldKeepAlive()) {
                                    break;
                                }
                            }
                        } catch (\Throwable $err) {
                            $a=1;
                        } finally {
                            $connection->close();
                        }
                    })->run();
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                }
            }
        })->args($server, $this->registry)->runWithClonedDiContainer();
        return false;
    }
}
