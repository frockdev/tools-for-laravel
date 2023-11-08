<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\EndpointCallers\HttpEndpointCaller;
use FrockDev\ToolsForLaravel\Events\RequestGot;
use FrockDev\ToolsForLaravel\MessageObjects\HttpMessageObject;
use Google\Protobuf\Internal\Message;
use Illuminate\Console\Command;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use OpenTracing\Tracer;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

class HttpConsumer extends Command
{
    protected $signature = 'frock:http-consumer';

    public function handle(HttpEndpointCaller $endpointCaller) {
        /** @var Tracer $tracer */
        $tracer = app()->make(Tracer::class);
        // Create new RoadRunner worker from global environment
        $worker = Worker::create();

        // Create common PSR-17 HTTP factory
        $factory = new Psr17Factory();

        $psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

        while (true) {
            try {
                $request = $psr7->waitRequest();
                $scope = $tracer->startActiveSpan('httpRequestStarted', [
                    'tags' => [
                        'http.method' => $request->getMethod(),
                        'http.url' => $request->getUri()->getPath()
                    ],

                ]);
                if ($request === null) {
                    break;
                }

                RequestGot::dispatch();

            } catch (\Throwable $e) {
                // Although the PSR-17 specification clearly states that there can be
                // no exceptions when creating a request, however, some implementations
                // may violate this rule. Therefore, it is recommended to process the
                // incoming request for errors.
                //
                // Send "Bad Request" response.
                $psr7->respond(new Response(400, [], json_encode([
                    'error' => true,
                    'errorMessage' => $e->getMessage(),
                    'errorCode' => $e->getCode(),
                ])));
                continue;
            }

            try {
                $context = [];
                $context = $this->addHeadersToContext($request, $context);
                $method = $request->getMethod();
                $path = $request->getUri()->getPath();

                $routesInfo = config('httpEndpoints');

                if (!array_key_exists($path, $routesInfo)) {
                    $psr7->respond(new Response(404, [], 'Endpoint Not Found'));
                    continue;
                }

                if ($routesInfo[$path]['method']!=='ANY' && $routesInfo[$path]['method']!==$method) {
                    $psr7->respond(new Response(405, [], 'Method Not Allowed'));
                    continue;
                }

                $inputType = $routesInfo[$path]['inputType'];
                /** @var Message $inputObject */
                $inputObject = new $inputType($request->getQueryParams());
                if ($request->getBody()->getContents()!=='') {
                    $inputObject->mergeFromJsonString($request->getBody()->getContents());
                }

                $messageObject = new HttpMessageObject(
                    $inputObject,
                    $routesInfo[$path]['endpoint'],
                    $routesInfo[$path]['inputType'],
                    $routesInfo[$path]['outputType'],
                    $context[config('frock.httpTraceIdCtxHeader', 'X-Trace-ID')] ?? ''
                );

                $response = $endpointCaller->call($context, $messageObject);

                $psr7->respond(new Response(200, [
                    'Content-Type' => 'application/json',
                    config('frock.httpTraceIdCtxHeader', 'X-Trace-ID') => $messageObject->traceId,
                ], $response->serializeToJsonString()));
            } catch (\Throwable $e) {
                if ($e->getCode()>0) {
                    $psr7->respond(
                        new Response(
                            $e->getCode(),
                            [
                                'Content-Type' => 'application/json',
                                config('frock.httpTraceIdCtxHeader', 'X-Trace-ID') => $messageObject->traceId ?? 'noTraceId',
                            ], json_encode([
                                'error' => true,
                                'errorMessage' => $e->getMessage(),
                                'errorCode' => $e->getCode(),
                            ])
                        )
                    );
                } else {
                    $psr7->respond(new Response(500, [
                        'Content-Type' => 'application/json',
                        config('frock.httpTraceIdCtxHeader', 'X-Trace-ID') => $messageObject->traceId,
                    ], json_encode([
                        'error' => true,
                        'errorMessage' => $e->getMessage(),
                        'errorCode' => $e->getCode(),
                    ])));
                    $psr7->getWorker()->error((string)$e);
                }
            }
            $scope->close();
            $tracer->flush();
        }
    }

    private function addHeadersToContext(?\Psr\Http\Message\ServerRequestInterface $request, array $context): array
    {
        if ($request===null) return $context;
        foreach ($request->getHeaders() as $headerName => $headerValues) {
            $context[$headerName] = $headerValues[0];
        }
        return $context;
    }
}
