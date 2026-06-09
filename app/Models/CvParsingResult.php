<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvParsingResult extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'cv_parsing_results';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'candidate_id',
        'application_id',
        'source_document_id',
        'source_document_sha256',
        'parser_version',
        'parse_status',
        'profile_summary',
        'parsed_full_name',
        'parsed_email',
        'parsed_phone',
        'parsed_location',
        'total_years_experience',
        'extracted_skills',
        'hard_skills_json',
        'soft_skills_json',
        'tools_frameworks_json',
        'languages_json',
        'job_titles_json',
        'companies_json',
        'experience_entries_json',
        'employment_chronology_json',
        'certifications_json',
        'projects_json',
        'education_entries_json',
        'honors_json',
        'school_categories_json',
        'keywords_json',
        'gender_inference',
        'school_background_tier',
        'ocean_dependency_status',
        'parsed_metadata_json',
        'parsed_payload_json',
        'raw_output_json',
        'flags_json',
        'parse_errors_json',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extracted_skills' => 'array',
            'hard_skills_json' => 'array',
            'soft_skills_json' => 'array',
            'tools_frameworks_json' => 'array',
            'languages_json' => 'array',
            'job_titles_json' => 'array',
            'companies_json' => 'array',
            'experience_entries_json' => 'array',
            'employment_chronology_json' => 'array',
            'certifications_json' => 'array',
            'projects_json' => 'array',
            'education_entries_json' => 'array',
            'honors_json' => 'array',
            'school_categories_json' => 'array',
            'keywords_json' => 'array',
            'total_years_experience' => 'decimal:2',
            'parsed_metadata_json' => 'array',
            'parsed_payload_json' => 'array',
            'raw_output_json' => 'array',
            'flags_json' => 'array',
            'parse_errors_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
