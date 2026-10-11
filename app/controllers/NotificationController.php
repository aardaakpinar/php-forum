<?php
declare(strict_types=1);

/**
 * NotificationController
 * Kullanıcının bildirimlerini listeler, tümünü okundu işaretleme işlemini yönetir.
 */
final class NotificationController extends Controller
{
    public function index(): array
    {
        $user = $this->requireLogin();

        if ($this->isPost() && $this->inputAction() === 'mark_all_read') {
            $this->verifyCsrf();
            Notification::markAllRead($user);
            $this->redirect(url('notifications'));
        }

        return [
            'user'  => $user,
            'notes' => Notification::fetchForUser($user, 100),
        ];
    }
}
