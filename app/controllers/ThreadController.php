<?php
declare(strict_types=1);

/**
 * ThreadController
 * Yeni konu olusturma ve tekil konu sayfasi bu controller'da toplanir.
 */
final class ThreadController extends Controller
{
    /** new_thread.php sayfasi: yeni konu olusturma formu ve islemi. */
    public function create(): array
    {
        $user = $this->requireLogin();
        $errors = [];
        $title = '';
        $body = '';
        $categories = Category::all();

        // Varsayılan kategori: ?c=slug veya ilk kategori
        $categoryId = $categories ? (int)$categories[0]['id'] : 0;
        $preset = Category::findBySlug((string)($_GET['c'] ?? ''));
        if ($preset) {
            $categoryId = (int)$preset['id'];
        }

        if ($this->isPost()) {
            $this->verifyCsrf();

            $title = trim((string)($_POST['title'] ?? ''));
            $body  = trim((string)($_POST['body'] ?? ''));
            $categoryId = (int)($_POST['category_id'] ?? 0);

            if (!Category::find($categoryId)) {
                $errors[] = 'Please choose a category.';
            }

            $maxTitle = (int)site_setting('max_title_length');
            $maxPost  = (int)site_setting('max_post_length');

            if ($title === '' || str_len_safe($title) > $maxTitle) {
                $errors[] = 'The title should be between 1 and ' . $maxTitle . ' characters.';
            }

            if ($body === '' || str_len_safe($body) > $maxPost) {
                $errors[] = 'The content should be between 1 and ' . $maxPost . ' characters.';
            }

            if (!$errors && ($wait = $this->cooldownRemaining($user)) > 0) {
                $errors[] = 'Please wait ' . $wait . ' more second' . ($wait === 1 ? '' : 's') . ' before posting again.';
            }

            if (!$errors) {
                $threadId = Thread::create(
                    (int)$user['id'],
                    $title,
                    $body,
                    $categoryId
                );

                if (user_prefs_for((int)$user['id'])['auto_follow']) {
                    ThreadFollow::follow((int)$user['id'], $threadId);
                }

                // Konu govdesinde bahsedilen kullanicilara bildirim gonder
                $mentioned = extract_mentioned_user_ids($body);

                foreach ($mentioned as $uid) {
                    if ((int)$uid === (int)$user['id']) {
                        continue;
                    }

                    Notification::create(
                        (int)$uid,
                        'mention',
                        (int)$user['id'],
                        $threadId,
                        null
                    );
                }

                $this->redirect(url('thread/' . $threadId));
            }
        }

        return [
            'errors'     => $errors,
            'title'      => $title,
            'body'       => $body,
            'categories' => $categories,
            'categoryId' => $categoryId,
        ];
    }

    /** thread.php sayfasi: konu goruntuleme + tum POST islemleri. */
    public function show(): array
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$id) {
            $this->notFound('No thread found.');
        }

        $thread = Thread::find($id);

        if (!$thread) {
            $this->notFound('No thread found.');
        }

        // ?post=ID permalinki: doğru sayfaya ve çapaya yönlendir
        if (!$this->isPost() && isset($_GET['post'])) {
            $pid = (int)$_GET['post'];
            $p = $pid ? Post::find($pid) : null;
            if ($p && (int)$p['thread_id'] === $id) {
                $this->redirect($this->threadUrl($id, Post::pageOf($id, $pid), 'post-' . $pid));
            }
        }

        $errors = [];
        $user = $this->currentUser();

        // Bir duzenleme denemesi hata verirse, ilgili duzenleme formunun
        // acik kalmasi ve girilen degerleri kaybetmemesi icin kullanilir.
        $editPostError = null;
        $editingThread = false;

        if ($this->isPost()) {
            $user = $this->requireLogin();
            $this->verifyCsrf();

            $action = $this->inputAction();

            switch ($action) {
                case 'delete_thread':
                    $this->handleDeleteThread($user, $thread, $id);
                    break;

                case 'delete_post':
                    $this->handleDeletePost($user, $id);
                    break;

                case 'edit_thread':
                    $this->handleEditThread(
                        $user,
                        $thread,
                        $id,
                        $errors,
                        $editingThread
                    );
                    break;

                case 'edit_post':
                    $this->handleEditPost(
                        $user,
                        $id,
                        $errors,
                        $editPostError
                    );
                    break;

                case 'lock':
                    $this->handleLock($user, $thread, $id);
                    break;

                case 'move':
                    $this->handleMove($user, $id);
                    break;

                case 'like':
                    $this->handleLike($user, $id);
                    break;

                case 'pin':
                    $this->handlePin($user, $thread, $id);
                    break;

                case 'follow':
                    // Herhangi bir giris yapmis kullanici, sahibi olmasa da,
                    // istedigi konuyu takip edip cevap bildirimi alabilir.
                    ThreadFollow::follow((int)$user['id'], $id);

                    $this->redirect(url('thread/' . $id));
                    break;

                case 'unfollow':
                    ThreadFollow::unfollow((int)$user['id'], $id);

                    $this->redirect(url('thread/' . $id));
                    break;

                case 'reply':
                default:
                    $this->handleReply($user, $thread, $id, $errors);
                    break;
            }
        }

        // Konu bilgisi (kategori vb.) işlemlerden sonra değişmiş olabilir
        $thread = Thread::find($id) ?? $thread;

        $paged = Post::paginateByThread($id, (int)($_GET['page'] ?? 1));
        $posts = $paged['rows'];

        // Görüntülenme sayacı: yalnızca GET, oturum başına konu başına bir kez
        if (!$this->isPost() && site_setting('count_views')) {
            $seen = $_SESSION['viewed_threads'] ?? [];
            if (!in_array($id, $seen, true)) {
                Thread::incrementViews($id);
                $thread['views'] = (int)$thread['views'] + 1;
                $seen[] = $id;
                $_SESSION['viewed_threads'] = array_slice($seen, -200);
            }
        }

        // Beğeniler
        $postIds = array_map(static fn($p) => (int)$p['id'], $posts);
        $likeCounts = [
            'thread' => Like::counts('thread', [$id])[$id] ?? 0,
            'post'   => Like::counts('post', $postIds),
        ];
        $liked = ['thread' => false, 'post' => []];
        if ($user) {
            $liked['thread'] = isset(Like::likedBy((int)$user['id'], 'thread', [$id])[$id]);
            $liked['post'] = Like::likedBy((int)$user['id'], 'post', $postIds);
        }

        // Kullanici bu konuyu su an goruntuluyor -> "okundu" olarak isaretle,
        // ve takip durumunu (buton icin) hesapla.
        $isFollowing = false;

        if ($user) {
            ThreadRead::markRead((int)$user['id'], $id);
            $isFollowing = ThreadFollow::isFollowing(
                (int)$user['id'],
                $id
            );
        }

        return [
            'id'            => $id,
            'thread'        => $thread,
            'posts'         => $posts,
            'user'          => $user,
            'errors'        => $errors,
            'editPostError' => $editPostError,
            'editingThread' => $editingThread,
            'isFollowing'   => $isFollowing,
            'page'          => $paged['page'],
            'pages'         => $paged['pages'],
            'offset'        => $paged['offset'],
            'totalReplies'  => $paged['total'],
            'likeCounts'    => $likeCounts,
            'liked'         => $liked,
            'categories'    => Category::all(),
        ];
    }

    private function handleDeleteThread(
        array $user,
        array $thread,
        int $id
    ): void {
        if (!$this->ownsOrAdmin($user, (int)$thread['user_id'])) {
            $this->forbidden();
        }

        Thread::delete($id);

        $this->redirect(url());
    }

    private function handleDeletePost(
        array $user,
        int $threadId
    ): void {
        $postId = (int)($_POST['post_id'] ?? 0);

        if (!$postId) {
            $this->badRequest('Invalid post.');
        }

        $post = Post::find($postId);

        if (!$post) {
            $this->notFound('Post not found.');
        }

        if (!$this->ownsOrAdmin($user, (int)$post['user_id'])) {
            $this->forbidden();
        }

        Post::delete($postId);

        // Sayfa artık yoksa (son mesaj silindiyse) bir önceki sayfaya düş
        $pages = max(1, (int)ceil(Thread::replyCount($threadId) / Post::perPage()));
        $this->redirect($this->threadUrl($threadId, min($this->currentPage(), $pages)));
    }

    private function handleEditThread(
        array $user,
        array $thread,
        int $id,
        array &$errors,
        bool &$editingThread
    ): void {
        if (!$this->ownsOrAdmin($user, (int)$thread['user_id'])) {
            $this->forbidden();
        }

        if (!can_edit_content($user, (int)$thread['user_id'], (int)$thread['created_at'])) {
            $this->forbidden('The edit window for this thread has closed.');
        }

        $newTitle = trim((string)($_POST['title'] ?? ''));
        $newBody  = trim((string)($_POST['body'] ?? ''));
        $maxTitle = (int)site_setting('max_title_length');
        $maxPost  = (int)site_setting('max_post_length');

        if ($newTitle === '' || str_len_safe($newTitle) > $maxTitle) {
            $errors[] = 'The title should be between 1 and ' . $maxTitle . ' characters.';
            $editingThread = true;
            return;
        }

        if ($newBody === '' || str_len_safe($newBody) > $maxPost) {
            $errors[] = 'The content should be between 1 and ' . $maxPost . ' characters.';
            $editingThread = true;
            return;
        }

        Thread::update($id, $newTitle, $newBody);

        $this->redirect(url('thread/' . $id));
    }

    private function handleEditPost(
        array $user,
        int $threadId,
        array &$errors,
        ?int &$editPostError
    ): void {
        $postId = (int)($_POST['post_id'] ?? 0);
        $newBody = trim((string)($_POST['body'] ?? ''));

        if (!$postId) {
            $this->badRequest('Invalid post.');
        }

        $post = Post::find($postId);

        if (!$post) {
            $this->notFound('Post not found.');
        }

        if (!$this->ownsOrAdmin($user, (int)$post['user_id'])) {
            $this->forbidden();
        }

        if (!can_edit_content($user, (int)$post['user_id'], (int)$post['created_at'])) {
            $this->forbidden('The edit window for this reply has closed.');
        }

        $maxPost = (int)site_setting('max_post_length');
        if ($newBody === '' || str_len_safe($newBody) > $maxPost) {
            $errors[] = 'Reply must be between 1 and ' . $maxPost . ' characters.';
            $editPostError = $postId;
            return;
        }

        Post::update($postId, $newBody);

        $this->redirect($this->threadUrl($threadId, $this->currentPage(), 'post-' . $postId));
    }

    private function handleLock(
        array $user,
        array $thread,
        int $id
    ): void {
        if (!$this->isAdmin($user)) {
            $this->forbidden();
        }

        Thread::setLocked(
            $id,
            (int)$thread['locked'] !== 1
        );

        $this->redirect(url('thread/' . $id));
    }

    private function handlePin(
        array $user,
        array $thread,
        int $id
    ): void {
        if (!$this->isAdmin($user)) {
            $this->forbidden();
        }

        Thread::setPinned(
            $id,
            (int)$thread['pinned'] !== 1
        );

        $this->redirect(url('thread/' . $id));
    }

    private function handleReply(
        array $user,
        array $thread,
        int $id,
        array &$errors
    ): void {
        if (
            (int)$thread['locked'] === 1 &&
            !$this->isAdmin($user)
        ) {
            $this->forbidden('This thread is locked.');
        }

        $body = trim((string)($_POST['body'] ?? ''));

        $maxPost = (int)site_setting('max_post_length');
        if ($body === '' || str_len_safe($body) > $maxPost) {
            $errors[] = 'Reply must be between 1 and ' . $maxPost . ' characters.';
            return;
        }

        if (($wait = $this->cooldownRemaining($user)) > 0) {
            $errors[] = 'Please wait ' . $wait . ' more second' . ($wait === 1 ? '' : 's') . ' before posting again.';
            return;
        }

        $postId = Post::create(
            $id,
            (int)$user['id'],
            $body
        );

        if (user_prefs_for((int)$user['id'])['auto_follow']) {
            ThreadFollow::follow((int)$user['id'], $id);
        }

        // Bu yanitta bahsedilen kullanicilara bildirim gonder
        // (takipten bagimsiz, her zaman calisir)
        $mentioned = extract_mentioned_user_ids($body);

        foreach ($mentioned as $uid) {
            if ((int)$uid === (int)$user['id']) {
                continue;
            }

            Notification::create(
                (int)$uid,
                'mention',
                (int)$user['id'],
                $id,
                $postId
            );
        }

        // Sadece bu konuyu ACIKCA takip eden kullanicilara bildirim gonder.
        // Mention alanlar zaten yukarida bildirim aldi, tekrar bildirilmiyor.
        $followers = ThreadFollow::followerIds(
            $id,
            (int)$user['id']
        );

        foreach ($followers as $uid) {
            if (in_array($uid, $mentioned, true)) {
                continue;
            }

            Notification::create(
                (int)$uid,
                'reply',
                (int)$user['id'],
                $id,
                $postId
            );
        }

        $pages = max(1, (int)ceil(Thread::replyCount($id) / Post::perPage()));
        $this->redirect($this->threadUrl($id, $pages, 'post-' . $postId));
    }

    private function handleMove(array $user, int $id): void
    {
        if (!$this->isAdmin($user)) {
            $this->forbidden();
        }

        $categoryId = (int)($_POST['category_id'] ?? 0);
        if (!Category::find($categoryId)) {
            $this->badRequest('Invalid category.');
        }

        Thread::setCategory($id, $categoryId);
        $this->redirect(url('thread/' . $id));
    }

    private function handleLike(array $user, int $threadId): void
    {
        if (!site_setting('allow_likes')) {
            $this->forbidden('Likes are disabled on this forum.');
        }

        $postId = (int)($_POST['post_id'] ?? 0);

        if ($postId) {
            $post = Post::find($postId);
            if (!$post || (int)$post['thread_id'] !== $threadId) {
                $this->notFound('Post not found.');
            }
            // Kendi mesajını beğenemez
            if ((int)$post['user_id'] !== (int)$user['id']
                && Like::toggle((int)$user['id'], 'post', $postId)) {
                Notification::create((int)$post['user_id'], 'like', (int)$user['id'], $threadId, $postId);
            }
            $this->redirect($this->threadUrl($threadId, $this->currentPage(), 'post-' . $postId));
        }

        $thread = Thread::find($threadId);
        if ($thread && (int)$thread['user_id'] !== (int)$user['id']
            && Like::toggle((int)$user['id'], 'thread', $threadId)) {
            Notification::create((int)$thread['user_id'], 'like', (int)$user['id'], $threadId, null);
        }
        $this->redirect($this->threadUrl($threadId, $this->currentPage(), 'thread-body'));
    }

    /** Flood kontrolü: bir sonraki gönderiye kalan saniye (0 = serbest). Adminler muaf. */
    private function cooldownRemaining(array $user): int
    {
        $cooldown = (int)site_setting('post_cooldown_seconds');
        if ($cooldown <= 0 || $this->isAdmin($user)) {
            return 0;
        }
        return max(0, User::lastPostAt((int)$user['id']) + $cooldown - time());
    }

    /** İstekteki ?page= değeri (formlar aynı URL'ye POST eder). */
    private function currentPage(): int
    {
        return max(1, (int)($_GET['page'] ?? 1));
    }

    private function threadUrl(int $id, int $page = 1, string $anchor = ''): string
    {
        return url('thread/' . $id)
            . ($page > 1 ? '?page=' . $page : '')
            . ($anchor !== '' ? '#' . $anchor : '');
    }
}
