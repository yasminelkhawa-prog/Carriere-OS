<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsyTest extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    public const PROFILES = ['ingenieur', 'management', 'finance'];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'application_id',
        'token',
        'candidate_first_name',
        'candidate_last_name',
        'candidate_email',
        'profile',
        'status',
        'expires_at',
        'completed_at',
        'score',
        'answers_json',
        'dimension_scores_json',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'answers_json' => 'array',
        'dimension_scores_json' => 'array',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_COMPLETED,
            self::STATUS_EXPIRED,
        ];
    }

    public static function profileLabels(): array
    {
        return [
            'ingenieur' => 'Ingénieur',
            'management' => 'Management',
            'finance' => 'Finance',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getCandidateFullNameAttribute(): string
    {
        return trim($this->candidate_first_name . ' ' . $this->candidate_last_name);
    }

    public function getProfileLabelAttribute(): string
    {
        return self::profileLabels()[$this->profile] ?? $this->profile;
    }
}
