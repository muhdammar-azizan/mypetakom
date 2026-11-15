<?php

namespace App\Support;

use App\Models\AttendanceSlot;

/**
 * Short-lived code that is added to a slot's check-in link (?c=...).
 * It changes every PERIOD seconds and cannot be guessed without the app key,
 * so a QR photo that is sent to someone far from the venue stops working within a minute.
 */
class RotatingCode
{
    public const PERIOD = 30;

    /** Windows (of PERIOD seconds) a freshly scanned link stays valid: the current one and the one before. */
    public const OPEN_GRACE = 1;

    /** After the page is open, the student needs time to fill in the form: about 10 minutes. */
    public const SUBMIT_GRACE = 20;

    public static function current(AttendanceSlot $slot): string
    {
        return self::codeFor($slot, self::window());
    }

    /** True when $code was issued for this slot within the last $graceWindows windows. */
    public static function valid(AttendanceSlot $slot, ?string $code, int $graceWindows = self::OPEN_GRACE): bool
    {
        if (! $code) {
            return false;
        }

        $now = self::window();

        for ($i = 0; $i <= $graceWindows; $i++) {
            if (hash_equals(self::codeFor($slot, $now - $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Seconds until the current code is replaced. */
    public static function secondsLeft(): int
    {
        return self::PERIOD - (now()->timestamp % self::PERIOD);
    }

    private static function window(): int
    {
        return intdiv(now()->timestamp, self::PERIOD);
    }

    private static function codeFor(AttendanceSlot $slot, int $window): string
    {
        return substr(hash_hmac('sha256', $slot->qr_token.'|'.$window, (string) config('app.key')), 0, 12);
    }
}
