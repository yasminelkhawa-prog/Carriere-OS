<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewParticipant extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'interview_id',
        'user_id',
        'participant_role',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
