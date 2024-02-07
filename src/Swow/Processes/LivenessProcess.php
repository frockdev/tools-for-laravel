<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\CoroutineManager;
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

    protected function run(): void
    {
        $host = '0.0.0.0';
        $port = 9512;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        CoroutineManager::runSafe(function (Server $server, Storage $storage) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    CoroutineManager::runSafe(static function () use ($connection, $storage): void {
                        try {
                            while (true) {
                                $request = null;
                                try {
                                    $request = $connection->recvHttpRequest();
                                    switch ($request->getUri()->getPath()) {
                                        case '/liveness':
                                            $response = new Response();
                                            $response->setStatus($storage->calculateCommonCode());
                                            $response->setBody($storage->renderReportAsAText());
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
                    }, 'livenessHandler');
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                }
            }
        }, $this->name, $server, $this->storage);
    }
}
