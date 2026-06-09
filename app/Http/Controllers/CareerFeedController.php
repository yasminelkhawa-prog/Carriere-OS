<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Feeds\AggregatorJobsXmlFeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CareerFeedController extends Controller
{
    public function __construct(
        private readonly AggregatorJobsXmlFeedService $feedService
    ) {
    }

    public function jobs(Request $request, Company $company): Response
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:80'],
            'campaign' => ['nullable', 'string', 'max:120'],
        ]);

        $source = trim((string) ($validated['source'] ?? 'indeed'));
        $source = $source !== '' ? $source : 'indeed';
        $campaign = trim((string) ($validated['campaign'] ?? ''));
        $campaign = $campaign !== '' ? $campaign : null;

        return $this->respondWithFeed(
            company: $company,
            board: $source,
            trackingSource: $source,
            campaign: $campaign
        );
    }

    public function indeed(Request $request, Company $company): Response
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:120'],
        ]);

        $campaign = trim((string) ($validated['campaign'] ?? ''));

        return $this->respondWithFeed(
            company: $company,
            board: 'indeed',
            trackingSource: 'indeed',
            campaign: $campaign !== '' ? $campaign : null
        );
    }

    public function syndication(Request $request, Company $company): Response
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:120'],
        ]);

        $campaign = trim((string) ($validated['campaign'] ?? ''));

        return $this->respondWithFeed(
            company: $company,
            board: 'syndication',
            trackingSource: 'glassdoor',
            campaign: $campaign !== '' ? $campaign : null
        );
    }

    private function respondWithFeed(
        Company $company,
        string $board,
        ?string $trackingSource = null,
        ?string $campaign = null
    ): Response {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);

        return response(
            $this->feedService->buildFeedXml(
                company: $company,
                board: $board,
                trackingSource: $trackingSource,
                campaign: $campaign
            ),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }
}
