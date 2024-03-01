<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\ExceptionHandlers\CommonErrorHandler;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Message\Response;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;

class RpcHttpProcess extends AbstractProcess
{
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    protected function run(): bool
    {
        $host = '0.0.0.0';
        $port = 8082;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
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

                                        /** @var Kernel $kernel */
                                        $kernel = app()->make(Kernel::class);
                                        $serverParams = array_merge([
                                            'REQUEST_URI'=> $request->getUri()->getPath(),
                                            'REQUEST_METHOD'=> $request->getMethod(),
                                            'QUERY_STRING'=> $request->getUri()->getQuery(),
                                        ], $request->getServerParams(), $convertedHeaders);
                                        $laravelRequest = new Request(
                                            query: $request->getQueryParams(),
                                            request: $request->getParsedBody(),
                                            attributes: array_merge($request->getAttributes(), ['transport'=>'rpc']),
                                            cookies: $request->getCookieParams(),
                                            files: $request->getUploadedFiles(),
                                            server: $serverParams,
                                            content: $request->getBody()->getContents());
                                        app()->instance('request', $laravelRequest);
                                        /** @var \Illuminate\Http\Response $response */
                                        $response = $kernel->handle(
                                            $laravelRequest
                                        );

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
                                    }

                                    break;
                                } catch (ProtocolException $exception) {
                                    $connection->error($exception->getCode(), $exception->getMessage(), close: true);

                                    break;
                                } catch (\Throwable $e) {
                                    $errorInfo = $errorHandler->handleError($e);
                                    $response = new Response();
                                    $response->setStatus($errorInfo->errorCode);
                                    $response->addHeader('Content-Type', 'application/json');
                                    $response->addHeader('x-trace-id', ContextStorage::get('x-trace-id'));
                                    $response->setBody(json_encode($errorInfo->errorData));
                                    $connection->sendHttpResponse($response);

                                    break;
                                }
                            }
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
        })->args($server, app()->make(CommonErrorHandler::class))->run();
        return false;
    }
}
