<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentimentResult extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';
    public const RISK_PENDING = 'pending';

    protected $table = 'sentiment_results';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'sentiment_score',
        'top_themes_json',
        'risk_level',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentiment_score' => 'float',
            'top_themes_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function riskLevels(): array
    {
        return [
            self::RISK_LOW,
            self::RISK_MEDIUM,
            self::RISK_HIGH,
            self::RISK_CRITICAL,
            self::RISK_PENDING,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

