<?php

namespace App\Models;

use App\Constants\SocialStatus;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Role, currently enforced by the Social Media Center only (see
     * App\Services\Social\SocialPermission). Existing admins default to
     * super_admin because the panel had no roles before.
     */
    public function getRoleAttribute($value): string
    {
        return $value ?: SocialStatus::ROLE_SUPER_ADMIN;
    }

    public function getRoleNameAttribute(): string
    {
        return SocialStatus::ROLES[$this->role] ?? 'Super Admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === SocialStatus::ROLE_SUPER_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
