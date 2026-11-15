<?php

namespace App\Providers;

use App\Support\MailText;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Reset your MyPetakom password')
                ->greeting('Hello '.MailText::escape($notifiable->name).',')
                ->line('We received a request to reset the password of your MyPetakom account.')
                ->action('Choose a new password', $url)
                ->line("This link works for {$minutes} minutes.")
                ->line('If you did not ask for this, you can ignore this e-mail. Your password stays the same.');
        });

        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verify your MyPetakom e-mail')
                ->greeting('Hello '.MailText::escape($notifiable->name).',')
                ->line('Please confirm that this e-mail address is yours, so password reset links and notices reach you.')
                ->action('Verify e-mail address', $url)
                ->line('If you did not create or change a MyPetakom account with this address, you can ignore this e-mail.');
        });

        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });
    }
}
