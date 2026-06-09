<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const LANGUAGE_EN = 'en';
    public const LANGUAGE_FR = 'fr';

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'template_key',
        'language',
        'subject_template',
        'body_template',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function languages(): array
    {
        return [self::LANGUAGE_EN, self::LANGUAGE_FR];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

