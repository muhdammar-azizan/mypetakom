<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * One simple notice: a title, a short line and a link.
 * It goes to the bell (in-app) and/or the user's e-mail, depending on their Settings.
 */
class Notice extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /**
     * Send to one user, or to a list of users (null and duplicates are skipped).
     * A user who turned both in-app and e-mail notices off gets nothing.
     * A failure to deliver (for example the mail server is down) is reported but never breaks the action
     * that caused the notice, such as approving a membership.
     */
    public static function send(User|Collection|array|null $to, string $title, string $body, string $url): void
    {
        $users = collect($to instanceof User ? [$to] : $to)->filter()->unique('id');

        foreach ($users as $user) {
            if (! $user->notify_in_app && ! $user->notify_email) {
                continue;
            }

            try {
                $user->notify(new self($title, $body, $url));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->notify_in_app ? 'database' : null,
            $notifiable->notify_email && $notifiable->email ? 'mail' : null,
        ]));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.MailText::escape($notifiable->name).',')
            ->line(MailText::escape($this->body))
            ->action('Open in MyPetakom', $this->url)
            ->line('You are getting this e-mail because e-mail notifications are turned on for your account. You can turn them off in Settings: '.route('settings.edit'));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
