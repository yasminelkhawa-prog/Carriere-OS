<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Feeds\AggregatorJobsXmlFeedService;
use Illuminate\Http\Response;

class AggregatorJobsFeedController extends Controller
{
    public function __construct(
        private readonly AggregatorJobsXmlFeedService $feedService
    ) {
    }

    public function show(string $board = 'indeed'): Response
    {
        return response(
            $this->feedService->getCachedFeedXml($board),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }
}
