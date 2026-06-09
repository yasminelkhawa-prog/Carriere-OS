<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoQuestion extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'video_questions';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'config_id',
        'display_order',
        'question_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(VideoConfig::class, 'config_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(VideoResponse::class, 'question_id');
    }
}

