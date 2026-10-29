<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Remembered devices (table auth_devices). All time arithmetic uses the DATABASE clock (NOW()),
 * so PHP and MySQL time zones can never disagree about when a token expires.
 */
class AuthDeviceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $d, int $lifetimeSeconds, int $hardSeconds): int
    {
        $this->db->prepare('
            INSERT INTO auth_devices
              (user_id, selector, token_hash, auth_version, browser, os, user_agent, ip_created, ip_last, expires_at, hard_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND),
                    IF(? > 0, DATE_ADD(NOW(), INTERVAL ? SECOND), NULL))
        ')->execute([
            $d['user_id'], $d['selector'], $d['token_hash'], $d['auth_version'],
            $d['browser'], $d['os'], $d['user_agent'], $d['ip'], $d['ip'],
            $lifetimeSeconds, $hardSeconds, $hardSeconds,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Row + freshness flags computed by the database clock. */
    public function findBySelector(string $selector): ?array
    {
        $stmt = $this->db->prepare('
            SELECT d.*,
                   (d.expires_at > NOW())                                        AS alive,
                   (d.hard_expires_at IS NULL OR d.hard_expires_at > NOW())      AS hard_ok,
                   (d.prev_hash IS NOT NULL AND d.prev_valid_until > NOW())      AS prev_ok,
                   TIMESTAMPDIFF(SECOND, COALESCE(d.rotated_at, d.created_at), NOW()) AS age
            FROM auth_devices d WHERE d.selector = ? LIMIT 1
        ');
        $stmt->execute([$selector]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Replace the validator. Succeeds only if nobody rotated it in the meantime (compare-and-swap),
     * so two parallel requests carrying the same cookie cannot both mint a new one.
     */
    public function rotate(int $id, string $oldHash, string $newHash, string $ip, int $lifetimeSeconds, int $graceSeconds): bool
    {
        $stmt = $this->db->prepare('
            UPDATE auth_devices
            SET prev_hash = ?, prev_valid_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                token_hash = ?, ip_last = ?, last_used_at = NOW(), rotated_at = NOW(),
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ? AND token_hash = ?
        ');
        $stmt->execute([$oldHash, $graceSeconds, $newHash, $ip, $lifetimeSeconds, $id, $oldHash]);
        return $stmt->rowCount() === 1;
    }

    public function touch(int $id, string $ip): void
    {
        $this->db->prepare('UPDATE auth_devices SET ip_last = ?, last_used_at = NOW() WHERE id = ?')->execute([$ip, $id]);
    }

    /** Is this device still valid (exists, not expired, belongs to the user)? */
    public function isActive(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1 FROM auth_devices
            WHERE id = ? AND user_id = ? AND expires_at > NOW() AND (hard_expires_at IS NULL OR hard_expires_at > NOW())
        ');
        $stmt->execute([$id, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT id, browser, os, ip_created, ip_last, created_at, last_used_at, expires_at
            FROM auth_devices WHERE user_id = ? AND expires_at > NOW() ORDER BY last_used_at DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM auth_devices WHERE id = ?')->execute([$id]);
    }

    public function deleteForUser(int $id, int $userId): void
    {
        $this->db->prepare('DELETE FROM auth_devices WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    public function deleteBySelector(string $selector): void
    {
        $this->db->prepare('DELETE FROM auth_devices WHERE selector = ?')->execute([$selector]);
    }

    /** Remove every device of the user except one (0 = remove all). */
    public function deleteOthers(int $userId, int $keepId): void
    {
        $this->db->prepare('DELETE FROM auth_devices WHERE user_id = ? AND id <> ?')->execute([$userId, $keepId]);
    }

    public function setAuthVersion(int $id, int $version): void
    {
        $this->db->prepare('UPDATE auth_devices SET auth_version = ? WHERE id = ?')->execute([$version, $id]);
    }

    public function deleteExpired(): void
    {
        $this->db->exec('DELETE FROM auth_devices WHERE expires_at < NOW() OR (hard_expires_at IS NOT NULL AND hard_expires_at < NOW())');
    }

    /** Keep at most $max devices per user (the least recently used ones go first). */
    public function trimToLimit(int $userId, int $max): void
    {
        $stmt = $this->db->prepare('SELECT id FROM auth_devices WHERE user_id = ? ORDER BY last_used_at DESC');
        $stmt->execute([$userId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach (array_slice($ids, $max) as $id) $this->delete((int) $id);
    }
}
