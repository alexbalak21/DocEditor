<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

class NoteController
{
    private const MAX_IMAGES    = 10;
    private const MAX_IMG_BYTES = 5 * 1024 * 1024; // 5 MB per image

    // ── POST /api/notes — save a note (called via fetch from the panel) ──
    public function store(): void
    {
        $user = Auth::user();
        if (!$user) { Response::error('Unauthorized', 401); return; }

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $url    = trim(substr($body['url']   ?? '', 0, 500));
        $title  = trim(substr($body['title'] ?? '', 0, 255));
        $text   = trim($body['body'] ?? '');
        $images = $body['images'] ?? [];

        if ($text === '' && empty($images)) {
            Response::error('Note is empty.', 422);
            return;
        }

        // Validate images array
        if (!is_array($images)) $images = [];
        $images = array_slice($images, 0, self::MAX_IMAGES);
        foreach ($images as $i => $img) {
            if (!is_string($img) || !str_starts_with($img, 'data:image/')) {
                unset($images[$i]);
                continue;
            }
            if (strlen($img) > self::MAX_IMG_BYTES) {
                unset($images[$i]); // silently drop oversized images
            }
        }
        $images = array_values($images);

        $db = Database::getInstance();
        $db->prepare('
            INSERT INTO notes (user_id, url, title, body, images)
            VALUES (?, ?, ?, ?, ?)
        ')->execute([
            $user->id,
            $url,
            $title,
            $text,
            json_encode($images, JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int) $db->lastInsertId();

        Logger::info('note.created', [
            'uid'    => $user->id,
            'note_id'=> $id,
            'url'    => $url,
            'images' => count($images),
        ]);

        Response::json(['ok' => true, 'id' => $id]);
    }

    // ── GET /notes — admin viewer ──────────────────────────────
    public function index(): void
    {
        $db    = Database::getInstance();
        $notes = $db->query('
            SELECT n.*, u.name AS user_name, u.email AS user_email
            FROM notes n
            LEFT JOIN users u ON u.id = n.user_id
            ORDER BY n.created_at DESC
        ')->fetchAll();

        // Decode images JSON for the view
        foreach ($notes as &$note) {
            $note['images'] = json_decode($note['images'] ?? '[]', true) ?: [];
        }
        unset($note);

        Response::view('notes.index', [
            'title' => 'Feedback Notes',
            'notes' => $notes,
        ]);
    }

    // ── POST /api/notes/:id/delete ────────────────────────────
    public function destroy(int $id): void
    {
        $db = Database::getInstance();
        $db->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
        Response::json(['ok' => true]);
    }
}
