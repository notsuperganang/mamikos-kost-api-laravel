<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every API failure as an RFC 9457 problem document with a stable shape,
 * identical to the Spring implementation.
 */
final class ProblemDetail
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (ValidationException $e, Request $request) => self::api($request)
            ? self::response($request, 422, 'validation', 'Validation Failed', 'The given data was invalid.', ['errors' => $e->errors()])
            : null);

        $exceptions->render(fn (InsufficientCreditException $e, Request $request) => self::api($request)
            ? self::response($request, 422, 'insufficient-credit', 'Insufficient Credit', $e->getMessage())
            : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => self::api($request)
            ? self::response($request, 401, 'unauthorized', 'Unauthorized', $e->getMessage() ?: 'Authentication is required.')
            : null);

        $exceptions->render(fn (AuthorizationException $e, Request $request) => self::api($request)
            ? self::response($request, 403, 'forbidden', 'Forbidden', 'You are not allowed to perform this action.')
            : null);

        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => self::api($request)
            ? self::response($request, 404, 'not-found', 'Not Found', class_basename($e->getModel()).' was not found.')
            : null);

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! self::api($request)) {
                return null;
            }

            $status = $e->getStatusCode();

            return match ($status) {
                403 => self::response($request, 403, 'forbidden', 'Forbidden', 'You are not allowed to perform this action.'),
                404 => self::response($request, 404, 'not-found', 'Not Found', 'The requested resource was not found.'),
                429 => self::response($request, 429, 'too-many-requests', 'Too Many Requests', 'Too many attempts, please retry later.', headers: $e->getHeaders()),
                default => self::response($request, $status, 'http-error', Response::$statusTexts[$status] ?? 'Error', $e->getMessage() ?: 'The request could not be processed.', headers: $e->getHeaders()),
            };
        });

        $exceptions->render(fn (Throwable $e, Request $request) => self::api($request) && ! config('app.debug')
            ? self::response($request, 500, 'internal', 'Internal Server Error', 'An unexpected error occurred.')
            : null);
    }

    private static function api(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private static function response(Request $request, int $status, string $type, string $title, string $detail, array $extra = [], array $headers = []): JsonResponse
    {
        $problem = [
            'type' => "/problems/{$type}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.ltrim($request->path(), '/'),
        ] + $extra;

        return response()->json($problem, $status, ['Content-Type' => 'application/problem+json'] + $headers);
    }
}
