<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\ExceptionHandlers\CommonErrorHandler;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\CoroutineManager;
use Google\Protobuf\Internal\Message;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException;
use Swow\Psr7\Message\Response;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;

class RpcHttpProcess extends AbstractProcess
{
    private array $routes = [];
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    protected function run(): void
    {
        $host = '0.0.0.0';
        $port = 8082;
        $bindFlag = Socket::BIND_FLAG_NONE;

        $server = new Server(Socket::TYPE_TCP);
        $server->bind($host, $port, $bindFlag)->listen();
        CoroutineManager::runSafe(function (Server $server, array $routes, CommonErrorHandler $errorHandler) {
            while (true) {
                try {
                    $connection = null;
                    $connection = $server->acceptConnection();
                    CoroutineManager::runSafe(static function () use ($connection, $routes, $errorHandler): void {
                        try {
                            while (true) {
                                try {
                                    $request = $connection->recvHttpRequest();
                                    $requestedUri = trim($request->getUri()->getPath(), '/');
                                    ContextStorage::set('X-Trace-Id', $request->getHeader('X-Trace-Id')[0]??uuid_create());
                                    if (!array_key_exists($requestedUri, $routes)) {
                                        $connection->error(\Swow\Http\Status::NOT_FOUND, 'Not Found', close: true);

                                        break;
                                    }
                                    if ($request->getMethod()!=$routes[$requestedUri]['method']) {
                                        $connection->error(\Swow\Http\Status::NOT_ALLOWED, 'Method Not Allowed', close: true);

                                        break;
                                    }
                                    $endpoint = $routes[$requestedUri]['endpoint'];
                                    $convertedHeaders=array_map(function($item) {
                                        return $item[0];
                                    }, $request->getHeaders());
                                    $endpoint->setContext($convertedHeaders);

                                    if ($request->getMethod()==='GET') {
                                        $requestObject = new ($endpoint::GRPC_INPUT_TYPE)($request->getQueryParams());
                                    } elseif ($request->getMethod()==='POST') {
                                        if (!isset($convertedHeaders['Content-Type'])) {
                                            $connection->error(\Swow\Http\Status::BAD_REQUEST, 'Bad Request. Specify Content-Type: application/json', close: true);

                                            break;
                                        }
                                        /** @var Message $requestObject */
                                        $requestObject = new ($endpoint::GRPC_INPUT_TYPE)();
                                        $requestObject->mergeFromJsonString($request->getBody());
                                    } else {
                                        $connection->error(\Swow\Http\Status::NOT_ALLOWED, 'Method Not Allowed', close: true);

                                        break;
                                    }

                                    /** @var Message $result */
                                    $result = $endpoint($requestObject, $connection); //invoke
                                    $response = new Response();
                                    $response->addHeader('Content-Type', 'application/json');
                                    $response->addHeader('X-Trace-Id', ContextStorage::get('X-Trace-Id'));
                                    $response->setStatus(200);

                                    if (method_exists($result, 'serializeViaSymfonySerializer')) {
                                        try {
                                            $response->setBody($result->serializeViaSymfonySerializer());
                                        } catch (\Symfony\Component\Serializer\Exception\NotNormalizableValueException $e) {
                                            $response->setBody($result->serializeToJsonString());
                                        }
                                    } else {
                                        $response->setBody($result->serializeToJsonString());
                                    }
                                    $connection->sendHttpResponse($response);

                                    break;
                                } catch (ProtocolException $exception) {
                                    $connection->error($exception->getCode(), $exception->getMessage(), close: true);

                                    break;
                                } catch (\Throwable $e) {
                                    $errorInfo = $errorHandler->handleError($e);
                                    $response = new Response();
                                    $response->setStatus($errorInfo->errorCode);
                                    $response->addHeader('Content-Type', 'application/json');
                                    $response->addHeader('X-Trace-Id', ContextStorage::get('X-Trace-Id'));
                                    $response->setBody(json_encode($errorInfo->errorData));
                                    $connection->sendHttpResponse($response);

                                    break;
                                }
                            }
                        } finally {
                            $connection->close();
                        }
                    }, 'rpcHttpHandler');
                } catch (SocketException|CoroutineException $exception) {
                    if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                        sleep(1);
                    } else {
                        break;
                    }
                }
            }
        }, $this->name, $server, $this->routes, app()->make(CommonErrorHandler::class));
    }
}
