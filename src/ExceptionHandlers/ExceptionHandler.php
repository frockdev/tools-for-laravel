<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

use Throwable;

//@todo check if we need own exceptions handler for Standard Laravel Http Mode
class ExceptionHandler extends \Illuminate\Foundation\Exceptions\Handler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
