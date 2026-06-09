<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialReaction extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const TYPE_FIRE = "\u{1F525}";
    public const TYPE_HEART = "\u{1F49C}";
    public const TYPE_CLAP = "\u{1F44F}";
    public const TYPE_WAVE = "\u{1F44B}";

    public const UPDATED_AT = null;

    protected $table = 'social_reactions';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'post_id',
        'reaction_type',
        'user_id',
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
            self::TYPE_FIRE,
            self::TYPE_HEART,
            self::TYPE_CLAP,
            self::TYPE_WAVE,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
