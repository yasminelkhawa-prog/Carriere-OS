<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiasAlert extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $table = 'bias_alerts';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'job_id',
        'dimension_key',
        'severity',
        'message',
        'created_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function severities(): array
    {
        return [
            self::SEVERITY_LOW,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL,
        ];
    }

    public static function severityRank(string $severity): int
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 4,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_MEDIUM => 2,
            default => 1,
        };
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}

