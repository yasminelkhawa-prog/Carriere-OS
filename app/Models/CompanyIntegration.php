<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyIntegration extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    public const PROVIDER_LINKEDIN = 'linkedin';

    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ERROR = 'error';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'provider',
        'status',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'granted_scopes_json',
        'external_account_id',
        'external_account_name',
        'last_connected_at',
        'last_used_at',
        'last_error',
        'meta_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'granted_scopes_json' => 'array',
            'last_connected_at' => 'datetime',
            'last_used_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function providers(): array
    {
        return [
            self::PROVIDER_LINKEDIN,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && trim((string) $this->access_token) !== '';
    }
}
