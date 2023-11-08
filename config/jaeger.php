<?php

return [
    'traceEnabled'=>env('JAEGER_TRACE_ENABLED', false),
    'samplerType'=>env('JAEGER_SAMPLER_TYPE', \Jaeger\SAMPLER_TYPE_CONST),
    'reportingHost'=>env('JAEGER_REPORTING_HOST', 'jaeger-all-in-one1.jaeger'),
    'reportingPort'=>env('JAEGER_REPORTING_PORT', 6832),
];
