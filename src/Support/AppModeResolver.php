<?php

namespace FrockDev\ToolsForLaravel\Support;

use function Hyperf\Support\env;

class AppModeResolver
{
    private const HTTP_APP_MODE = 'http';
    private const GRPC_APP_MODE = 'grpc';
    private const CRON_APP_MODE = 'cron';
    private const HTTP_GRPC_NATS_APP_MODE = 'httpGrpcNats';
    private const HTTP_GRPC_APP_MODE = 'httpGrpc';
    private const NATS_APP_MODE = 'nats';
    private const ALL_APP_MODE = 'all';

    public function isNatsAllowedToRun(): bool {
        return env('APP_MODE')==self::NATS_APP_MODE
            || env('APP_MODE')==self::ALL_APP_MODE;
    }

    public function isHttpAllowedToRun(): bool {
        return env('APP_MODE')==self::HTTP_APP_MODE
            || env('APP_MODE')==self::HTTP_GRPC_APP_MODE
            || env('APP_MODE')==self::HTTP_GRPC_NATS_APP_MODE
            || env('APP_MODE')==self::ALL_APP_MODE;
    }

    public function isGrpcAllowedToRun(): bool {
        return env('APP_MODE')==self::GRPC_APP_MODE
            || env('APP_MODE')==self::HTTP_GRPC_APP_MODE
            || env('APP_MODE')==self::HTTP_GRPC_NATS_APP_MODE
            || env('APP_MODE')==self::ALL_APP_MODE;
    }

    public function isCronAllowedToRun(): bool {
        return env('APP_MODE')==self::CRON_APP_MODE
            || env('APP_MODE')==self::ALL_APP_MODE;
    }
}
