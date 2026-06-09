<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\CompanyRegistrationApprovedMail;
use App\Mail\CompanyRegistrationRejectedMail;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyRegistrationRequest;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

class CompanyApprovalController extends Controller
{
    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function index(): View
    {
        $this->authorize('access-platform-console');

        $allowedStatuses = [
            CompanyRegistrationRequest::STATUS_PENDING,
            CompanyRegistrationRequest::STATUS_APPROVED,
            CompanyRegistrationRequest::STATUS_REJECTED,
        ];

        $status = request()->input('status');

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = CompanyRegistrationRequest::query()
            ->with(['company:id,name,slug,status', 'requestedBy.profile'])
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('platform.company-approvals', [
            'requests'       => $requests,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Company $company): View
    {
        $this->authorize('access-platform-console');

        $registrationRequest = CompanyRegistrationRequest::query()
            ->with(['requestedBy.profile', 'reviewedBy.profile'])
            ->where('company_id', $company->id)
            ->latest('created_at')
            ->firstOrFail();

        return view('platform.company-approval-show', [
            'company' => $company,
            'registrationRequest' => $registrationRequest,
        ]);
    }

    public function approve(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('access-platform-console');

        $registrationRequest = CompanyRegistrationRequest::query()
            ->where('company_id', $company->id)
            ->where('status', CompanyRegistrationRequest::STATUS_PENDING)
            ->latest('created_at')
            ->firstOrFail();

        $company->update([
            'status' => Company::STATUS_ACTIVE,
        ]);

        CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('membership_status', CompanyMembership::STATUS_PENDING)
            ->update(['membership_status' => CompanyMembership::STATUS_ACTIVE]);

        $registrationRequest->update([
            'status' => CompanyRegistrationRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $adminRecipients = $company->memberships()
            ->with(['user.profile'])
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_COMPANY_ADMIN)
            ->get()
            ->pluck('user')
            ->filter(static fn ($user): bool => $user !== null && is_string($user->email) && $user->email !== '')
            ->unique('email')
            ->values();

        if ($adminRecipients->isEmpty() && $registrationRequest->requestedBy !== null) {
            $adminRecipients = collect([$registrationRequest->requestedBy->loadMissing('profile')]);
        }

        $failedDeliveries = 0;

        foreach ($adminRecipients as $recipient) {
            $locale = $recipient->profile?->locale ?? config('app.locale');
            $verificationUrl = $recipient->hasVerifiedEmail()
                ? null
                : URL::temporarySignedRoute(
                    'verification.verify',
                    now()->addMinutes((int) config('auth.verification.expire', 60)),
                    [
                        'id' => $recipient->getKey(),
                        'hash' => sha1($recipient->getEmailForVerification()),
                    ]
                );

            try {
                Mail::to($recipient->email)->sendNow(new CompanyRegistrationApprovedMail(
                    company: $company,
                    recipientName: $recipient->profile?->full_name ?? $recipient->email,
                    mailLocale: $locale,
                    verificationUrl: $verificationUrl
                ));
            } catch (Throwable $exception) {
                $failedDeliveries++;
                report($exception);
            }
        }

        $this->sensitiveEvents->companyApproved(
            companyId: (string) $company->id,
            metadata: [
                'company_slug' => $company->slug,
                'registration_request_id' => $registrationRequest->id,
            ],
            actor: $request->user()
        );

        $response = back()->with('status', __('platform.company_approved'));

        if ($failedDeliveries > 0) {
            $response->with('warning', "Approval completed, but {$failedDeliveries} email notification(s) failed to send.");
        }

        return $response;
    }

    public function reject(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('access-platform-console');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $registrationRequest = CompanyRegistrationRequest::query()
            ->where('company_id', $company->id)
            ->where('status', CompanyRegistrationRequest::STATUS_PENDING)
            ->latest('created_at')
            ->firstOrFail();

        $company->update([
            'status' => Company::STATUS_REJECTED,
        ]);

        $registrationRequest->update([
            'status' => CompanyRegistrationRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $recipient = $registrationRequest->requestedBy;
        if ($recipient !== null) {
            $locale = $recipient->profile?->locale ?? config('app.locale');
            Mail::to($recipient->email)->queue(new CompanyRegistrationRejectedMail(
                company: $company,
                recipientName: $recipient->profile?->full_name ?? $recipient->email,
                rejectionReason: $validated['rejection_reason'],
                mailLocale: $locale
            ));
        }

        $this->sensitiveEvents->companyRejected(
            companyId: (string) $company->id,
            metadata: [
                'company_slug' => $company->slug,
                'registration_request_id' => $registrationRequest->id,
                'rejection_reason' => $validated['rejection_reason'],
            ],
            actor: $request->user()
        );

        return back()->with('status', __('platform.company_rejected'));
    }
}
