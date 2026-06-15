<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Company;
use App\Models\RecruitmentNeed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    /**
     * Resolve company_id using every possible source, in priority order.
     */
    private function resolveCompanyId(Request $request): ?string
    {
        // 1. Session — set for regular (non-superadmin) users
        $fromSession = session('active_company_id');
        if (is_string($fromSession) && $fromSession !== '') {
            return $fromSession;
        }

        // 2. Request body — sent by chatbot frontend JS
        $fromRequest = $request->input('company_id');
        if (is_string($fromRequest) && $fromRequest !== '') {
            return $fromRequest;
        }

        // 3. Referer URL — browser auto-sends the page URL as Referer header
        //    For superadmin on /overview?company_id=xxx, this extracts the UUID
        $referer = $request->header('Referer', '');
        if ($referer !== '') {
            $queryString = parse_url($referer, PHP_URL_QUERY);
            if (is_string($queryString)) {
                parse_str($queryString, $refererParams);
                $fromReferer = $refererParams['company_id'] ?? null;
                if (is_string($fromReferer) && $fromReferer !== '') {
                    return $fromReferer;
                }
            }
        }

        // 4. User's first active company membership
        $user = Auth::user();
        if ($user instanceof User) {
            $membership = $user->activeMemberships()->first();
            if ($membership) {
                return (string) $membership->company_id;
            }

            // 5. For superadmin with no membership: first active company in DB
            if ($user->isSuperadmin()) {
                $firstCompany = Company::where('status', Company::STATUS_ACTIVE)->first();
                if ($firstCompany) {
                    return (string) $firstCompany->id;
                }
            }
        }

        return null;
    }

    public function handleChat(Request $request)
    {
        $messages = $request->input('messages', []);

        if (empty($messages)) {
            return response()->json(['error' => 'Messages required'], 400);
        }

        $apiKey = env('OPENROUTER_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'Missing OPENROUTER_API_KEY'], 500);
        }

        $companyId = $this->resolveCompanyId($request);

        $system = "You are an HR analyst assistant embedded in an Applicant Tracking System (ATS).
You help HR managers understand the candidate pipeline and recruitment needs (postes).
- Use the provided tools to read live data; NEVER invent candidates or numbers.
- When asked for a report, structure it with clear markdown headings, bullet points, and tables.
- Always cite concrete counts and candidate names when relevant.
- If the user asks something the tools can't answer, say so plainly.
Today: " . date('Y-m-d') . ".";

        array_unshift($messages, ['role' => 'system', 'content' => $system]);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_applications',
                    'description' => 'Query the ATS applications table to fetch candidates. Returns up to 50 rows.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Filter by application status (active, rejected, hired, withdrawn)'],
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'applications_stats',
                    'description' => 'Get aggregate statistics across all applications: totals by status.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_recruitment_needs',
                    'description' => 'Query the recruitment needs (postes). Returns up to 50 rows with title, status, department, site, worker type.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Filter by status (Pas encore lancé, En cours, Clôturé)'],
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'recruitment_needs_stats',
                    'description' => 'Get aggregate statistics for recruitment needs (postes): totals by status, by site, by worker type.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[]
                    ]
                ]
            ]
        ];

        $maxSteps = 5;

        while ($maxSteps > 0) {
            $maxSteps--;

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'CarriereOS',
                'Content-Type' => 'application/json',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'openai/gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto'
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => $response->body()], $response->status());
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                return response()->json(['error' => 'No choice returned'], 500);
            }

            $message = $choice['message'];
            $messages[] = $message;

            $finishReason = $choice['finish_reason'] ?? null;

            if ($finishReason === 'tool_calls') {
                $toolCalls = $message['tool_calls'] ?? [];
                foreach ($toolCalls as $toolCall) {
                    $toolName = $toolCall['function']['name'];
                    $toolInput = json_decode($toolCall['function']['arguments'], true) ?? [];

                    $result = $this->executeTool($toolName, $toolInput, $companyId);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'content' => is_string($result) ? $result : json_encode($result)
                    ];
                }
                continue;
            }

            return response()->json([
                'role' => 'assistant',
                'content' => $message['content'] ?? ''
            ]);
        }

        return response()->json(['error' => 'Too many steps'], 500);
    }

    private function executeTool(string $name, array $input, ?string $companyId)
    {
        $user = Auth::user();
        $isSuperadmin = $user instanceof User && $user->isSuperadmin();

        if ($name === 'query_applications') {
            // For non-superadmin: BelongsToCompany global scope auto-filters by session.
            // For superadmin: global scope is skipped, so we must filter manually.
            $query = Application::with(['candidate', 'job']);

            if ($isSuperadmin && $companyId) {
                $query->where('applications.company_id', $companyId);
            }

            if (!empty($input['status'])) {
                $query->where('applications.status', $input['status']);
            }

            $applications = $query->latest()->limit(50)->get()->map(function ($app) {
                return [
                    'id' => $app->id,
                    'status' => $app->status,
                    'candidate_name' => $app->candidate
                        ? $app->candidate->first_name . ' ' . $app->candidate->last_name
                        : 'Unknown',
                    'job_title' => $app->job ? $app->job->title : 'Unknown',
                ];
            });

            return ['count' => $applications->count(), 'applications' => $applications];
        }

        if ($name === 'applications_stats') {
            $query = Application::query();

            if ($isSuperadmin && $companyId) {
                $query->where('applications.company_id', $companyId);
            }

            $total = (clone $query)->count();
            $byStatus = (clone $query)
                ->selectRaw('applications.status, count(*) as count')
                ->groupBy('applications.status')
                ->pluck('count', 'status');

            return ['total' => $total, 'by_status' => $byStatus];
        }

        if ($name === 'query_recruitment_needs') {
            $query = RecruitmentNeed::with(['department']);

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            if (!empty($input['status'])) {
                $query->where('status', $input['status']);
            }

            $needs = $query->latest()->limit(50)->get()->map(function ($need) {
                return [
                    'id' => $need->id,
                    'status' => $need->status,
                    'title' => $need->new_recruit_position_title,
                    'department' => $need->department ? $need->department->name : 'Unknown',
                    'site' => $need->site,
                    'worker_type' => $need->worker_type,
                    'contract_type' => $need->contract_type,
                ];
            });

            return ['count' => $needs->count(), 'needs' => $needs];
        }

        if ($name === 'recruitment_needs_stats') {
            $query = RecruitmentNeed::query();

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $total = (clone $query)->count();
            $byStatus = (clone $query)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');
            $bySite = (clone $query)
                ->selectRaw('site, count(*) as count')
                ->groupBy('site')
                ->pluck('count', 'site');
            $byWorkerType = (clone $query)
                ->selectRaw('worker_type, count(*) as count')
                ->groupBy('worker_type')
                ->pluck('count', 'worker_type');

            return [
                'total' => $total,
                'by_status' => $byStatus,
                'by_site' => $bySite,
                'by_worker_type' => $byWorkerType,
            ];
        }

        return ['error' => 'Unknown tool'];
    }
}
