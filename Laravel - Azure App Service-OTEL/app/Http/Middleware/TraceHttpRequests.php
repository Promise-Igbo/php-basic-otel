<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TraceHttpRequests
{
    public function __construct(private readonly TracerInterface $tracer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $span = $this->tracer
            ->spanBuilder('HTTP '.$request->method())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.request.method', $request->method())
            ->startSpan();
        $scope = $span->activate();

        try {
            $response = $next($request);
            $route = $request->route();

            if ($route instanceof Route) {
                $routePath = '/'.ltrim($route->uri(), '/');
                $span->updateName($request->method().' '.$routePath);
                $span->setAttribute('http.route', $routePath);
            }

            $statusCode = $response->getStatusCode();
            $span->setAttribute('http.response.status_code', $statusCode);

            if ($statusCode >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }

            return $response;
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR);

            throw $exception;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
