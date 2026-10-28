<?php

namespace App\Services;

use App\Models\PortalNotification;
use App\Models\User;
use App\Services\PortalNotificationMailer;

class PortalNotificationService
{
    public function __construct(private readonly PortalNotificationMailer $mailer) {}

    public function send(User $recipient, string $type, string $title, string $message, array $data = []): void
    {
        PortalNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        $this->mailer->send($recipient, $title, $title, $message);
    }

    /** @param list<string> $roles */
    public function sendToRoles(
        array $roles,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?int $barangayId = null,
    ): void
    {
        User::query()
            ->whereIn('role', $roles)
            ->when($barangayId !== null, fn ($query) => $query->where('barangay_id', $barangayId))
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($type, $title, $message, $data): void {
                foreach ($users as $user) {
                    $this->send($user, $type, $title, $message, $data);
                }
            });
    }
}