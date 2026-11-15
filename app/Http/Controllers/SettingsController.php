<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('settings.edit', [
            'user' => $user,
            'deleteBlocked' => $this->deleteBlockedReason($user),
            'twoFactor' => $user->isStudent() ? null : $this->twoFactorState($user, app(TwoFactor::class)),
        ]);
    }

    /** What the two-factor card shows: off, a setup waiting for its first code, or on. */
    private function twoFactorState(User $user, TwoFactor $twoFactor): array
    {
        if ($user->hasTwoFactorEnabled()) {
            return ['state' => 'on', 'codes_left' => $twoFactor->recoveryCodesLeft($user)];
        }

        if ($user->two_factor_secret) {
            return ['state' => 'setup', 'qr' => $twoFactor->qrSvg($user), 'key' => $twoFactor->readableKey($user)];
        }

        return ['state' => 'off'];
    }

    /** Notification and privacy switches. */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'notify_in_app' => $request->boolean('notify_in_app'),
            'notify_email' => $request->boolean('notify_email'),
            // Only students appear on the leaderboard, so other roles keep their value.
            'show_on_leaderboard' => $user->isStudent() ? $request->boolean('show_on_leaderboard') : $user->show_on_leaderboard,
        ]);

        return back()->with('toast', 'Settings saved.');
    }

    /** Delete the logged-in user's own account after the password is confirmed. */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validateWithBag('deleteAccount', ['password' => ['required', 'current_password']]);

        if ($reason = $this->deleteBlockedReason($user)) {
            return back()->withErrors(['password' => $reason], 'deleteAccount');
        }

        Activity::log('user.deleted', ucfirst($user->role).' '.$user->name.' deleted their own account.', $user);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        if ($user->membership) {
            Storage::delete($user->membership->student_card_path);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        return redirect()->route('login')->with('status', 'Your account has been deleted.');
    }

    /**
     * Why this user cannot delete their own account, or null when they can.
     * Deleting an advisor would also delete their events and the merit tied to them.
     */
    private function deleteBlockedReason(User $user): ?string
    {
        if ($user->isCoordinator()) {
            return 'A coordinator account must be removed by another coordinator.';
        }

        if ($user->isAdvisor() && Event::where('advisor_id', $user->id)->exists()) {
            return 'You still own events. Ask a coordinator to remove your account instead.';
        }

        return null;
    }
}
