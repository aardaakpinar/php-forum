<?php
declare(strict_types=1);

/**
 * HomeController
 * Ana sayfa: tum konularin listesini gosterir.
 */
final class HomeController extends Controller
{
    public function index(): array
    {
        $user = $this->currentUser();
        $userId = $user ? (int)$user['id'] : 0;

        return [
            'user'    => $user,
            'threads' => Thread::listAll($userId, 100),
        ];
    }
}
