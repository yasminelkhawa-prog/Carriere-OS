<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'full_name',
        'email',
        'phone',
        'location',
        'years_experience',
        'last_company',
        'main_skills',
        'diploma_type',
        'school_type',
        'school_name',
        'school_country',
        'notification_preferences_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_preferences_json' => 'array',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'job_match' => true,
            'status_change' => true,
            'interview_invite' => true,
            'interview_reminder' => true,
            'recruiter_message' => true,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        return array_merge(
            self::defaultNotificationPreferences(),
            (array) ($this->notification_preferences_json ?? [])
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function cvParsingResults(): HasMany
    {
        return $this->hasMany(CvParsingResult::class);
    }
}
