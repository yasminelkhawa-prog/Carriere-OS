<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Models\VideoConfig;
use App\Models\VideoQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VideoConfigController extends Controller
{
    use ResolvesManagedCompany;

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, true);

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('admin.video-configs.index', [
                'requiresCompanySelection' => true,
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'jobs' => collect(),
                'configs' => collect(),
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get(['id', 'title']);

        $configs = VideoConfig::withoutGlobalScopes()
            ->with(['job:id,title', 'questions'])
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.video-configs.index', [
            'requiresCompanySelection' => false,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'jobs' => $jobs,
            'configs' => $configs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $validated = $this->validatePayload($request, $companyId);

        DB::transaction(function () use ($companyId, $validated): void {
            $config = VideoConfig::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'job_id' => $validated['job_id'],
                'name' => trim((string) $validated['name']),
                'read_time_seconds' => (int) $validated['read_time_seconds'],
                'answer_time_seconds' => (int) $validated['answer_time_seconds'],
                'retries_allowed' => (int) $validated['retries_allowed'],
            ]);

            $this->syncQuestions($config, $validated['questions']);
        });

        return redirect()
            ->route('admin.video-configs.index', $this->companyQuery($request))
            ->with('status', __('video_assessment.config.messages.created'));
    }

    public function update(Request $request, VideoConfig $videoConfig): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        abort_unless((string) $videoConfig->company_id === $companyId, 403);

        $validated = $this->validatePayload($request, $companyId);

        DB::transaction(function () use ($videoConfig, $validated): void {
            $videoConfig->forceFill([
                'job_id' => $validated['job_id'],
                'name' => trim((string) $validated['name']),
                'read_time_seconds' => (int) $validated['read_time_seconds'],
                'answer_time_seconds' => (int) $validated['answer_time_seconds'],
                'retries_allowed' => (int) $validated['retries_allowed'],
            ])->save();

            $this->syncQuestions($videoConfig, $validated['questions']);
        });

        return redirect()
            ->route('admin.video-configs.index', $this->companyQuery($request))
            ->with('status', __('video_assessment.config.messages.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, string $companyId): array
    {
        return $request->validate([
            'job_id' => [
                'required',
                'uuid',
                Rule::exists('jobs', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'read_time_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'answer_time_seconds' => ['required', 'integer', 'min:10', 'max:900'],
            'retries_allowed' => ['required', 'integer', 'min:0', 'max:10'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*' => ['required', 'string', 'max:4000'],
        ], [
            'questions.required' => __('video_assessment.config.validation.questions_required'),
            'questions.*.required' => __('video_assessment.config.validation.question_text_required'),
        ]);
    }

    /**
     * @param array<int, string> $questions
     */
    private function syncQuestions(VideoConfig $config, array $questions): void
    {
        VideoQuestion::withoutGlobalScopes()
            ->where('config_id', $config->id)
            ->delete();

        $order = 1;
        foreach ($questions as $questionText) {
            $trimmed = trim((string) $questionText);
            if ($trimmed === '') {
                continue;
            }

            VideoQuestion::withoutGlobalScopes()->create([
                'company_id' => $config->company_id,
                'config_id' => $config->id,
                'display_order' => $order,
                'question_text' => $trimmed,
            ]);

            $order++;
        }
    }

    /**
     * @return array<string, string>
     */
    private function companyQuery(Request $request): array
    {
        $companyId = (string) $request->input('company_id', $request->query('company_id', ''));
        return $companyId !== '' ? ['company_id' => $companyId] : [];
    }
}

