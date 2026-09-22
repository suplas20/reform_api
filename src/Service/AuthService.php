<?php

declare(strict_types=1);

namespace App\Service;

use App\Auth\SocialIdentityVerifier;
use App\Database\TransactionManager;
use App\Exception\ServiceUnavailableException;
use App\Exception\UnauthorizedException;
use App\Repository\AuthRepository;
use DateTimeImmutable;
use DateTimeZone;

final class AuthService
{
    public function __construct(
        private readonly AuthRepository $authRepository,
        private readonly SocialIdentityVerifier $socialIdentityVerifier,
        private readonly TransactionManager $transactionManager,
        private readonly int $accessTtlSeconds,
        private readonly int $refreshTtlSeconds,
    ) {
    }

    /** @return array<string, mixed> */
    public function socialLogin(
        string $provider,
        ?string $authorizationCode,
        ?string $idToken,
        string $redirectUri,
        string $deviceId,
    ): array {
        $identity = $this->socialIdentityVerifier->verify(
            $provider,
            $authorizationCode,
            $idToken,
            $redirectUri,
        );
        $existing = $this->authRepository->findSocialUser($provider, $identity['providerUserId']);
        $isNewUser = $existing === null;

        return $this->transactionManager->run(function () use (
            $existing,
            $identity,
            $provider,
            $deviceId,
            $isNewUser,
        ): array {
            $now = gmdate('Y-m-d H:i:s');
            $userId = $existing === null
                ? $this->authRepository->createUser($identity['name'], $identity['email'], $now)
                : (int) $existing['id'];

            if ($existing === null) {
                $this->authRepository->createSocialAccount(
                    $userId,
                    $provider,
                    $identity['providerUserId'],
                    $identity['email'],
                    $now,
                );
            }

            $tokens = $this->issueTokens();
            $this->authRepository->createSession(
                $userId,
                $deviceId,
                self::hashToken($tokens['accessToken']),
                self::hashToken($tokens['refreshToken']),
                $tokens['accessExpiresAtDatabase'],
                $tokens['refreshExpiresAtDatabase'],
                $now,
            );
            $user = $this->authRepository->findActiveUserById($userId);

            return [
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken'],
                'accessTokenExpiresAt' => $tokens['accessTokenExpiresAt'],
                'isNewUser' => $isNewUser,
                'user' => self::mapLoginUser($user ?? ['id' => $userId, 'name' => null, 'nickname' => null]),
            ];
        });
    }

    /** @return array<string, string> */
    public function refresh(string $refreshToken, string $deviceId): array
    {
        $session = $this->authRepository->findRefreshSession(
            self::hashToken($refreshToken),
            $deviceId,
            gmdate('Y-m-d H:i:s'),
        );

        if ($session === null) {
            throw new UnauthorizedException('REFRESH_TOKEN_INVALID', '로그인이 만료되었습니다.');
        }

        $tokens = $this->issueTokens();
        $this->authRepository->rotateSession(
            (int) $session['id'],
            self::hashToken($tokens['accessToken']),
            self::hashToken($tokens['refreshToken']),
            $tokens['accessExpiresAtDatabase'],
            $tokens['refreshExpiresAtDatabase'],
            gmdate('Y-m-d H:i:s'),
        );

        return [
            'accessToken' => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
            'accessTokenExpiresAt' => $tokens['accessTokenExpiresAt'],
        ];
    }

    public function logout(int $userId, string $refreshToken, string $deviceId): void
    {
        $this->authRepository->revokeSession(
            $userId,
            self::hashToken($refreshToken),
            $deviceId,
            gmdate('Y-m-d H:i:s'),
        );
    }

    /** @return array<string, mixed> */
    public function requireUser(?string $authorization): array
    {
        if (!is_string($authorization) || preg_match('/^Bearer\s+([^\s]+)$/i', trim($authorization), $matches) !== 1) {
            throw new UnauthorizedException('UNAUTHENTICATED', '로그인이 필요합니다.');
        }

        $user = $this->authRepository->findActiveUserByAccessTokenHash(
            self::hashToken($matches[1]),
            gmdate('Y-m-d H:i:s'),
        );

        if ($user === null) {
            throw new UnauthorizedException('UNAUTHENTICATED', '로그인이 필요합니다.');
        }

        return $user;
    }

    /** @return array<string, mixed>|null */
    public function optionalUser(?string $authorization): ?array
    {
        if ($authorization === null || trim($authorization) === '') {
            return null;
        }

        return $this->requireUser($authorization);
    }

    /** @return array<string, string> */
    private function issueTokens(): array
    {
        if ($this->accessTtlSeconds < 1 || $this->refreshTtlSeconds < 1) {
            throw new ServiceUnavailableException(
                'AUTH_POLICY_NOT_CONFIGURED',
                '인증 만료 정책이 설정되지 않았습니다.',
            );
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $accessExpiresAt = $now->modify('+' . $this->accessTtlSeconds . ' seconds');
        $refreshExpiresAt = $now->modify('+' . $this->refreshTtlSeconds . ' seconds');

        return [
            'accessToken' => bin2hex(random_bytes(32)),
            'refreshToken' => bin2hex(random_bytes(48)),
            'accessTokenExpiresAt' => $accessExpiresAt->format('Y-m-d\TH:i:s\Z'),
            'accessExpiresAtDatabase' => $accessExpiresAt->format('Y-m-d H:i:s'),
            'refreshExpiresAtDatabase' => $refreshExpiresAt->format('Y-m-d H:i:s'),
        ];
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @param array<string, mixed> $user */
    private static function mapLoginUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'] === null ? null : (string) $user['name'],
            'nickname' => $user['nickname'] === null ? null : (string) $user['nickname'],
            'avatarUrl' => null,
        ];
    }
}
