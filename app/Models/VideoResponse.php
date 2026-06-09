<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoResponse extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'video_responses';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'question_id',
        'attempt_number',
        'video_file_url',
        'duration_seconds',
        'pauses_count',
        'speech_rate_estimate',
        'filler_ratio_estimate',
        'transcript_text',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'duration_seconds' => 'integer',
            'pauses_count' => 'integer',
            'speech_rate_estimate' => 'decimal:2',
            'filler_ratio_estimate' => 'decimal:4',
            'created_at' => 'datetime',
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

    public function question(): BelongsTo
    {
        return $this->belongsTo(VideoQuestion::class, 'question_id');
    }
}

