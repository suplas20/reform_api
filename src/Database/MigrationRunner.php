<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    private const FILE_PATTERN = '/^\d{3}_[a-z0-9_]+\.sql$/';

    public function __construct(
        private readonly PDO $connection,
        private readonly string $migrationDirectory,
    ) {
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();

        $appliedMigrations = $this->appliedMigrations();
        $newlyApplied = [];

        foreach ($this->migrationFiles() as $path) {
            $version = pathinfo($path, PATHINFO_FILENAME);
            $checksum = hash_file('sha256', $path);

            if (!is_string($checksum)) {
                throw new RuntimeException(sprintf('Unable to checksum migration: %s', basename($path)));
            }

            if (isset($appliedMigrations[$version])) {
                if (!hash_equals($appliedMigrations[$version], $checksum)) {
                    throw new RuntimeException(sprintf(
                        'Applied migration was modified: %s',
                        basename($path),
                    ));
                }

                continue;
            }

            $sql = file_get_contents($path);

            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException(sprintf('Migration is empty or unreadable: %s', basename($path)));
            }

            $this->connection->exec($sql);
            $this->recordMigration($version, $checksum);
            $newlyApplied[] = $version;
        }

        return $newlyApplied;
    }

    /**
     * @return array{applied: list<string>, pending: list<string>}
     */
    public function status(): array
    {
        $this->ensureMigrationTable();

        $appliedMigrations = $this->appliedMigrations();
        $availableVersions = array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            $this->migrationFiles(),
        );

        return [
            'applied' => array_values(array_intersect($availableVersions, array_keys($appliedMigrations))),
            'pending' => array_values(array_diff($availableVersions, array_keys($appliedMigrations))),
        ];
    }

    private function ensureMigrationTable(): void
    {
        $this->connection->exec(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    version VARCHAR(100) NOT NULL,
                    checksum CHAR(64) NOT NULL,
                    applied_at DATETIME NOT NULL,
                    PRIMARY KEY (version)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                SQL,
        );
    }

    /**
     * @return array<string, string>
     */
    private function appliedMigrations(): array
    {
        $statement = $this->connection->query(
            'SELECT version, checksum FROM schema_migrations ORDER BY version',
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to read applied migrations.');
        }

        $migrations = [];

        while ($row = $statement->fetch()) {
            $migrations[(string) $row['version']] = (string) $row['checksum'];
        }

        return $migrations;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationDirectory) || !is_readable($this->migrationDirectory)) {
            throw new RuntimeException('Migration directory is missing or unreadable.');
        }

        $paths = glob($this->migrationDirectory . '/*.sql');

        if ($paths === false) {
            throw new RuntimeException('Unable to list migration files.');
        }

        foreach ($paths as $path) {
            if (preg_match(self::FILE_PATTERN, basename($path)) !== 1) {
                throw new RuntimeException(sprintf(
                    'Invalid migration filename: %s',
                    basename($path),
                ));
            }
        }

        sort($paths, SORT_STRING);

        return array_values($paths);
    }

    private function recordMigration(string $version, string $checksum): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO schema_migrations (version, checksum, applied_at)
                VALUES (:version, :checksum, :applied_at)
                SQL,
        );

        $statement->execute([
            'version' => $version,
            'checksum' => $checksum,
            'applied_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
