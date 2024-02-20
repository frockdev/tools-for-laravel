<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

use FrockDev\ToolsForLaravel\ExceptionHandlers\Data\ErrorData;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommonErrorHandler
{
    public function handleError(Throwable $throwable): ?ErrorData {
        if ($throwable instanceof \Illuminate\Http\Client\RequestException) {
            return $this->handleHttpClientRequestException($throwable);
        }
        if (
            $throwable instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            if ($throwable->getStatusCode() == 404) {
                return $this->handle404($throwable);
            }
        }
        if ($throwable instanceof \UnexpectedValueException) {
            return $this->handle400($throwable);
        }
        if ($throwable instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->handle404($throwable);
        }
        if ($throwable->getCode() == 404) {
            return $this->handle404($throwable);
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

    private function handle404($throwable): ErrorData
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

    private function handle400(Throwable $throwable)
    {
        if (str_contains($throwable->getMessage(), 'Invalid message property:')) {
            $message = str_replace('Invalid message property:', 'Invalid request field:', $throwable->getMessage());
        } else {
            $message = $throwable->getMessage();
        }
        $result = new ErrorData();
        $result->errorCode = 400;
        $result->errorData = [
            'error'=>true,
            'errorCode'=>400,
            'errorMessage' =>$message
        ];
        return $result;
    }

    private function handleHttpClientRequestException(\Illuminate\Http\Client\RequestException $throwable)
    {
        $result = new ErrorData();
        $result->errorCode = 424;
        $result->errorData = [
            'error'=>true,
            'errorCode'=>424,
            'errorMessage' => 'Http client responded with: '.$throwable->response->status().'. '.$throwable->response->body()
        ];
        return $result;
    }
}
