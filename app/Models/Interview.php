<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interview extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const LOCATION_ZOOM = 'zoom';
    public const LOCATION_IN_PERSON = 'in_person';
    public const LOCATION_OTHER = 'other';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'interview_type',
        'scheduled_start_at',
        'scheduled_end_at',
        'timezone',
        'location_type',
        'meeting_link',
        'location_address',
        'status',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InterviewParticipant::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SCHEDULED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function locationTypes(): array
    {
        return [
            self::LOCATION_ZOOM,
            self::LOCATION_IN_PERSON,
            self::LOCATION_OTHER,
        ];
    }
}
