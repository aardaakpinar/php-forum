<?php
declare(strict_types=1);

/**
 * HomeController
 * Ana sayfa: kategori / sıralama / filtre destekli, sayfalı konu listesi.
 */
final class HomeController extends Controller
{
    public function index(): array
    {
        $user = $this->currentUser();
        $userId = $user ? (int)$user['id'] : 0;

        $categories = Category::all();

        $category = null;
        $slug = trim((string)($_GET['c'] ?? ''));
        if ($slug !== '') {
            $category = Category::findBySlug($slug);
            if (!$category) {
                $this->notFound('Category not found.');
            }
        }

        $defaultSort = (string)effective_setting('default_sort');
        if (!in_array($defaultSort, Thread::sortKeys(), true)) {
            $defaultSort = 'latest';
        }
        $sort = (string)($_GET['sort'] ?? $defaultSort);
        if (!in_array($sort, Thread::sortKeys(), true)) {
            $sort = $defaultSort;
        }

        $filter = (string)($_GET['filter'] ?? '');
        if (!in_array($filter, ['following', 'mine', 'unanswered'], true) || (!$user && $filter !== 'unanswered')) {
            $filter = '';
        }

        $result = Thread::paginate([
            'viewer_id'   => $userId,
            'category_id' => $category ? (int)$category['id'] : null,
            'user_id'     => $filter === 'mine' ? $userId : null,
            'follower_id' => $filter === 'following' ? $userId : null,
            'unanswered'  => $filter === 'unanswered',
            'sort'        => $sort,
            'page'        => (int)($_GET['page'] ?? 1),
        ]);

        return [
            'user'       => $user,
            'threads'    => $result['rows'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'pages'      => $result['pages'],
            'category'   => $category,
            'categories' => $categories,
            'sort'       => $sort,
            'defaultSort' => $defaultSort,
            'filter'     => $filter,
            'stats'      => [
                'threads' => Thread::countAll(),
                'posts'   => Post::countAll(),
                'members' => User::countAll(),
                'newest'  => User::newest(),
            ],
        ];
    }
}
