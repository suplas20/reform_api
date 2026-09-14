<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    private ?PDO $connection = null;

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     database: string,
     *     username: string,
     *     password: string,
     *     charset: string
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->validateConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
        );

        $this->connection = new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ],
        );

        return $this->connection;
    }

    private function validateConfig(): void
    {
        if (
            $this->config['database'] === ''
            || $this->config['username'] === ''
            || preg_match('/^[A-Za-z0-9_]+$/', $this->config['database']) !== 1
        ) {
            throw new RuntimeException('Database configuration is incomplete or invalid.');
        }

        if (
            $this->config['port'] < 1
            || $this->config['port'] > 65535
            || str_contains($this->config['host'], ';')
            || $this->config['charset'] !== 'utf8mb4'
        ) {
            throw new RuntimeException('Database connection settings are invalid.');
        }
    }
}
