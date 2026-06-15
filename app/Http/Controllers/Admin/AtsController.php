<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use App\Services\AtsService;
use Illuminate\Http\Request;

class AtsController extends Controller
{
    private AtsService $atsService;

    public function __construct(AtsService $atsService)
    {
        $this->atsService = $atsService;
    }

    public function index(Request $request)
    {
        $companyId = $this->managedCompanyId($request);
        $jobs = Job::where('company_id', $companyId)->latest()->get();

        return view('admin.ats.dashboard', compact('jobs'));
    }

    public function uploadCvForm(Request $request, Job $job)
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless((string) $job->company_id === (string) $companyId, 403);

        return view('admin.ats.upload-cv', compact('job'));
    }

    public function storeCv(Request $request, Job $job)
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless((string) $job->company_id === (string) $companyId, 403);

        $request->validate([
            'cv_file' => 'required|file|mimes:pdf,docx,doc|max:10240',
        ]);

        $company = \App\Models\Company::findOrFail($companyId);

        try {
            $application = $this->atsService->processCvUpload($request->file('cv_file'), $company, $job);
            return redirect()->route('ats.candidates', $job)->with('success', 'CV uploaded and analyzed successfully! Score: ' . $application->score);
        } catch (\Exception $e) {
            return back()->withErrors(['cv_file' => 'Error processing CV: ' . $e->getMessage()]);
        }
    }

    public function candidates(Request $request, Job $job)
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless((string) $job->company_id === (string) $companyId, 403);

        // Ranked candidates for this job
        $applications = Application::with('candidate')
            ->where('job_id', $job->id)
            ->whereNotNull('score')
            ->orderByDesc('score')
            ->get();

        return view('admin.ats.candidates', compact('job', 'applications'));
    }

    public function showCandidate(Request $request, Application $application)
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless((string) $application->company_id === (string) $companyId, 403);

        $application->load(['candidate', 'job', 'cv']);

        return view('admin.ats.show-candidate', compact('application'));
    }

    // Helper method to get the company ID, assuming the user is logged in
    // Adapting from other controllers that use a similar trait/method
    private function managedCompanyId(Request $request, bool $strict = false): ?string
    {
        // For simplicity in this isolated module, we retrieve the first company of the user.
        // In a real scenario, this matches the app's context logic.
        $user = $request->user();
        if ($user && $user->isSuperadmin() && $request->has('company_id')) {
            return $request->input('company_id');
        }
        return $user ? $user->companies()->first()?->id : null;
    }
}
