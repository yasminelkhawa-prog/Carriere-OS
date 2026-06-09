<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingSchedule extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'onboarding_schedule';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'title',
        'start_at',
        'end_at',
        'location',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'created_at' => 'datetime',
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
