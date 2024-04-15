<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestFinished;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestStartedHandling;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Support\Facades\Log;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Server\Server;
use Swow\Psr7\Server\ServerConnection;
use Swow\Socket;
use Swow\SocketException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;

class HttpProcess extends AbstractProcess
{

    private function ifTryingToGetFilesFromBuild($request): bool
    {
        return str_starts_with($request->getUri()->getPath(), '/build');
    }

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
                                $tryingGetFilesFromBuild = $this->ifTryingToGetFilesFromBuild($request);
                                if ($tryingGetFilesFromBuild) {
                                    $swowResponse = new \Swow\Psr7\Message\Response();
                                    if (file_exists(public_path($request->getUri()->getPath()))) {
                                        $file = new File(public_path($request->getUri()->getPath()));
                                        $contentOfFIle = $file->getContent();
                                        $swowResponse->setBody($contentOfFIle);
                                        $swowResponse->setStatus(200);
                                        $swowResponse->setHeaders([]);
                                        $swowResponse->setProtocolVersion('1.1');
                                        $connection->sendHttpResponse($swowResponse);
                                        $connection->close();
                                        return;
                                    } else {
                                        $swowResponse->setStatus(404);
                                        $swowResponse->setBody('File not found');
                                        $swowResponse->setHeaders([]);
                                        $swowResponse->setProtocolVersion('1.1');
                                        $connection->sendHttpResponse($swowResponse);
                                        $connection->close();
                                        return;
                                    }
                                }
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
                                    attributes: [...$request->getAttributes(), 'transport'=>'http'],
                                    cookies: $request->getCookieParams(),
                                    files: $request->getUploadedFiles(),
                                    server: $serverParams,
                                    content: $request->getBody()->getContents()
                                );

                                $laravelRequest = Request::createFromBase($symfonyRequest);

                                $dispatcher = app()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                                $dispatcher->dispatch(new RequestStartedHandling($laravelRequest));

                                $kernel = app()->make(HttpKernelContract::class);
                                $response = $kernel->handle($laravelRequest);

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
                            } finally {
                                /** @var \Illuminate\Events\Dispatcher $dispatcher */
                                $dispatcher = ContextStorage::getApplication()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                                $dispatcher->dispatch(new RequestFinished(ContextStorage::getApplication()));
                            }
                            $connection->close();
                        })->args($connection)->runWithClonedDiContainer();
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                } finally {
                    /** @var \Illuminate\Events\Dispatcher $dispatcher */
                    $dispatcher = ContextStorage::getApplication()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                    $dispatcher->dispatch(new RequestFinished(ContextStorage::getApplication()));
                }
            }
        })->args($server)->runWithClonedDiContainer();
        return false;
    }
}
