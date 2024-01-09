<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

use FrockDev\ToolsForLaravel\ExceptionHandlers\Data\ErrorData;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Illuminate\Support\Facades\Log;
use Swow\Psr7\Message\ResponsePlusInterface;
use Hyperf\HttpMessage\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class HttpExceptionHandler extends ExceptionHandler
{
    private CommonErrorHandler $commonErrorHandler;

    public function __construct(CommonErrorHandler $commonErrorHandler)
    {
        $this->commonErrorHandler = $commonErrorHandler;
    }


    public function handle(Throwable $throwable, ResponsePlusInterface $response)
    {
        $this->stopPropagation();
        $errorData = $this->commonErrorHandler->handleError($throwable);
        if ($errorData=== null) {
            Log::error($throwable->getMessage(), ['message'=>$throwable->getMessage(), 'trace'=>$throwable->getTraceAsString()]);
            if (env('SHOW_UNHANDLED_ERROR_IN_PROD', false)) {
                $message500 = $throwable->getMessage();
            } else {
                $message500 = 'Internal Error';
            }
            return $response->setStatus(500)
                ->setBody(new SwooleStream(
                    json_encode([
                        'error' => true,
                        'errorCode'=>500,
                        'errorMessage' => $message500
                    ], JSON_UNESCAPED_SLASHES)
                ));
        } else {
            return $response->setStatus($errorData->errorCode)
                ->setBody(new SwooleStream(
                    json_encode([
                        'error' => true,
                        'errorCode'=>$errorData->errorCode,
                        'errorMessage' => json_encode($errorData->errorData, JSON_UNESCAPED_SLASHES)
                    ], JSON_UNESCAPED_SLASHES)
                ));
        }

    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
