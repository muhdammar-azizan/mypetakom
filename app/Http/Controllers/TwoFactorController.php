<?php

namespace App\Http\Controllers;

use App\Services\TwoFactor;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Staff turn two-factor sign-in on or off in Settings. Every change needs the account password again. */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    /** Make a secret and show the QR code. Nothing is protected until the user confirms with a code. */
    public function start(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);

        if ($request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('settings.edit')->with('toast', 'Two-factor sign-in is already on.');
        }

        $this->twoFactor->start($request->user());

        return redirect()->route('settings.edit');
    }

    /** First code from the app: turns two-factor on and shows the recovery codes once. */
    public function confirm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $request->validateWithBag('twoFactor', ['code' => ['required', 'string', 'max:20']]);

        if (! $user->two_factor_secret || $user->hasTwoFactorEnabled()) {
            return redirect()->route('settings.edit');
        }

        $codes = $this->twoFactor->confirm($user, $request->input('code'));

        if ($codes === null) {
            return back()->withErrors(['code' => 'That code is not correct. Check the newest code in your app and try again.'], 'twoFactor');
        }

        Activity::log('user.two_factor_enabled', $user->name.' turned on two-factor sign-in.', $user);

        return $this->showCodes($codes, 'Two-factor sign-in is on');
    }

    /** Give up a setup that was not finished. */
    public function cancel(Request $request): RedirectResponse
    {
        if (! $request->user()->hasTwoFactorEnabled()) {
            $this->twoFactor->disable($request->user());
        }

        return redirect()->route('settings.edit');
    }

    /** New recovery codes. The old ones stop working. */
    public function regenerate(Request $request): Response|RedirectResponse
    {
        $this->confirmPassword($request);
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('settings.edit');
        }

        $codes = $this->twoFactor->newRecoveryCodes($user);
        Activity::log('user.two_factor_codes_renewed', $user->name.' made new recovery codes.', $user);

        return $this->showCodes($codes, 'New recovery codes');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            Activity::log('user.two_factor_disabled', $user->name.' turned off two-factor sign-in.', $user);
        }

        $this->twoFactor->disable($user);

        return redirect()->route('settings.edit')->with('toast', 'Two-factor sign-in is off.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);
    }

    /** Rendered straight from this request and never cached: the codes exist nowhere else. */
    private function showCodes(array $codes, string $heading): Response
    {
        return response()->view('settings.recovery-codes', ['codes' => $codes, 'heading' => $heading])
            ->header('Cache-Control', 'no-store, private');
    }
}
