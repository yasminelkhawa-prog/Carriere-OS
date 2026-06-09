<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationScoring extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'application_scorings';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'global_match_score',
        'vrin_json',
        'component_scores_json',
        'source_status_json',
        'strengths_json',
        'weaknesses_json',
        'xai_summary',
        'overall_recommendation',
        'ranking_position',
        'ranking_percentile',
        'is_top_three',
        'analysis_status',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'global_match_score' => 'decimal:2',
            'vrin_json' => 'array',
            'component_scores_json' => 'array',
            'source_status_json' => 'array',
            'strengths_json' => 'array',
            'weaknesses_json' => 'array',
            'ranking_position' => 'integer',
            'ranking_percentile' => 'decimal:2',
            'is_top_three' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
