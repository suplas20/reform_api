<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class MediaRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        string $id,
        int $userId,
        string $purpose,
        string $originalName,
        string $contentType,
        int $expectedSize,
        string $storagePath,
        string $uploadTokenHash,
        string $expiresAt,
        string $now,
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO media_uploads
                    (id, user_id, purpose, original_name, content_type, expected_size,
                     storage_path, upload_token_hash, status, expires_at, created_at, updated_at)
                VALUES
                    (:id, :user_id, :purpose, :original_name, :content_type, :expected_size,
                     :storage_path, :upload_token_hash, :status, :expires_at, :created_at, :updated_at)
                SQL,
        );
        $statement->execute([
            'id' => $id,
            'user_id' => $userId,
            'purpose' => $purpose,
            'original_name' => $originalName,
            'content_type' => $contentType,
            'expected_size' => $expectedSize,
            'storage_path' => $storagePath,
            'upload_token_hash' => $uploadTokenHash,
            'status' => 'PENDING',
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findPendingUpload(string $id, string $tokenHash, string $now): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT * FROM media_uploads
                WHERE id = :id
                  AND upload_token_hash = :upload_token_hash
                  AND status = :status
                  AND expires_at > :now
                LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $id, 'upload_token_hash' => $tokenHash, 'status' => 'PENDING', 'now' => $now]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findOwned(int $userId, string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM media_uploads WHERE id = :id AND user_id = :user_id LIMIT 1',
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function markUploaded(string $id, string $now): void
    {
        $statement = $this->connection->prepare(
            'UPDATE media_uploads SET status = :status, updated_at = :updated_at WHERE id = :id AND status = :pending_status',
        );
        $statement->execute(['id' => $id, 'status' => 'UPLOADED', 'updated_at' => $now, 'pending_status' => 'PENDING']);
    }

    public function markCompleted(int $userId, string $id, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE media_uploads
                SET status = :status, completed_at = :completed_at, updated_at = :updated_at
                WHERE id = :id AND user_id = :user_id AND status = :uploaded_status
                SQL,
        );
        $statement->execute([
            'id' => $id,
            'user_id' => $userId,
            'status' => 'COMPLETED',
            'completed_at' => $now,
            'updated_at' => $now,
            'uploaded_status' => 'UPLOADED',
        ]);
    }
}
