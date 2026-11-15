<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link to the account that matches a student ID, staff ID or e-mail.
     * The answer is always the same, so the page cannot be used to find out who has an account.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $login = trim($data['login']);

        $user = str_contains($login, '@')
            ? User::where('email', Str::lower($login))->first()
            : User::where('student_id', Str::upper($login))->orWhere('staff_id', Str::upper($login))->first();

        if ($user && ! $user->isDeactivated()) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'If an account matches, we have sent a link to its e-mail address. Check your inbox and spam folder.');
    }
}
