<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiasAuditStat extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'bias_audit_stats';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'job_id',
        'stage_id',
        'time_bucket_start',
        'time_bucket_end',
        'dimension_key',
        'group_a_count',
        'group_b_count',
        'impact_ratio',
        'fairness_index',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_bucket_start' => 'datetime',
            'time_bucket_end' => 'datetime',
            'group_a_count' => 'integer',
            'group_b_count' => 'integer',
            'impact_ratio' => 'decimal:4',
            'fairness_index' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(JobPipelineStage::class, 'stage_id');
    }
}

