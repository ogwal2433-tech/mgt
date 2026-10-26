<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'employee_id',
        'is_active',
        'must_change_password',
        'two_factor_secret',
        'two_factor_enabled',
        'last_login_at',
        'deactivated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function notificationsLog(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function loginHistory(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * Send the queued password-reset notification (never blocks the request).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function employeeContract(): HasOne
    {
        return $this->hasOne(EmployeeContract::class, 'employee_id', 'employee_id');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === $slug);
    }

    /** @param iterable<string> $slugs */
    public function hasAnyRole(iterable $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasRole($slug)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->permissions->contains(
            fn (Permission $permission) => $permission->slug === $slug
        ));
    }

    /** @param iterable<string> $slugs */
    public function hasAnyPermission(iterable $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        $slugs = [];
        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $slugs[] = $permission->slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    public function isManagingDirector(): bool
    {
        return $this->hasRole('managing_director');
    }

    public function isHr(): bool
    {
        return $this->hasAnyRole(['hr', 'hr_finance']);
    }

    public function isTechnicalLead(): bool
    {
        return $this->hasRole('technical_lead');
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasRole('system_admin');
    }

    public function displayName(): string
    {
        return $this->employee?->full_name ?? $this->name;
    }

    /**
     * Send the branded Biiteek password-reset notification.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
