<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjtResponse extends Model
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
        'scenario_id',
        'response_text',
        'copy_paste_blocked_flag',
        'ai_score',
        'ai_feedback_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copy_paste_blocked_flag' => 'boolean',
            'ai_score' => 'decimal:2',
            'ai_feedback_json' => 'array',
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

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(SjtScenario::class, 'scenario_id');
    }
}

