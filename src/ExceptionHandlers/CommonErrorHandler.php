<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

use FrockDev\ToolsForLaravel\ExceptionHandlers\Data\ErrorData;
use Hyperf\HttpMessage\Exception\HttpException;
use Illuminate\Validation\ValidationException;
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
        if ($throwable instanceof ValidationException) {
            return $this->handle422($throwable);
        }
        return $this->handle500($throwable);
    }

    private function handle500(\Throwable $throwable): ErrorData
    {
        $result = new ErrorData();
        $result->errorCode = 500;
        $result->errorData = [
            'error'=>true,
            'errorCode'=>500,
            'errorMessage' => $throwable->getMessage()
        ];
        return $result;
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

    private function handle422(ValidationException $throwable)
    {
        $problems = '';
        foreach ($throwable->errors() as $field => $messages) {
            $problems .= "'".$field.'\': '.implode(', ', $messages).'; ';
        }
        $result = new ErrorData();
        $result->errorCode = 422;
        $result->errorData = [
            'error'=>true,
            'errorCode'=>422,
            'errorMessage' => 'Validation Error. Problems: '.$problems
        ];
        return $result;
    }
}
