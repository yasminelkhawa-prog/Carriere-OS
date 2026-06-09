<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPost extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const TYPE_KUDOS = 'kudos';
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_IDEA = 'idea';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_TEAM_ONLY = 'team_only';

    public const CONTENT_MAX_LENGTH = 2000;

    protected $table = 'social_posts';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'author_user_id',
        'type',
        'visibility',
        'content_text',
        'media_url',
        'reactions',
        'related_job_id',
        'metadata_json',
        'poll_question_text',
        'poll_options_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'reactions' => 'array',
            'metadata_json' => 'array',
            'poll_options_json' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_KUDOS,
            self::TYPE_WELCOME,
            self::TYPE_ANNOUNCEMENT,
            self::TYPE_IDEA,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function mediaAllowedTypes(): array
    {
        return [
            self::TYPE_WELCOME,
            self::TYPE_ANNOUNCEMENT,
            self::TYPE_IDEA,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function visibilities(): array
    {
        return [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_TEAM_ONLY,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function reactionEntries(): HasMany
    {
        return $this->hasMany(SocialReaction::class, 'post_id');
    }

    public function relatedJob(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'related_job_id');
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(SocialPulsePollVote::class, 'post_id');
    }
}
