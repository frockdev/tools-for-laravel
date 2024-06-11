<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\ExceptionHandlers\CommonErrorHandler;
use FrockDev\ToolsForLaravel\Support\HttpHelperFunctions;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestFinished;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Illuminate\Support\Facades\Log;
use Swow\Psr7\Message\Response;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;

class RpcHttpProcess extends AbstractProcess
{
    protected function run(): bool
    {
        $host = '0.0.0.0';
        $port = 8082;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        Log::info('RPC HTTP server is starting...');
        sleep(1);
        Co::define($this->name)->charge(function (Server $server, CommonErrorHandler $errorHandler) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    Co::define('rpcHttpHandler')->charge(static function () use ($connection, $errorHandler): void {
                        try {
                            while (true) {
                                try {
                                    $request = $connection->recvHttpRequest();
                                    ContextStorage::set('x-trace-id', $request->getHeader('x-trace-id')[0]??uuid_create());
                                    try {
                                        $convertedHeaders = [];
                                        foreach ($request->getHeaders() as $key=>$value) {
                                            $convertedHeaders['HTTP_'.$key] = $value[0];
                                        }
                                        $convertedHeaders['HTTP_x-trace-id'] = ContextStorage::get('x-trace-id');

                                        $serverParams = array_merge([
                                            'REQUEST_URI'=> $request->getUri()->getPath(),
                                            'REQUEST_METHOD'=> $request->getMethod(),
                                            'QUERY_STRING'=> $request->getUri()->getQuery(),
                                        ], $request->getServerParams(), $convertedHeaders);
                                        $parsedBody = HttpHelperFunctions::buildNestedArrayFromParsedBody($request->getParsedBody());
                                        $symfonyRequest = new \Symfony\Component\HttpFoundation\Request(
                                            query: $request->getQueryParams(),
                                            request: $parsedBody,
                                            attributes: $request->getAttributes(),
                                            cookies: $request->getCookieParams(),
                                            files: $request->getUploadedFiles(),
                                            server: $serverParams,
                                            content: $request->getBody()->getContents()
                                        );
                                        $laravelRequest = Request::createFromBase($symfonyRequest);
                                        $dispatcher = app()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                                        $dispatcher->dispatch(new RequestReceived(ContextStorage::getMainApplication(), app(), $laravelRequest));
                                        /** @var HttpKernelContract $kernel */
                                        $kernel = app()->make(HttpKernelContract::class);
                                        $response = $kernel->handle($laravelRequest);
                                        $dispatcher->dispatch(new RequestHandled(app(), $laravelRequest, $response));

                                        $swowResponse = new \Swow\Psr7\Message\Response();
                                        $swowResponse->setBody($response->getContent());
                                        $swowResponse->setStatus($response->getStatusCode());
                                        $swowResponse->setHeaders($response->headers->all());
                                        $swowResponse->setProtocolVersion($response->getProtocolVersion());

                                        $connection->sendHttpResponse($swowResponse);
                                    } catch (\Throwable $e) {
                                        $errorInfo = $errorHandler->handleError($e);
                                        $response = new Response();
                                        $response->setStatus($errorInfo->errorCode);
                                        $response->addHeader('Content-Type', 'application/json');
                                        $response->addHeader('x-trace-id', ContextStorage::get('x-trace-id'));
                                        $response->setBody(json_encode($errorInfo->errorData));
                                        $connection->sendHttpResponse($response);
                                    } finally {
                                        /** @var \Illuminate\Events\Dispatcher $dispatcher */
                                        $dispatcher = ContextStorage::getApplication()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                                        $dispatcher->dispatch(new RequestFinished(ContextStorage::getApplication()));
                                    }

                                    break;
                                } catch (ProtocolException $exception) {
                                    $connection->error($exception->getCode(), $exception->getMessage(), close: true);

                                    break;
                                } catch (\Throwable $e) {
                                    Log::error($e->getMessage(), ['exception'=>$e]);
                                    $errorInfo = $errorHandler->handleError($e);
                                    $response = new Response();
                                    $response->setStatus($errorInfo->errorCode);
                                    $response->addHeader('Content-Type', 'application/json');
                                    $response->addHeader('x-trace-id', ContextStorage::get('x-trace-id'));
                                    $response->setBody(json_encode($errorInfo->errorData));
                                    $connection->sendHttpResponse($response);

                                    break;
                                } finally {
                                    /** @var \Illuminate\Events\Dispatcher $dispatcher */
                                    $dispatcher = ContextStorage::getApplication()->make(\Illuminate\Contracts\Events\Dispatcher::class);
                                    $dispatcher->dispatch(new RequestFinished(ContextStorage::getApplication()));
                                }
                            }
                        } catch (\Throwable $e) {
                            Log::error($e->getMessage(), ['exception'=>$e]);
                            $errorInfo = $errorHandler->handleError($e);
                            $response = new Response();
                            $response->setStatus($errorInfo->errorCode);
                            $response->addHeader('Content-Type', 'application/json');
                            $response->addHeader('x-trace-id', ContextStorage::get('x-trace-id'));
                            $response->setBody(json_encode($errorInfo->errorData));
                            $connection->sendHttpResponse($response);
                        } finally {
                            $connection->close();
                            $dispatcher->dispatch(new RequestTerminated(ContextStorage::getMainApplication(), app(), $laravelRequest, $response));

                            $route = $laravelRequest->route();

                            if ($route instanceof Route && method_exists($route, 'flushController')) {
                                $route->flushController();
                            }
                        }
                    })->runWithClonedDiContainer();
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
        })->args($server, app()->make(CommonErrorHandler::class))->run();
        return false;
    }
}
