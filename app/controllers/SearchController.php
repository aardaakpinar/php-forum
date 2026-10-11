<?php
declare(strict_types=1);

/**
 * SearchController
 * Konu başlığı, gövdesi ve cevaplarda anahtar kelime araması yapar (sayfalı).
 */
final class SearchController extends Controller
{
    public function search(): array
    {
        $user = $this->currentUser();
        $userId = $user ? (int)$user['id'] : 0;

        $q = trim((string)($_GET['q'] ?? ''));
        if (str_len_safe($q) > 100) {
            $q = function_exists('mb_substr') ? mb_substr($q, 0, 100, 'UTF-8') : substr($q, 0, 100);
        }

        $result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        if ($q !== '') {
            $result = Thread::paginate([
                'viewer_id' => $userId,
                'query'     => $q,
                'sort'      => 'latest',
                'page'      => (int)($_GET['page'] ?? 1),
                'per_page'  => 15,
            ]);
        }

        return [
            'user'    => $user,
            'q'       => $q,
            'results' => $result['rows'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'pages'   => $result['pages'],
        ];
    }
}
