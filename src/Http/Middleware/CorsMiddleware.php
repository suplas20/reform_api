<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CorsMiddleware
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    private const ALLOWED_HEADERS = ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key'];

    /** @var array<string, true> */
    private array $allowedOrigins;

    /** @param list<string> $allowedOrigins */
    public function __construct(array $allowedOrigins)
    {
        $this->allowedOrigins = [];

        foreach ($allowedOrigins as $origin) {
            $origin = rtrim(trim($origin), '/');

            if ($origin !== '') {
                $this->allowedOrigins[$origin] = true;
            }
        }
    }

    public function preflight(Request $request): ?Response
    {
        if (
            $request->getMethod() !== 'OPTIONS'
            || !$this->isApiRequest($request)
            || !$this->isAllowedOrigin($request)
            || !$this->isAllowedRequestedMethod($request)
            || !$this->hasOnlyAllowedRequestedHeaders($request)
        ) {
            return null;
        }

        return $this->applyHeaders($request, new Response(status: Response::HTTP_NO_CONTENT));
    }

    public function applyHeaders(Request $request, Response $response): Response
    {
        if (!$this->isApiRequest($request) || !$this->isAllowedOrigin($request)) {
            return $response;
        }

        $origin = (string) $request->headers->get('Origin');
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', self::ALLOWED_METHODS));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', self::ALLOWED_HEADERS));
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->setVary(array_values(array_unique([...$response->getVary(), 'Origin'])));

        return $response;
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    private function isAllowedOrigin(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        return is_string($origin) && isset($this->allowedOrigins[rtrim($origin, '/')]);
    }

    private function isAllowedRequestedMethod(Request $request): bool
    {
        $method = strtoupper((string) $request->headers->get('Access-Control-Request-Method'));

        return in_array($method, self::ALLOWED_METHODS, true);
    }

    private function hasOnlyAllowedRequestedHeaders(Request $request): bool
    {
        $requested = $request->headers->get('Access-Control-Request-Headers');

        if ($requested === null || trim($requested) === '') {
            return true;
        }

        $allowed = array_map('strtolower', self::ALLOWED_HEADERS);

        foreach (explode(',', $requested) as $header) {
            if (!in_array(strtolower(trim($header)), $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
