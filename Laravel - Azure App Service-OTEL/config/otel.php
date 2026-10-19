<?php

return [
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
    'headers' => env('OTEL_EXPORTER_OTLP_HEADERS'),
    'service_name' => env('OTEL_SERVICE_NAME', 'little-poll'),
];
