<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Resolve the email for a login value (email, student ID or staff ID).
     */
    protected function resolveEmail(): string
    {
        $login = trim($this->string('login')->toString());

        if (str_contains($login, '@')) {
            return Str::lower($login);
        }

        return User::where('student_id', Str::upper($login))
            ->orWhere('staff_id', Str::upper($login))
            ->value('email') ?? '';
    }

    /**
     * Check the login and password and return the user, WITHOUT logging them in.
     * The controller decides whether a second step (two-factor) comes before the login.
     *
     * @throws ValidationException
     */
    public function verifyCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::guard()->getProvider();
        $user = $provider->retrieveByCredentials(['email' => $this->resolveEmail()]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => $this->string('password')->toString()])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if ($user->isDeactivated()) {
            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Contact the Petakom Coordinator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Check the credentials and log the user in (accounts without two-factor).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        Auth::login($this->verifyCredentials(), $this->boolean('remember'));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
