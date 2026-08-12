<?php
declare(strict_types=1);

/**
 * ProfileController
 * Bir kullanicinin herkese acik profilini (istatistikler + son konulari) gosterir.
 */
final class ProfileController extends Controller
{
    private const THREADS_LIMIT = 30;

    public function show(): array
    {
        $viewer = $this->currentUser();
        $viewerId = $viewer ? (int)$viewer['id'] : 0;

        $username = trim((string)($_GET['u'] ?? ''));
        if ($username === '') {
            $this->notFound('User not found.');
        }

        $profileUser = User::findPublicByUsername($username);
        if (!$profileUser) {
            $this->notFound('User not found.');
        }

        $threadCount = Thread::countByUser((int)$profileUser['id']);
        $postCount = Post::countByUser((int)$profileUser['id']);
        $recentThreads = Thread::listByUser((int)$profileUser['id'], $viewerId, self::THREADS_LIMIT);

        return [
            'viewer'        => $viewer,
            'profileUser'   => $profileUser,
            'threadCount'   => $threadCount,
            'postCount'     => $postCount,
            'recentThreads' => $recentThreads,
        ];
    }
}
