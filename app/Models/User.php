<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use HasUuids;
    use Notifiable;

    public const ROLE_COMPANY_ADMIN = 'company_admin';
    public const ROLE_RECRUITER = 'recruiter';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_CANDIDATE = 'candidate';
    public const PLATFORM_SUPERADMIN = 'superadmin';
    public const PLATFORM_NONE = 'none';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'platform_role',
        'active',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_COMPANY_ADMIN,
            self::ROLE_RECRUITER,
            self::ROLE_MANAGER,
            self::ROLE_EMPLOYEE,
            self::ROLE_CANDIDATE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function platformRoles(): array
    {
        return [
            self::PLATFORM_SUPERADMIN,
            self::PLATFORM_NONE,
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function hasRole(string $role): bool
    {
        $activeCompanyId = session('active_company_id');

        if ($activeCompanyId === null) {
            return false;
        }

        return $this->memberships()
            ->where('company_id', $activeCompanyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', $role)
            ->exists();
    }

    public function isSuperadmin(): bool
    {
        return $this->platform_role === self::PLATFORM_SUPERADMIN;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_memberships')
            ->withPivot(['id', 'company_role', 'membership_status'])
            ->withTimestamps();
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('membership_status', CompanyMembership::STATUS_ACTIVE);
    }

    public function secureSettings(): HasMany
    {
        return $this->hasMany(UserSecureSetting::class);
    }

    public function interviewFeedback(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class, 'author_user_id');
    }
}
