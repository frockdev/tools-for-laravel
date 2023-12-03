<?php

return [
    'traceIdCtxHeader'=> env('TRACE_ID_NATS_HEADER', 'X-Trace-ID'), // todo refactor for nats only
    'httpTraceIdCtxHeader'=> env('TRACE_ID_HTTP_HEADER', 'X-Trace-ID'),
    'disableMetrics'=> env('FROCK_DISABLE_METRICS', false),
];
