<?php
declare(strict_types=1);

/**
 * NotificationController
 * Kullanicinin bildirimlerini listeler, tumunu okundu isaretleme islemini yonetir.
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
            'notes' => Notification::fetchForUser($user, 200),
        ];
    }
}
