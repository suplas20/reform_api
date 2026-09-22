<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AuthRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveUserByAccessTokenHash(string $tokenHash, string $now): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT users.id, users.name, users.nickname, users.email, users.phone,
                    users.tagline, users.profile_image, users.created_at
                FROM auth_sessions
                INNER JOIN users ON users.id = auth_sessions.user_id
                WHERE auth_sessions.access_token_hash = :token_hash
                  AND auth_sessions.access_expires_at > :now
                  AND auth_sessions.revoked_at IS NULL
                  AND users.status = :user_status
                  AND users.deleted_at IS NULL
                LIMIT 1
                SQL,
        );
        $statement->execute(['token_hash' => $tokenHash, 'now' => $now, 'user_status' => 'ACTIVE']);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findRefreshSession(string $tokenHash, string $deviceId, string $now): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT auth_sessions.id, auth_sessions.user_id
                FROM auth_sessions
                INNER JOIN users ON users.id = auth_sessions.user_id
                WHERE auth_sessions.refresh_token_hash = :token_hash
                  AND auth_sessions.device_id = :device_id
                  AND auth_sessions.refresh_expires_at > :now
                  AND auth_sessions.revoked_at IS NULL
                  AND users.status = :user_status
                  AND users.deleted_at IS NULL
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'device_id' => $deviceId,
            'now' => $now,
            'user_status' => 'ACTIVE',
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findSocialUser(string $provider, string $providerUserId): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT users.id, users.name, users.nickname, users.profile_image
                FROM user_social_accounts
                INNER JOIN users ON users.id = user_social_accounts.user_id
                WHERE user_social_accounts.provider = :provider
                  AND user_social_accounts.provider_user_id = :provider_user_id
                  AND users.status = :user_status
                  AND users.deleted_at IS NULL
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'user_status' => 'ACTIVE',
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveUserById(int $userId): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, name, nickname, email, phone, tagline, profile_image, created_at
                FROM users
                WHERE id = :id
                  AND status = :status
                  AND deleted_at IS NULL
                LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $userId, 'status' => 'ACTIVE']);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function createUser(?string $name, ?string $email, string $now): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (name, email, status, created_at, updated_at) VALUES (:name, :email, :status, :created_at, :updated_at)',
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function createSocialAccount(
        int $userId,
        string $provider,
        string $providerUserId,
        ?string $email,
        string $now,
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO user_social_accounts
                    (user_id, provider, provider_user_id, provider_email, created_at, updated_at)
                VALUES
                    (:user_id, :provider, :provider_user_id, :provider_email, :created_at, :updated_at)
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_email' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function createSession(
        int $userId,
        string $deviceId,
        string $accessTokenHash,
        string $refreshTokenHash,
        string $accessExpiresAt,
        string $refreshExpiresAt,
        string $now,
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO auth_sessions
                    (user_id, device_id, access_token_hash, refresh_token_hash,
                     access_expires_at, refresh_expires_at, created_at)
                VALUES
                    (:user_id, :device_id, :access_token_hash, :refresh_token_hash,
                     :access_expires_at, :refresh_expires_at, :created_at)
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'access_token_hash' => $accessTokenHash,
            'refresh_token_hash' => $refreshTokenHash,
            'access_expires_at' => $accessExpiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'created_at' => $now,
        ]);
    }

    public function rotateSession(
        int $sessionId,
        string $accessTokenHash,
        string $refreshTokenHash,
        string $accessExpiresAt,
        string $refreshExpiresAt,
        string $now,
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE auth_sessions
                SET access_token_hash = :access_token_hash,
                    refresh_token_hash = :refresh_token_hash,
                    access_expires_at = :access_expires_at,
                    refresh_expires_at = :refresh_expires_at,
                    last_used_at = :last_used_at
                WHERE id = :id
                  AND revoked_at IS NULL
                SQL,
        );
        $statement->execute([
            'id' => $sessionId,
            'access_token_hash' => $accessTokenHash,
            'refresh_token_hash' => $refreshTokenHash,
            'access_expires_at' => $accessExpiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'last_used_at' => $now,
        ]);
    }

    public function revokeSession(int $userId, string $refreshTokenHash, string $deviceId, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE auth_sessions
                SET revoked_at = :revoked_at
                WHERE user_id = :user_id
                  AND refresh_token_hash = :refresh_token_hash
                  AND device_id = :device_id
                  AND revoked_at IS NULL
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'refresh_token_hash' => $refreshTokenHash,
            'device_id' => $deviceId,
            'revoked_at' => $now,
        ]);
    }
}
