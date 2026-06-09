<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if (app()->runningInConsole() || app()->runningUnitTests()) {
                return;
            }

            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            if ($user->isSuperadmin()) {
                return;
            }

            $activeCompanyId = session('active_company_id');

            if ($activeCompanyId === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.'.config('guardrails.tenant.scope_column', 'company_id'),
                $activeCompanyId
            );
        });
    }

    public function scopeForCompany(Builder $query, int|string $companyId): Builder
    {
        return $query->where(config('guardrails.tenant.scope_column', 'company_id'), $companyId);
    }
}
