<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StrategyLabBrief extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWED = 'reviewed';
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'brief_title',
        'brief_pdf_url',
        'deadline_at',
        'status',
        'final_decision_status',
        'final_decision_note',
        'final_decision_by_user_id',
        'final_decision_at',
        'generated_ai_request_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'final_decision_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ASSIGNED,
            self::STATUS_SUBMITTED,
            self::STATUS_REVIEWED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function decisionStatuses(): array
    {
        return [
            self::DECISION_APPROVED,
            self::DECISION_REJECTED,
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

    public function generatedAiRequest(): BelongsTo
    {
        return $this->belongsTo(AiRequest::class, 'generated_ai_request_id');
    }

    public function finalDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_decision_by_user_id');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(StrategyLabSubmission::class, 'application_id', 'application_id');
    }

    public function aiSummary(): HasOne
    {
        return $this->hasOne(StrategyLabAiSummary::class, 'application_id', 'application_id');
    }
}
