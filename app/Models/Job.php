<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Job extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const EMPLOYMENT_FULL_TIME = 'full_time';
    public const EMPLOYMENT_PART_TIME = 'part_time';
    public const EMPLOYMENT_CONTRACT = 'contract';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'department_id',
        'title',
        'description_html',
        'location',
        'location_street',
        'location_city',
        'location_country',
        'location_postal_code',
        'employment_type',
        'status',
        'blind_mode_active',
        'salary_min',
        'salary_max',
        'salary_currency',
        'salary_budget_max',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blind_mode_active' => 'boolean',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'salary_budget_max' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function employmentTypes(): array
    {
        return [
            self::EMPLOYMENT_FULL_TIME,
            self::EMPLOYMENT_PART_TIME,
            self::EMPLOYMENT_CONTRACT,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @return array<int, string>
     */
    public static function blockTypes(): array
    {
        return [
            'overview',
            'responsibilities',
            'requirements',
            'benefits',
            'company_intro',
            'custom',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function descriptionBlocks(): HasMany
    {
        return $this->hasMany(JobDescriptionBlock::class)->orderBy('display_order');
    }

    public function persona(): HasOne
    {
        return $this->hasOne(JobPersona::class);
    }

    public function weightingConfig(): HasOne
    {
        return $this->hasOne(JobWeightingConfig::class);
    }

    public function pipelineStages(): HasMany
    {
        return $this->hasMany(JobPipelineStage::class)->orderBy('display_order');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function sjtScenarios(): HasMany
    {
        return $this->hasMany(SjtScenario::class);
    }

    public function videoConfigs(): HasMany
    {
        return $this->hasMany(VideoConfig::class);
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }
}
