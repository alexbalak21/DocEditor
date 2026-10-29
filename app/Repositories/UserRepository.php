<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\User;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $this->db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
                 ->execute([$name, $email, $passwordHash]);
        return (int) $this->db->lastInsertId();
    }

    /** Is this email used by someone other than $exceptId? */
    public function emailTaken(string $email, int $exceptId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $exceptId]);
        return (bool) $stmt->fetchColumn();
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                 ->execute([$name, $email, $id]);
    }

    /** Set a new password hash and bump auth_version so every OTHER session is logged out. */
    public function updatePassword(int $id, string $hash): void
    {
        $this->db->prepare('UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?')
                 ->execute([$hash, $id]);
    }

    /** Invalidate every session of this user (they all carry the old auth_version). Returns the new version. */
    public function bumpAuthVersion(int $id): int
    {
        $this->db->prepare('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?')->execute([$id]);
        $stmt = $this->db->prepare('SELECT auth_version FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /** Transparent re-hash (password_needs_rehash) — does NOT end other sessions. */
    public function rehashPassword(int $id, string $hash): void
    {
        $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
