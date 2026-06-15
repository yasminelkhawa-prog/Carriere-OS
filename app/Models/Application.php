<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_HIRED = 'hired';
    public const STATUS_REJECTED = 'rejected';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'candidate_id',
        'job_id',
        'current_stage_id',
        'status',
        'source_type',
        'source_detail',
        'utm_source',
        'utm_campaign',
        'utm_medium',
        'cv_id',
        'score',
        'ai_result_json',
    ];

    protected $casts = [
        'ai_result_json' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_WITHDRAWN,
            self::STATUS_HIRED,
            self::STATUS_REJECTED,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(JobPipelineStage::class, 'current_stage_id');
    }

    public function cvParsingResults(): HasMany
    {
        return $this->hasMany(CvParsingResult::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CandidateNote::class);
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ApplicationActivityEvent::class);
    }

    public function scoring(): HasOne
    {
        return $this->hasOne(ApplicationScoring::class);
    }

    public function unifiedInterviewReport(): HasOne
    {
        return $this->hasOne(UnifiedInterviewReport::class);
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(ApplicationStageHistory::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApplicationTask::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function sjtResponses(): HasMany
    {
        return $this->hasMany(SjtResponse::class);
    }

    public function strategyLabBrief(): HasOne
    {
        return $this->hasOne(StrategyLabBrief::class);
    }

    public function strategyLabSubmission(): HasOne
    {
        return $this->hasOne(StrategyLabSubmission::class);
    }

    public function strategyLabAiSummary(): HasOne
    {
        return $this->hasOne(StrategyLabAiSummary::class);
    }

    public function videoInterviewResponses(): HasMany
    {
        return $this->hasMany(VideoResponse::class);
    }

    public function rejectionDraft(): HasOne
    {
        return $this->hasOne(RejectionDraft::class);
    }

    public function reverseFeedback(): HasOne
    {
        return $this->hasOne(ReverseFeedback::class);
    }

    public function candidateSurvey(): HasOne
    {
        return $this->hasOne(CandidateSurvey::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'source_detail', 'id');
    }

    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function onboardingDocuments(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class)->latest('created_at');
    }

    public function onboardingScheduleItems(): HasMany
    {
        return $this->hasMany(OnboardingSchedule::class)->orderBy('start_at');
    }

    public function onboardingTasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class)->orderByDesc('created_at');
    }

    public function psyTests(): HasMany
    {
        return $this->hasMany(PsyTest::class);
    }
}
