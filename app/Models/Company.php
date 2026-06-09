<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'brand_logo_url',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_memberships')
            ->withPivot(['id', 'company_role', 'membership_status'])
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function registrationRequests(): HasMany
    {
        return $this->hasMany(CompanyRegistrationRequest::class);
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CompanyValue::class);
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(FaqItem::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(CompanyIntegration::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function retentionSetting(): HasOne
    {
        return $this->hasOne(CompanyRetentionSetting::class);
    }
}
