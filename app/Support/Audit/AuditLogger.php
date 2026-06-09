<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $actionType,
        string $entityType,
        ?string $entityId = null,
        array $metadata = [],
        ?Request $request = null,
        ?User $actor = null,
        ?string $companyId = null
    ): void {
        $actor ??= Auth::user();
        $request ??= request();
        $companyId ??= session('active_company_id');
        if ($companyId === null && $entityType === 'company' && $entityId !== null) {
            $companyId = $entityId;
        }

        AuditLog::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'actor_user_id' => $actor?->id,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => (string) $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
