<?php
declare(strict_types=1);

/**
 * ProfileController
 * Bir kullanıcının herkese açık profilini (istatistik + son konular + son cevaplar) gösterir.
 */
final class ProfileController extends Controller
{
    private const THREADS_LIMIT = 15;

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

        $uid = (int)$profileUser['id'];
        $isSelf = $viewerId === $uid;
        $prefs = user_prefs_for($uid);

        // Gizlilik: "members only" profiller misafirlere kapalıdır (sahibi ve adminler hariç)
        if ($prefs['profile_visibility'] === 'members' && !$viewer && !$isSelf) {
            $this->forbidden('This profile is only visible to logged-in members.');
        }

        $showReplies = $prefs['show_replies'] || $isSelf || $this->isAdmin($viewer);
        $profileUser['location'] = ($prefs['show_location'] || $isSelf) ? (string)$profileUser['location'] : '';

        $tab = ($_GET['tab'] ?? 'threads') === 'replies' && $showReplies ? 'replies' : 'threads';

        $threads = Thread::paginate([
            'viewer_id' => $viewerId,
            'user_id'   => $uid,
            'sort'      => 'new',
            'per_page'  => self::THREADS_LIMIT,
            'page'      => $tab === 'threads' ? (int)($_GET['page'] ?? 1) : 1,
        ]);

        return [
            'viewer'      => $viewer,
            'profileUser' => $profileUser,
            'threadCount' => Thread::countByUser($uid),
            'postCount'   => Post::countByUser($uid),
            'tab'         => $tab,
            'showReplies' => $showReplies,
            'threads'     => $threads,
            'replies'     => $tab === 'replies' ? Post::recentByUser($uid, 20) : [],
        ];
    }
}
