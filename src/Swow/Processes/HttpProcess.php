<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Http\Response;
use Swow\Coroutine;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

class HttpProcess extends AbstractProcess
{

    public function getCleanableInstances(): array {
        return config('frock.cleanableInstances', [

        ]);
    }
    protected function run(): void
    {
        $host = '0.0.0.0';
        $port = 8080;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        Coroutine::run(function (Server $server) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    Coroutine::run(static function () use ($connection): void {
                        try {
                            $request = $connection->recvHttpRequest();
                            $kernel = app()->make(Kernel::class);
                            $serverParams = array_merge([
                                'REQUEST_URI'=> $request->getUri()->getPath(),
                                'REQUEST_METHOD'=> $request->getMethod(),
                                'QUERY_STRING'=> $request->getUri()->getQuery(),
                            ], $request->getServerParams());
                            $laravelRequest = new Request(
                                query: $request->getQueryParams(),
                                request: $request->getParsedBody(),
                                attributes: $request->getAttributes(),
                                cookies: $request->getCookieParams(),
                                files: $request->getUploadedFiles(),
                                server: $serverParams,
                                content: $request->getBody()->getContents());
                            /** @var Response $response */
                            $response = $kernel->handle(
                                $laravelRequest
                            );

                            $swowResponse = new \Swow\Psr7\Message\Response();
                            $swowResponse->setBody($response->getContent());
                            $swowResponse->setStatus($response->getStatusCode());
                            $swowResponse->setHeaders($response->headers->all());
                            $swowResponse->setProtocolVersion($response->getProtocolVersion());

                            $connection->sendHttpResponse($swowResponse);
                            ContextStorage::clearStorage();

                        } catch (ProtocolException $exception) {
                            $connection->error($exception->getCode(), $exception->getMessage(), close: true);
                        }
                        ContextStorage::clearStorage();
                        $connection->close();
                    });
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                }
            }
        }, $server);
    }
}
