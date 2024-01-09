<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

use FrockDev\ToolsForLaravel\ExceptionHandlers\Data\ErrorData;
use Hyperf\HttpMessage\Exception\HttpException;
use Throwable;

class CommonErrorHandler
{
    public function handleError(Throwable $throwable): ?ErrorData {
        /** @var ErrorData|null $errorData */
        $errorData = null;
        if ($throwable instanceof HttpException || $throwable instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            if ($throwable->getStatusCode() == 404) {
                return $this->handle404($throwable);
            }
        }

        return null;
    }

    private function handle404(\Symfony\Component\HttpKernel\Exception\HttpException|HttpException $throwable): ErrorData
    {
        $result = new ErrorData();
        $result->errorCode = 404;
        $result->errorData = [
            'error'=>true,
            'errorCode'=>404,
            'errorMessage' => $throwable->getMessage()
        ];
        return $result;
    }
}
