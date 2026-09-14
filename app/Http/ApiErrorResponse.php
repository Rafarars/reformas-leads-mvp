<?php

namespace App\Http;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Reformas\Shared\Domain\ConflictError;
use Reformas\Shared\Domain\DomainError;
use Reformas\Shared\Domain\InvalidArgumentError;
use Reformas\Shared\Domain\NotFoundError;
use Reformas\Shared\Domain\UnauthorizedError;

// Un solo formato de error para toda la API: {type, message}. El cliente decide por type.
final class ApiErrorResponse
{
    public static function fromDomainError(DomainError $error): JsonResponse
    {
        $status = match (true) {
            $error instanceof InvalidArgumentError => 422,
            $error instanceof NotFoundError => 404,
            $error instanceof ConflictError => 409,
            $error instanceof UnauthorizedError => 401,
            default => 500,
        };

        $response = self::make($error->errorType(), $error->publicMessage(), $status);

        if ($error instanceof UnauthorizedError) {
            $response->headers->set('WWW-Authenticate', 'Bearer');
        }

        return $response;
    }

    public static function validation(ValidationException $exception): JsonResponse
    {
        return self::make('validation_failed', 'Faltan datos o tienen un tipo incorrecto.', 422, [
            'errors' => $exception->errors(),
        ]);
    }

    public static function tooManyRequests(ThrottleRequestsException $exception): JsonResponse
    {
        $response = self::make('too_many_requests', 'Demasiadas peticiones. Vuelve a intentarlo en unos segundos.', 429);
        $response->headers->add($exception->getHeaders());

        return $response;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function make(string $type, string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(['type' => $type, 'message' => $message, ...$extra], $status);
    }
}
