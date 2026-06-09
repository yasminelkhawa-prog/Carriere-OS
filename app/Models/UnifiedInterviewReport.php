<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnifiedInterviewReport extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'ai_full_payload',
        'xai_summary',
        'ocean_openness',
        'ocean_conscientiousness',
        'ocean_extraversion',
        'ocean_agreeableness',
        'ocean_neuroticism',
        'generic_motivation',
        'match_percentage',
        'salary_expected_min',
        'salary_expected_max',
        'salary_currency',
        'salary_fit_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_full_payload' => 'array',
            'generic_motivation' => 'boolean',
            'match_percentage' => 'decimal:2',
            'salary_fit_score' => 'decimal:2',
        ];
    }

    public function getIsGenericMotivationAttribute(): bool
    {
        return (bool) ($this->attributes['generic_motivation'] ?? false);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
