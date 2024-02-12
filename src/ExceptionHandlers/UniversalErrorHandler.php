<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler;

class UniversalErrorHandler extends Handler
{
    private CommonErrorHandler $commonErrorHandler;
    private Handler $httpErrorHandler;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->httpErrorHandler = app()->make(Handler::class);
        $this->commonErrorHandler = app()->make(CommonErrorHandler::class);
    }

    public function render($request, \Throwable $e)
    {
        $error = $this->commonErrorHandler->handleError($e);
        if ($request->attributes->get('transport')==='rpc') {
            return response()->json($error->errorData)->setStatusCode($error->errorCode);
        }
        return $this->httpErrorHandler->render($request, $e);
    }

    public function report(\Throwable $e) {
        parent::report($e);
    }
}
