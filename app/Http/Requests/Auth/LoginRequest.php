<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginHistory;
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
    public function authorize(): bool
    {
        return true;
    }

    /**
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
     * Authenticate with username or email.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $password = (string) $this->input('password');
        $remember = $this->boolean('remember');

        $user = User::query()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->first();

        $credentials = $user
            ? ['id' => $user->id, 'password' => $password]
            : ['username' => $login, 'password' => $password];

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey());

            LoginHistory::create([
                'user_id' => $user?->id,
                'username' => $login,
                'ip_address' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 255),
                'status' => 'failed',
                'failure_reason' => 'Invalid credentials',
            ]);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        $authenticated = Auth::user();

        if (! $authenticated->is_active) {
            Auth::logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            LoginHistory::create([
                'user_id' => $authenticated->id,
                'username' => $login,
                'ip_address' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 255),
                'status' => 'blocked',
                'failure_reason' => 'Account deactivated',
            ]);

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Contact the system administrator.',
            ]);
        }

        $authenticated->forceFill(['last_login_at' => now()])->saveQuietly();

        LoginHistory::create([
            'user_id' => $authenticated->id,
            'username' => $authenticated->username,
            'ip_address' => $this->ip(),
            'user_agent' => substr((string) $this->userAgent(), 0, 255),
            'status' => 'success',
            'logged_in_at' => now(),
        ]);

        \App\Support\AuditLogger::log('auth.login', User::class, $authenticated->id, 'User logged in');

        RateLimiter::clear($this->throttleKey());
    }

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

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('login')) . '|' . $this->ip());
    }
}
