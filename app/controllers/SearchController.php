<?php
declare(strict_types=1);

/**
 * SearchController
 * Konu basligi/govdesinde basit anahtar kelime aramasi yapar.
 */
final class SearchController extends Controller
{
    public function search(): array
    {
        $user = $this->currentUser();
        $userId = $user ? (int)$user['id'] : 0;

        $q = trim((string)($_GET['q'] ?? ''));
        $results = [];

        if ($q !== '') {
            $results = Thread::search($q, $userId, 100);
        }

        return [
            'user'    => $user,
            'q'       => $q,
            'results' => $results,
        ];
    }
}
