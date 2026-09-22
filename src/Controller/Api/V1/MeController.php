<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MeController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserService $userService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->userService->profile($this->userId($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);
        $changes = [];

        foreach (['name' => 100, 'nickname' => 100, 'phone' => 30, 'tagline' => 200] as $field => $maximum) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = $input[$field] === null
                    ? null
                    : RequestValidator::requiredString($input[$field], $maximum);
            }
        }

        if (array_key_exists('email', $input)) {
            $email = $input['email'] === null ? null : RequestValidator::requiredString($input['email'], 255);

            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                RequestValidator::invalid();
            }

            $changes['email'] = $email;
        }

        if (array_key_exists('avatarMediaId', $input)) {
            $changes['avatarMediaId'] = $input['avatarMediaId'] === null
                ? null
                : RequestValidator::requiredString($input['avatarMediaId'], 40);
        }

        return ApiResponse::success($this->userService->updateProfile($this->userId($request), $changes));
    }

    public function activityCounts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->userService->activityCounts($this->userId($request)));
    }

    public function delete(Request $request): Response
    {
        $this->userService->deactivate($this->userId($request));

        return new Response('', 204);
    }

    private function userId(Request $request): int
    {
        $user = $this->authService->requireUser($request->headers->get('Authorization'));

        return (int) $user['id'];
    }
}
