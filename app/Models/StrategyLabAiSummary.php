<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyLabAiSummary extends Model
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
        'application_id',
        'executive_summary_text',
        'strengths_json',
        'weaknesses_json',
        'creativity_score',
        'overall_recommendation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strengths_json' => 'array',
            'weaknesses_json' => 'array',
            'creativity_score' => 'decimal:2',
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
}
