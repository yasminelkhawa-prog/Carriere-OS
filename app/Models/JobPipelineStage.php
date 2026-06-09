<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPipelineStage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'job_pipeline_stages';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'stage_key',
        'stage_label',
        'display_order',
        'is_terminal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_terminal' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'current_stage_id');
    }
}
