<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApplicationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

final class ExceptionHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(Request $request, Throwable $exception): Response
    {
        [$status, $code, $message, $headers] = $this->map($exception);

        if ($status >= 500) {
            $this->logger->error('Unhandled application exception.', [
                'exception_class' => $exception::class,
                'request_method' => $request->getMethod(),
                'request_path' => $request->getPathInfo(),
            ]);
        }

        if ($this->isApiRequest($request)) {
            $response = ApiResponse::error($code, $message, $status);
            $response->headers->add($headers);

            return $response;
        }

        return new Response(
            $message,
            $status,
            array_replace(['Content-Type' => 'text/plain; charset=UTF-8'], $headers),
        );
    }

    /**
     * @return array{int, string, string, array<string, string>}
     */
    private function map(Throwable $exception): array
    {
        if ($exception instanceof ApplicationException) {
            return [
                $exception->statusCode(),
                $exception->errorCode(),
                $exception->userMessage(),
                [],
            ];
        }

        if ($exception instanceof ResourceNotFoundException) {
            return [404, 'ROUTE_NOT_FOUND', '요청한 경로를 찾을 수 없습니다.', []];
        }

        if ($exception instanceof MethodNotAllowedException) {
            return [
                405,
                'METHOD_NOT_ALLOWED',
                '허용되지 않은 요청 방식입니다.',
                ['Allow' => implode(', ', $exception->getAllowedMethods())],
            ];
        }

        return [500, 'INTERNAL_SERVER_ERROR', '서버 오류가 발생했습니다.', []];
    }

    private function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/api' || str_starts_with($path, '/api/');
    }
}
