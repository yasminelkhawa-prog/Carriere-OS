<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Referral extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_HIRED = 'hired';
    public const STATUS_REJECTED = 'rejected';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'referrer_user_id',
        'candidate_email',
        'candidate_name',
        'candidate_linkedin_url',
        'resume_file_url',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_CONVERTED,
            self::STATUS_HIRED,
            self::STATUS_REJECTED,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function linkedApplication(): HasOne
    {
        return $this->hasOne(Application::class, 'source_detail', 'id')
            ->where('source_type', 'referral');
    }
}
