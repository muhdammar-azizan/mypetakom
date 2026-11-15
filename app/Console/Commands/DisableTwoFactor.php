<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DisableTwoFactor extends Command
{
    protected $signature = 'two-factor:disable {login : Email, student ID or staff ID of the account}';

    protected $description = 'Turn off two-factor sign-in for an account (for a locked-out coordinator who has no colleague to reset it)';

    public function handle(TwoFactor $twoFactor): int
    {
        $login = trim($this->argument('login'));

        $user = str_contains($login, '@')
            ? User::where('email', Str::lower($login))->first()
            : User::where('student_id', Str::upper($login))->orWhere('staff_id', Str::upper($login))->first();

        if (! $user) {
            $this->error('No account matches "'.$login.'".');

            return self::FAILURE;
        }

        if (! $user->two_factor_secret) {
            $this->info($user->name.' does not use two-factor sign-in. Nothing to do.');

            return self::SUCCESS;
        }

        $twoFactor->disable($user);
        $this->info('Two-factor sign-in is now off for '.$user->name.' ('.$user->email.').');

        return self::SUCCESS;
    }
}
