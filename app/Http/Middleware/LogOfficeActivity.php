<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogOfficeActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $response;
        }

        if (!$request->user()) {
            return $response;
        }

        $route = $request->route();
        $action = $this->determineAction($request);
        $model = $this->extractModel($response);
        $oldValues = $request->input('_old_values', []);
        $newValues = $request->except(['_old_values', '_token', 'password', 'password_confirmation']);

        AuditLogService::log(
            action: $action,
            model: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            request: $request,
        );

        return $response;
    }

    protected function determineAction(Request $request): string
    {
        $method = $request->method();
        $routeName = $request->route()?->getName() ?? '';

        $base = match ($method) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'modified',
        };

        $resource = $this->extractResourceName($routeName);

        return "{$resource}.{$base}";
    }

    protected function extractResourceName(string $routeName): string
    {
        $parts = explode('.', $routeName);

        return $parts[0] ?? 'unknown';
    }

    protected function extractModel(Response $response)
    {
        if (!$response instanceof \Illuminate\Http\JsonResponse) {
            return null;
        }

        $data = $response->getData(true);

        if (!is_array($data)) {
            return null;
        }

        $resource = $data['data'] ?? $data;

        if (!is_array($resource)) {
            return null;
        }

        $modelClass = $resource['_model'] ?? null;

        if ($modelClass && class_exists($modelClass) && isset($resource['id'])) {
            return $modelClass::find($resource['id']);
        }

        return null;
    }
}
