<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\PsyTest;
use App\Services\PsyTestService;
use App\Mail\PsyTestInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PsyTestController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly PsyTestService $psyTestService
    ) {}

    public function index(Request $request)
    {
        $companyId = $this->managedCompanyId($request, false);
        abort_unless((bool) $companyId, 403, 'Company not found in context.');

        $query = PsyTest::with('application.job')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at');

        if ($request->has('status') && $request->get('status') !== '') {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('profile') && $request->get('profile') !== '') {
            $query->where('profile', $request->get('profile'));
        }

        $psyTests = $query->paginate(15)->withQueryString();

        // withoutGlobalScope bypasses the BelongsToCompany session filter on Candidate
        $applications = Application::with([
                'candidate' => fn($q) => $q->withoutGlobalScope('company'),
                'job',
            ])
            ->where('company_id', $companyId)
            ->where('status', Application::STATUS_ACTIVE)
            ->get();

        return view('admin.psy-tests.index', compact('psyTests', 'applications'));
    }

    public function generate(Request $request)
    {
        $companyId = $this->managedCompanyId($request, false);
        abort_unless((bool) $companyId, 403, 'Company not found in context.');

        $validated = $request->validate([
            'application_id' => ['required', 'uuid', 'exists:applications,id'],
            'profile' => ['required', 'string', 'in:' . implode(',', PsyTest::PROFILES)],
            'validity_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);

        $application = Application::where('company_id', $companyId)
            ->findOrFail($validated['application_id']);

        // Load candidate bypassing global company scope
        $candidate = Candidate::withoutGlobalScope('company')->find($application->candidate_id);

        // Check if a pending test already exists for this application
        $existingPending = PsyTest::where('application_id', $application->id)
            ->where('status', PsyTest::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingPending) {
            return back()->with('error', 'Un test est déjà en attente pour ce candidat.');
        }

        $fullName = $candidate->full_name ?? 'Candidat';
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $psyTest = PsyTest::create([
            'company_id' => $companyId,
            'application_id' => $application->id,
            'token' => $this->psyTestService->generateToken(),
            'candidate_first_name' => $firstName,
            'candidate_last_name' => $lastName,
            'candidate_email' => $candidate->email ?? 'no-reply@example.com',
            'profile' => $validated['profile'],
            'status' => PsyTest::STATUS_PENDING,
            'expires_at' => now()->addHours((int) $validated['validity_hours']),
        ]);

        // Send Email
        $testUrl = route('public.psy-test.show', ['token' => $psyTest->token]);
        Mail::to($psyTest->candidate_email)->send(new PsyTestInvitationMail($psyTest, $testUrl));

        return back()
            ->with('success', 'Lien généré avec succès.')
            ->with('generated_url', $testUrl);
    }

    public function show(Request $request, PsyTest $psyTest)
    {
        $companyId = $this->managedCompanyId($request, false);
        abort_unless((bool) $companyId, 403, 'Company not found in context.');
        abort_if((string) $psyTest->company_id !== $companyId, 404);

        $profileData = $this->psyTestService->loadQuestions($psyTest->profile);

        return view('admin.psy-tests.show', compact('psyTest', 'profileData'));
    }
}
