<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentNeed extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'department_id',
        'year',
        'site',
        'departing_position_title',
        'departure_date',
        'departure_reason',
        'new_recruit_position_title',
        'budget_approved',
        'status',
        'contract_type',
        'worker_type',
        'recruitment_type',
        'internal_posting',
        'external_sourcing',
        'sourcing_tools',
        'new_recruit_name',
        'gender',
        'expected_start_date',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'expected_start_date' => 'date',
        'budget_approved' => 'boolean',
        'internal_posting' => 'boolean',
        'external_sourcing' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
