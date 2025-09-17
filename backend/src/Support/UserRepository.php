<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use PDO;
use PDOException;

class UserRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureTableExists();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $result = $statement->fetch();

        return $result === false ? null : $result;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE google_id = :google_id LIMIT 1');
        $statement->execute(['google_id' => $googleId]);

        $result = $statement->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array{ google_id: string, email: string, name?: string|null, avatar_url?: string|null } $user
     */
    public function upsertGoogleUser(array $user): array
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO users (google_id, email, name, avatar_url, created_at, updated_at)
            VALUES (:google_id, :email, :name, :avatar_url, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                name = VALUES(name),
                avatar_url = VALUES(avatar_url),
                updated_at = VALUES(updated_at)
            SQL
        );

        $statement->execute([
            'google_id' => $user['google_id'],
            'email' => $user['email'],
            'name' => $user['name'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $existing = $this->findByGoogleId($user['google_id']);
        if ($existing === null) {
            throw new PDOException('Failed to persist Google user');
        }

        return $existing;
    }

    private function ensureTableExists(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                google_id VARCHAR(64) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NULL,
                avatar_url VARCHAR(512) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_google_id (google_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
    }
}
