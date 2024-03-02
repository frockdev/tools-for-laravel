<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
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
    protected function run(): bool
    {
        $host = '0.0.0.0';
        $port = 8080;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        Log::info("Http server starting at $host:$port");
        Co::define($this->name . '_server')->charge(function (Server $server) {
            while (true) {
                try {
                    $connection = null;
                    Log::info("Http server started. Waiting for connections...");
                    $connection = $server->acceptConnection();
                    Co::define('http_consumer')
                        ->charge(static function () use ($connection): void {
                            try {
                                $request = $connection->recvHttpRequest();
                                /** @var Kernel $kernel */
                                $kernel = app()->make(Kernel::class);
                                ContextStorage::set('x-trace-id', $request->getHeader('x-trace-id') ?? uuid_create());
                                $convertedHeaders = [];
                                foreach ($request->getHeaders() as $key => $header) {
                                    $convertedHeaders['HTTP_' . $key] = $header[0];
                                }
                                $convertedHeaders['HTTP_x-trace-id'] = ContextStorage::get('x-trace-id');

                                $serverParams = array_merge([
                                    'REQUEST_URI' => $request->getUri()->getPath(),
                                    'REQUEST_METHOD' => $request->getMethod(),
                                    'QUERY_STRING' => $request->getUri()->getQuery(),
                                ], $request->getServerParams(), $convertedHeaders);
                                $laravelRequest = new Request(
                                    query: $request->getQueryParams(),
                                    request: $request->getParsedBody(),
                                    attributes: $request->getAttributes(),
                                    cookies: $request->getCookieParams(),
                                    files: $request->getUploadedFiles(),
                                    server: $serverParams,
                                    content: $request->getBody()->getContents());
                                app()->instance('request', $laravelRequest);
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

                            } catch (ProtocolException $exception) {
                                $connection->error($exception->getCode(), $exception->getMessage(), close: true);
                            }
                            $connection->close();
                        })->run();
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                }
            }
        })->args($server)->run();
        return false;
    }
}
