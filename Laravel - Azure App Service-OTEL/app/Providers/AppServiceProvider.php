<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TracerInterface::class, function (): TracerInterface {
            $endpoint = config('otel.endpoint');

            if (! is_string($endpoint) || trim($endpoint) === '') {
                return (new NoopTracerProvider)->getTracer('learning/little-poll');
            }

            $endpoint = rtrim(trim($endpoint), '/');
            $scheme = parse_url($endpoint, PHP_URL_SCHEME);

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('OTEL_EXPORTER_OTLP_ENDPOINT must use HTTP or HTTPS.');
            }

            $serviceName = config('otel.service_name');

            if (! is_string($serviceName) || trim($serviceName) === '') {
                throw new InvalidArgumentException('OTEL_SERVICE_NAME must be a non-empty string.');
            }

            $transport = (new OtlpHttpTransportFactory)->create(
                endpoint: $endpoint.'/v1/traces',
                contentType: 'application/x-protobuf',
                headers: $this->otlpHeaders(),
            );
            $resource = ResourceInfoFactory::defaultResource()->merge(
                ResourceInfo::create(Attributes::create([
                    'service.name' => $serviceName,
                ])),
            );
            $provider = new TracerProvider(
                new SimpleSpanProcessor(new SpanExporter($transport)),
                resource: $resource,
            );

            return $provider->getTracer('learning/little-poll');
        });
    }

    public function boot(): void {}

    /**
     * @return array<string, string>
     */
    private function otlpHeaders(): array
    {
        $configuredHeaders = config('otel.headers');

        if (! is_string($configuredHeaders) || trim($configuredHeaders) === '') {
            return [];
        }

        $headers = [];

        foreach (explode(',', $configuredHeaders) as $configuredHeader) {
            $separator = strpos($configuredHeader, '=');

            if ($separator === false) {
                throw new InvalidArgumentException('OTEL_EXPORTER_OTLP_HEADERS must be a comma-separated list of key=value pairs.');
            }

            $name = rawurldecode(trim(substr($configuredHeader, 0, $separator)));
            $value = rawurldecode(trim(substr($configuredHeader, $separator + 1)));

            if ($name === '' || $value === '') {
                throw new InvalidArgumentException('OTEL_EXPORTER_OTLP_HEADERS must contain non-empty header names and values.');
            }

            $headers[$name] = $value;
        }

        return $headers;
    }
}
