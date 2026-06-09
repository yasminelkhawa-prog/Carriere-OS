<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMembership extends Model
{
    use HasFactory;
    use HasUuids;

    public const ROLE_COMPANY_ADMIN = 'company_admin';
    public const ROLE_RECRUITER = 'recruiter';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_CANDIDATE = 'candidate';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'company_role',
        'membership_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
