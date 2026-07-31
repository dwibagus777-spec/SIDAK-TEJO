<?php

namespace App\Controllers;

use App\Repositories\NotificationRepository;
use App\Services\NotificationService;
use App\Services\NotificationScheduler;

class Notification extends BaseController
{
    private NotificationRepository $repository;
    private NotificationService $service;
    private NotificationScheduler $scheduler;

    public function __construct()
    {
        $this->repository = new NotificationRepository();
        $this->service    = new NotificationService();
        $this->scheduler  = new NotificationScheduler();
    }

    public function index()
    {
        $userId = session()->get('user_id');
        $notifications = $this->repository->getUserNotifications($userId, 50);
        $unreadCount   = $this->repository->getUnreadCount($userId);

        return view('notifications/index', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'userRole'      => session()->get('user_role'),
        ]);
    }

    public function markAllAsRead()
    {
        $userId = session()->get('user_id');
        $this->repository->markAllAsRead($userId);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true]);
        }
        return redirect()->to(site_url('notifications'))->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }

    public function apiUnread()
    {
        $userId = session()->get('user_id');
        $count = $this->repository->getUnreadCount($userId);
        return $this->response->setJSON(['unread_count' => $count]);
    }

    public function apiUnreadList()
    {
        try {
            $userId = session()->get('user_id');
            $list   = $this->repository->getUserNotifications($userId, 10);
            $count  = $this->repository->getUnreadCount($userId);

            $items = [];
            foreach ($list as $n) {
                $createdTime = strtotime($n['created_at'] ?? 'now');
                $diffMinutes = round((time() - $createdTime) / 60);

                $timeAgo = 'Baru saja';
                if ($diffMinutes >= 1440) {
                    $timeAgo = round($diffMinutes / 1440) . ' hari lalu';
                } elseif ($diffMinutes >= 60) {
                    $timeAgo = round($diffMinutes / 60) . ' jam lalu';
                } elseif ($diffMinutes > 0) {
                    $timeAgo = $diffMinutes . ' menit lalu';
                }

                $items[] = [
                    'id'       => $n['id'] ?? 0,
                    'title'    => $n['title'] ?? 'Notifikasi',
                    'message'  => $n['message'] ?? '',
                    'type'     => $n['type'] ?? 'INFO',
                    'is_read'  => (int)($n['is_read'] ?? 0),
                    'time_ago' => $timeAgo,
                    'target'   => !empty($n['target']) ? $n['target'] : site_url('notifications'),
                ];
            }

            return $this->response->setJSON([
                'status'       => 'success',
                'unread_count' => $count,
                'items'        => $items
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Notification::apiUnreadList] Exception: ' . $e->getMessage());
            return $this->response->setJSON([
                'status'       => 'error',
                'unread_count' => 0,
                'items'        => []
            ]);
        }
    }

    public function templates()
    {
        if (!check_role(['administrator'])) {
            return redirect()->to(site_url('notifications'))->with('error', 'Akses ditolak.');
        }
        return view('notifications/templates', [
            'templates' => $this->repository->getTemplates(),
        ]);
    }

    public function rules()
    {
        if (!check_role(['administrator'])) {
            return redirect()->to(site_url('notifications'))->with('error', 'Akses ditolak.');
        }
        return view('notifications/rules', [
            'rules' => $this->repository->getRules(),
        ]);
    }

    public function preferences()
    {
        $userId = session()->get('user_id') ?: 1;
        return view('notifications/preferences', [
            'prefs' => $this->repository->getUserPreferences($userId),
        ]);
    }

    public function triggerEscalation()
    {
        $res = $this->scheduler->checkSlaEscalations();
        return $this->response->setJSON($res);
    }
}
