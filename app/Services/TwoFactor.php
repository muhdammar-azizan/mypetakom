<?php

namespace App\Services;

use App\Models\User;
use App\Support\Qr;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor sign-in with an authenticator app (time-based one-time codes, RFC 6238),
 * plus single-use recovery codes for when the phone is lost.
 */
class TwoFactor
{
    public const ISSUER = 'MyPetakom';

    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google) {}

    /** A new secret. It does nothing until the user confirms it with a code from their app. */
    public function start(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->google->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    /** The otpauth:// link inside the QR code that authenticator apps scan. */
    public function otpauthUrl(User $user): string
    {
        return $this->google->getQRCodeUrl(self::ISSUER, $user->email, $user->two_factor_secret);
    }

    public function qrSvg(User $user): string
    {
        return Qr::svg($this->otpauthUrl($user), 220);
    }

    /** The secret in groups of four, for people who type it into the app instead of scanning. */
    public function readableKey(User $user): string
    {
        return trim(chunk_split($user->two_factor_secret, 4, ' '));
    }

    /**
     * Check a 6-digit code from the app. A code that was already accepted once is refused,
     * so someone who saw it over a shoulder cannot use it again.
     */
    public function verify(User $user, ?string $code): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);

        if (! $user->two_factor_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // The last used step goes in as 0 (not null): with null the library only answers true and we could not remember the step.
        $step = $this->google->verifyKeyNewer($user->two_factor_secret, $code, $user->two_factor_last_used_step ?? 0, 1);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_step' => $step])->save();

        return true;
    }

    /**
     * Turn two-factor on after the first code is right.
     *
     * @return list<string>|null the recovery codes to show once, or null when the code was wrong
     */
    public function confirm(User $user, ?string $code): ?array
    {
        if ($user->two_factor_confirmed_at || ! $this->verify($user, $code)) {
            return null;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->newRecoveryCodes($user);
    }

    /**
     * Replace all recovery codes. Only the hashes are stored: the plain codes exist in the response and nowhere else.
     *
     * @return list<string>
     */
    public function newRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5)))
            ->all();

        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => $this->hash($c), $codes)])->save();

        return $codes;
    }

    /** Use one recovery code. It stops working straight away. */
    public function useRecoveryCode(User $user, ?string $input): bool
    {
        $hash = $this->hash((string) $input);
        $remaining = $user->two_factor_recovery_codes ?? [];
        $found = null;

        // Look at every code, so the time taken does not show which one matched.
        foreach ($remaining as $index => $stored) {
            if (hash_equals($stored, $hash)) {
                $found = $index;
            }
        }

        if ($found === null) {
            return false;
        }

        unset($remaining[$found]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();

        return true;
    }

    public function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /** Turn two-factor off completely. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    /** "ABCDE-fghij", "abcdefghij" and " abcde-fghij " are the same code. */
    private function hash(string $code): string
    {
        $normal = str_replace(['-', ' '], '', Str::lower($code));

        return hash_hmac('sha256', $normal, (string) config('app.key'));
    }
}
