<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestStartedHandling;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Server\Server;
use Swow\Psr7\Server\ServerConnection;
use Swow\Socket;
use Swow\SocketException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;

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
        Co::define($this->name . '_server')->charge(function: function (Server $server) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    Co::define('http_consumer')
                        ->charge(function: function (ServerConnection $connection): void {
                            try {
                                $request = $connection->recvHttpRequest();
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
                                $symfonyRequest = new \Symfony\Component\HttpFoundation\Request(
                                    query: $request->getQueryParams(),
                                    request: $request->getAttributes(),
                                    attributes: $request->getAttributes(),
                                    cookies: $request->getCookieParams(),
                                    files: $request->getUploadedFiles(),
                                    server: $serverParams,
                                    content: $request->getBody()->getContents()
                                );

                                $laravelRequest = Request::createFromBase($symfonyRequest);

                                $dispatcher = app()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                                $dispatcher->dispatch(new RequestStartedHandling($laravelRequest));

                                /** @var Kernel $kernel */
                                $kernel = app()->make(Kernel::class);
                                /** @var Response $response */
                                $response = $kernel->handle(
                                    $laravelRequest
                                );

                                if ($response instanceof BinaryFileResponse) {
                                    $swowResponse = new \Swow\Psr7\Message\Response();
                                    /** @var File $file */
                                    $file = $response->getFile();
                                    $contentOfFIle = $file->getContent();
                                    $swowResponse->setBody($contentOfFIle);
                                    $swowResponse->setStatus($response->getStatusCode());
                                    $swowResponse->setHeaders($response->headers->all());
                                    $swowResponse->setProtocolVersion($response->getProtocolVersion());
                                } elseif ($response instanceof \Illuminate\Http\Response) {
                                    $swowResponse = new \Swow\Psr7\Message\Response();
                                    $swowResponse->setBody($response->getContent());
                                    $swowResponse->setStatus($response->getStatusCode());
                                    $swowResponse->setHeaders($response->headers->all());
                                    $swowResponse->setProtocolVersion($response->getProtocolVersion());
                                } elseif ($response instanceof \Illuminate\Http\RedirectResponse) {
                                    $swowResponse = new \Swow\Psr7\Message\Response();
                                    $swowResponse->setBody($response->getContent());
                                    $swowResponse->setStatus($response->getStatusCode());
                                    $swowResponse->setHeaders($response->headers->all());
                                    $swowResponse->setProtocolVersion($response->getProtocolVersion());
                                } elseif ($response instanceof \Illuminate\Http\JsonResponse) {
                                    $swowResponse = new \Swow\Psr7\Message\Response();
                                    $swowResponse->setBody($response->getContent());
                                    $swowResponse->setStatus($response->getStatusCode());
                                    $swowResponse->setHeaders($response->headers->all());
                                    $swowResponse->setProtocolVersion($response->getProtocolVersion());
                                } else {
                                    $connection->error(510, 'Unsopported Response Type: '.get_class($response), close: true);
                                }
                                $connection->sendHttpResponse($swowResponse);

                            } catch (ProtocolException $exception) {
                                $connection->error($exception->getCode(), $exception->getMessage(), close: true);
                            }
                            $connection->close();
                        })->args($connection)->runWithClonedDiContainer();
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                }
            }
        })->args($server)->runWithClonedDiContainer();
        return false;
    }
}
