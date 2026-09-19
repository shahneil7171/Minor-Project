<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Lightweight role-specific in-app alert stored on the "database" channel.
 *
 * Carries a title, a human-readable body and a deep-link URL so any layout
 * can render the bell dropdown / notification list without knowing the
 * domain details of the event that created it.
 *
 * Optional domain context (for example type, order_id, order_number) can be
 * attached through $meta without changing the shape every existing caller
 * already relies on.
 */
class StoreAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public array $meta = [],
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Domain context is merged first so the core rendering keys can never
        // be overridden by whatever a caller passes in.
        return array_merge($this->meta, [
            'title' => $this->title,
            // "message" is an alias of "body" kept so notification views that
            // already read data['message'] keep working unchanged.
            'message' => $this->body,
            'body'  => $this->body,
            'url'   => $this->url,
        ]);
    }
}
