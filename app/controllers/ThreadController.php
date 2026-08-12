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

        if ($this->isPost()) {
            $this->verifyCsrf();

            $title = trim((string)($_POST['title'] ?? ''));
            $body  = trim((string)($_POST['body'] ?? ''));

            if ($title === '' || str_len_safe($title) > 150) {
                $errors[] = 'The title should be between 1 and 150 characters.';
            }

            if ($body === '' || str_len_safe($body) > 10000) {
                $errors[] = 'The content should be between 1 and 10000 characters.';
            }

            if (!$errors) {
                $threadId = Thread::create(
                    (int)$user['id'],
                    $title,
                    $body
                );

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
            'errors' => $errors,
            'title'  => $title,
            'body'   => $body,
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

        $posts = Post::listByThread($id);

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

        $this->redirect(url('thread/' . $threadId));
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

        $newTitle = trim((string)($_POST['title'] ?? ''));
        $newBody  = trim((string)($_POST['body'] ?? ''));

        if ($newTitle === '' || str_len_safe($newTitle) > 150) {
            $errors[] = 'The title should be between 1 and 150 characters.';
            $editingThread = true;
            return;
        }

        if ($newBody === '' || str_len_safe($newBody) > 10000) {
            $errors[] = 'The content should be between 1 and 10000 characters.';
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

        if ($newBody === '' || str_len_safe($newBody) > 10000) {
            $errors[] = 'Reply must be between 1 and 10000 characters.';
            $editPostError = $postId;
            return;
        }

        Post::update($postId, $newBody);

        $this->redirect(
            url('thread/' . $threadId) . '#post-' . $postId
        );
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

        if ($body === '' || str_len_safe($body) > 10000) {
            $errors[] = 'Reply must be between 1 and 10000 characters.';
            return;
        }

        $postId = Post::create(
            $id,
            (int)$user['id'],
            $body
        );

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

        $this->redirect(
            url('thread/' . $id) . '#latest'
        );
    }
}
