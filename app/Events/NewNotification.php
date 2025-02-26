<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {
        Log::info('NewNotification event constructed', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id
        ]);
    }

    public function broadcastOn(): array
    {
        Log::info('Broadcasting to channel', [
            'channel' => 'notifications.' . $this->notification->user_id,
            'event' => 'NewNotification',
            'notification' => $this->notification->toArray()
        ]);

        return [
            new PrivateChannel('notifications.' . $this->notification->user_id)
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewNotification';
    }

    public function broadcastWith(): array
    {
        $data = [
            'notification' => [
                'id' => $this->notification->id,
                'title' => $this->notification->title,
                'content' => $this->notification->content,
                'type' => $this->notification->type,
                'data' => $this->notification->data,
                'is_seen' => $this->notification->is_seen,
                'created_at' => $this->notification->created_at->diffForHumans(),
                'reference' => [
                    'id' => $this->notification->reference_id,
                    'type' => $this->notification->reference_type
                ]
            ]
        ];

        Log::info('Broadcasting data', $data);

        return $data;
    }

    // Forțăm procesarea sincronă
    public function shouldQueue(): bool
    {
        return false;
    }
} 