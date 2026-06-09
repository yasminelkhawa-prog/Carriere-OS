<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\CompanyRegistrationRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PlatformConsoleController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('access-platform-console');

        $requestStatusCounts = CompanyRegistrationRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalCompanies = (int) $requestStatusCounts->sum();
        $pendingCompanies = (int) ($requestStatusCounts[CompanyRegistrationRequest::STATUS_PENDING] ?? 0);
        $activeCompanies = (int) ($requestStatusCounts[CompanyRegistrationRequest::STATUS_APPROVED] ?? 0);
        $rejectedCompanies = (int) ($requestStatusCounts[CompanyRegistrationRequest::STATUS_REJECTED] ?? 0);

        $trendMonths = $this->lastMonths(6);
        $trendStart = $trendMonths->first()->startOfMonth();
        $trendEnd = $trendMonths->last()->endOfMonth();

        $trendRows = CompanyRegistrationRequest::query()
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->get(['created_at', 'status']);

        $registrationTrend = $trendMonths->map(function (CarbonImmutable $month) use ($trendRows): array {
            $monthKey = $month->format('Y-m');
            $monthlyRows = $trendRows->filter(function (CompanyRegistrationRequest $row) use ($monthKey): bool {
                return $row->created_at?->format('Y-m') === $monthKey;
            });

            return [
                'label' => $month->format('M Y'),
                'total' => $monthlyRows->count(),
                'pending' => $monthlyRows->where('status', CompanyRegistrationRequest::STATUS_PENDING)->count(),
                'approved' => $monthlyRows->where('status', CompanyRegistrationRequest::STATUS_APPROVED)->count(),
                'rejected' => $monthlyRows->where('status', CompanyRegistrationRequest::STATUS_REJECTED)->count(),
            ];
        })->values();

        $aiStatusCounts = AiRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentApprovals = CompanyRegistrationRequest::query()
            ->with(['company:id,name,slug', 'requestedBy:id,email', 'requestedBy.profile:user_id,full_name'])
            ->where('status', CompanyRegistrationRequest::STATUS_APPROVED)
            ->latest('reviewed_at')
            ->limit(8)
            ->get();

        return view('platform.console', [
            'totalCompanies' => $totalCompanies,
            'pendingCompanies' => $pendingCompanies,
            'activeCompanies' => $activeCompanies,
            'rejectedCompanies' => $rejectedCompanies,
            'approvalRate' => $totalCompanies > 0 ? round(($activeCompanies / $totalCompanies) * 100, 1) : 0.0,
            'registrationTrend' => $registrationTrend,
            'aiStatusCounts' => [
                'queued' => (int) ($aiStatusCounts[AiRequest::STATUS_QUEUED] ?? 0),
                'running' => (int) ($aiStatusCounts[AiRequest::STATUS_RUNNING] ?? 0),
                'succeeded' => (int) ($aiStatusCounts[AiRequest::STATUS_SUCCEEDED] ?? 0),
                'failed' => (int) ($aiStatusCounts[AiRequest::STATUS_FAILED] ?? 0),
            ],
            'recentApprovals' => $recentApprovals,
        ]);
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function lastMonths(int $count): Collection
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($count - 1);

        return collect(range(0, $count - 1))
            ->map(static fn (int $offset): CarbonImmutable => $start->addMonths($offset));
    }
}
