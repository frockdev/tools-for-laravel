<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\Liveness\Storage;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Message\Response;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;

class LivenessProcess extends AbstractProcess
{

    public function __construct(private Storage $storage)
    {

    }

    protected function run(): bool
    {
        $host = '0.0.0.0';
        $port = 9512;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        Co::define($this->name)
            ->charge(function (Server $server, Storage $storage) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    Co::define('livenessHandler')->charge(static function () use ($connection, $storage): void {
                        try {
                            while (true) {
                                $request = null;
                                try {
                                    $request = $connection->recvHttpRequest();
                                    switch ($request->getUri()->getPath()) {
                                        case '/liveness':
                                            $response = new Response();
                                            $response->setStatus($storage->calculateCommonCode());
                                            $response->addHeader('Content-Type', 'text/html');
                                            $response->setBody($storage->renderReportAsAHtmlTable());
                                            $connection->sendHttpResponse($response);
                                            break;
                                        case '/':
                                            $connection->error(\Swow\Http\Status::NOT_FOUND, 'Not Found', close: true);
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
        })->args($server, $this->storage)->run();
        return false;
    }
}
