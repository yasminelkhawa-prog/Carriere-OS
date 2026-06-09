<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingDocument extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const TYPE_ID = 'id_document';
    public const TYPE_TAX = 'tax_form';
    public const TYPE_BANK = 'bank_details';
    public const TYPE_DIPLOMA = 'diploma';
    public const TYPE_OTHER = 'other';

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'application_id',
        'doc_type',
        'file_url',
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

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ID,
            self::TYPE_TAX,
            self::TYPE_BANK,
            self::TYPE_DIPLOMA,
            self::TYPE_OTHER,
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
