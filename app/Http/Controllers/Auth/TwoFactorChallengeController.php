<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second step of signing in for accounts with two-factor on.
 * The password was already checked, but the user is NOT logged in until the code is right.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor';

    /** Minutes the user has to enter the code after the password. */
    public const WINDOW_MINUTES = 10;

    /** Wrong codes allowed before the user must start again from the password. */
    private const MAX_ATTEMPTS = 5;

    public function __construct(private TwoFactor $twoFactor) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return $this->startOver($request, 'Please log in again.');
        }

        return view('auth.two-factor-challenge', ['recovery' => $request->boolean('recovery')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return $this->startOver($request, 'Your sign-in expired. Please log in again.');
        }

        $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);

        $usingRecovery = $request->filled('recovery_code');
        $field = $usingRecovery ? 'recovery_code' : 'code';
        $key = 'two-factor:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return $this->startOver($request, 'Too many wrong codes. Please log in again.');
        }

        $ok = $usingRecovery
            ? $this->twoFactor->useRecoveryCode($user, $request->input('recovery_code'))
            : $this->twoFactor->verify($user, $request->input('code'));

        if (! $ok) {
            RateLimiter::hit($key, 60);

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                return $this->startOver($request, 'Too many wrong codes. Please log in again.');
            }

            throw ValidationException::withMessages([
                $field => $usingRecovery ? 'That recovery code is not valid, or it was already used.' : 'That code is not correct. Try the newest code in your app.',
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) ($request->session()->get(self::SESSION_KEY)['remember'] ?? false);
        $request->session()->forget(self::SESSION_KEY);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if ($usingRecovery) {
            $left = $this->twoFactor->recoveryCodesLeft($user);
            Activity::log('user.two_factor_recovery_used', $user->name.' signed in with a recovery code ('.$left.' left).', $user);

            return redirect()->intended(route('dashboard', absolute: false))->with('toast', $left > 2
                ? "Recovery code used. {$left} left."
                : "Recovery code used. Only {$left} left: make new ones in Settings.");
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** The user who passed the password step in this session, or null when it is missing, old or no longer valid. */
    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            return null;
        }

        $user = User::find($pending['id'] ?? null);

        return $user && $user->hasTwoFactorEnabled() && ! $user->isDeactivated() ? $user : null;
    }

    private function startOver(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
