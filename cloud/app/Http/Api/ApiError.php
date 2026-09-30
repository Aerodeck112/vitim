<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Services\DuplicateContactException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Formatul unic al erorilor API:
 *   {"error": {"code": "validation_failed", "message": "...", "details": {...}}}
 * Pentru erori neprevăzute nu se expun detalii interne (doar în debug).
 */
final class ApiError
{
    public static function render(Throwable $e): JsonResponse
    {
        [$status, $code, $message, $details] = match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', 'Datele trimise nu sunt valide.', $e->errors()],
            $e instanceof DuplicateContactException => [409, 'duplicate_contact', $e->getMessage(), ['existing_contact_id' => $e->existingContactId, 'identity' => $e->identityType]],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Autentificare necesară.', null],
            $e instanceof AuthorizationException => [403, 'forbidden', 'Nu ai permisiunea pentru această acțiune.', null],
            $e instanceof ModelNotFoundException => [404, 'not_found', 'Resursa nu există.', null],
            $e instanceof ThrottleRequestsException => [429, 'rate_limited', 'Prea multe cereri. Reîncearcă puțin mai târziu.', null],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), self::codeFor($e->getStatusCode()), self::messageFor($e), null],
            default => [500, 'server_error', 'Eroare internă.', config('app.debug') ? ['exception' => $e::class, 'message' => $e->getMessage()] : null],
        };
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse(['error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details], fn ($v) => $v !== null)], $status, $headers);
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            419 => 'csrf_token_mismatch',
            429 => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };
    }

    private static function messageFor(HttpExceptionInterface $e): string
    {
        $message = $e instanceof Throwable ? $e->getMessage() : '';

        return match ($e->getStatusCode()) {
            404 => 'Resursa nu există.',
            403 => $message !== '' ? $message : 'Nu ai permisiunea pentru această acțiune.',
            419 => 'Sesiune expirată. Reîncarcă pagina.',
            default => $message !== '' && $e->getStatusCode() < 500 ? $message : 'Eroare.',
        };
    }
}
