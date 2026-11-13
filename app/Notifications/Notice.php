<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** One simple in-app notice: a title, a short line and a link. */
class Notice extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /** Send to one user, or to a list of users (null and duplicates are skipped). */
    public static function send(User|Collection|array|null $to, string $title, string $body, string $url): void
    {
        $users = collect($to instanceof User ? [$to] : $to)->filter()->unique('id');

        foreach ($users as $user) {
            $user->notify(new self($title, $body, $url));
        }
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
