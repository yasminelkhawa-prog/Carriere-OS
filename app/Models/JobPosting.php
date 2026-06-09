<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Multiposting\MultipostingChannelRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_DISABLED = 'disabled';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY = 'ready';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'job_id',
        'platform',
        'status',
        'ai_generated_content',
        'tracking_url',
        'clicks_count',
        'posted_at',
        'last_publish_attempted_at',
        'last_publish_succeeded_at',
        'last_publish_status',
        'last_execution_mode',
        'last_publish_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicks_count' => 'integer',
            'posted_at' => 'datetime',
            'last_publish_attempted_at' => 'datetime',
            'last_publish_succeeded_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DISABLED,
            self::STATUS_DRAFT,
            self::STATUS_GENERATING,
            self::STATUS_READY,
            self::STATUS_PUBLISHING,
            self::STATUS_PUBLISHED,
            self::STATUS_FAILED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function platforms(): array
    {
        return app(MultipostingChannelRegistry::class)->jobBoardPlatforms(true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function clickEvents(): HasMany
    {
        return $this->hasMany(ClickEvent::class);
    }

    public function publishAttempts(): HasMany
    {
        return $this->hasMany(JobPostingPublishAttempt::class)->orderByDesc('created_at');
    }
}
