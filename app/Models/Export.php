<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const TYPE_DASHBOARD_OVERVIEW = 'dashboard_overview';
    public const TYPE_CANDIDATE_LIST = 'candidate_list';

    public const FORMAT_CSV = 'csv';
    public const FORMAT_PDF = 'pdf';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'exports';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'export_type',
        'requested_by_user_id',
        'filters_json',
        'format',
        'status',
        'file_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_DASHBOARD_OVERVIEW,
            self::TYPE_CANDIDATE_LIST,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function formats(): array
    {
        return [
            self::FORMAT_CSV,
            self::FORMAT_PDF,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
