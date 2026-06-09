<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewFeedback extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public $timestamps = false;
    protected $table = 'interview_feedback';
    protected $keyType = 'string';
    public $incrementing = false;
    public const RECOMMENDATION_HIRE = 'hire';
    public const RECOMMENDATION_HOLD = 'hold';
    public const RECOMMENDATION_NO = 'no';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'interview_id',
        'author_user_id',
        'ratings_json',
        'recommendation',
        'notes',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ratings_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return array<int, string>
     */
    public static function recommendations(): array
    {
        return [
            self::RECOMMENDATION_HIRE,
            self::RECOMMENDATION_HOLD,
            self::RECOMMENDATION_NO,
        ];
    }
}
