<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRequest extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ai_requests';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'request_type',
        'input_hash',
        'status',
        'model_name',
        'prompt_version',
        'request_payload',
        'response_payload',
        'error_message',
        'started_at',
        'finished_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(AiArtifact::class, 'ai_request_id');
    }
}
