<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function socialLogin(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);
        $provider = $this->requiredString($input['provider'] ?? null, 20);
        $provider = strtolower($provider);

        if (!in_array($provider, ['kakao', 'naver', 'google', 'apple'], true)) {
            $this->invalid();
        }

        $authorizationCode = $this->optionalString($input['authorizationCode'] ?? null, 4000);
        $idToken = $this->optionalString($input['idToken'] ?? null, 10000);
        if (($authorizationCode === null) === ($idToken === null)) {
            $this->invalid();
        }
        $redirectUri = $this->optionalString($input['redirectUri'] ?? null, 1000);
        if ($authorizationCode !== null && $redirectUri === null) {
            $this->invalid();
        }

        $device = $input['device'] ?? null;

        if (!is_array($device)) {
            $this->invalid();
        }

        return ApiResponse::success($this->authService->socialLogin(
            strtoupper($provider),
            $authorizationCode,
            $idToken,
            $redirectUri ?? '',
            $this->requiredString($device['deviceId'] ?? null, 100),
            $this->optionalString($input['state'] ?? null, 200),
        ));
    }

    public function naverCallback(Request $request): RedirectResponse
    {
        $state = $this->requiredString($request->query->all()['state'] ?? null, 200);
        if (preg_match('/^[A-Za-z0-9_-]{20,200}$/D', $state) !== 1) {
            $this->invalid();
        }
        $params = ['state' => $state];
        $error = $this->optionalString($request->query->all()['error'] ?? null, 100);
        if ($error !== null) {
            $params['error'] = $error === 'access_denied' ? 'access_denied' : 'server_error';
        } else {
            $params['code'] = $this->requiredString($request->query->all()['code'] ?? null, 4000);
        }
        // Fixed app destination: never accept a return URL from request input.
        return new RedirectResponse(
            'reform://auth/naver-callback?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            302,
            ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer'],
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);

        return ApiResponse::success($this->authService->refresh(
            $this->requiredString($input['refreshToken'] ?? null, 200),
            $this->requiredString($input['deviceId'] ?? null, 100),
        ));
    }

    public function logout(Request $request): Response
    {
        $user = $this->authService->requireUser($request->headers->get('Authorization'));
        $input = RequestValidator::json($request);
        $this->authService->logout(
            (int) $user['id'],
            $this->requiredString($input['refreshToken'] ?? null, 200),
            $this->requiredString($input['deviceId'] ?? null, 100),
        );

        return new Response('', 204);
    }

    private function requiredString(mixed $value, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);

        if ($value === null) {
            $this->invalid();
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $this->invalid();
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > $maximum) {
            $this->invalid();
        }

        return $value;
    }

    private function invalid(): never
    {
        throw new ValidationException('VALIDATION_ERROR', '입력값을 확인해주세요.');
    }
}
