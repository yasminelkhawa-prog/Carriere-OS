<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AiDiagnosticsController;
use App\Http\Controllers\Admin\CompanyValueController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\FaqItemController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\CompanyIntegrationController;
use App\Http\Controllers\Admin\JobMultipostingController;
use App\Http\Controllers\Admin\SjtScenarioController;
use App\Http\Controllers\Admin\ExportHistoryController;
use App\Http\Controllers\Admin\HealthChecklistController;
use App\Http\Controllers\Admin\AtsController;
use App\Http\Controllers\Admin\VideoConfigController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CandidateEmailVerificationLoginController;
use App\Http\Controllers\Auth\CompanyContextController;
use App\Http\Controllers\Auth\CompanyRegistrationController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CareerFeedController;
use App\Http\Controllers\CareerSiteController;
use App\Http\Controllers\CandidateWorkspaceController;
use App\Http\Controllers\CandidateAssessmentController;
use App\Http\Controllers\SocialHubController;
use App\Http\Controllers\FairnessDashboardController;
use App\Http\Controllers\StrategyLabController;
use App\Http\Controllers\VideoInterviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployerBrandController;
use App\Http\Controllers\ReportingExportController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\JobPostingTrackingController;
use App\Http\Controllers\CandidatePortalController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\Platform\AiDiagnosticsController as PlatformAiDiagnosticsController;
use App\Http\Controllers\Platform\CompanyApprovalController;
use App\Http\Controllers\Platform\ContactInquiryController as PlatformContactInquiryController;
use App\Http\Controllers\Platform\PlatformConsoleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', [PublicSiteController::class, 'home'])->name('public.home');
Route::get('/entry/company', [PublicSiteController::class, 'companyEntry'])->name('public.entry.company');
Route::get('/entry/candidate', [PublicSiteController::class, 'candidateEntry'])->name('public.entry.candidate');
Route::get('/jobs', [PublicSiteController::class, 'jobs'])->name('public.jobs.index');
Route::get('/jobs/{job}', [PublicSiteController::class, 'showJob'])->name('public.jobs.show');
Route::get('/about-us', [PublicSiteController::class, 'about'])->name('public.about');
Route::get('/contact-us', [PublicSiteController::class, 'contact'])->name('public.contact');
Route::post('/contact-us', [PublicSiteController::class, 'storeContact'])
    ->middleware('throttle:public-contact')
    ->name('public.contact.store');

// Public route to clear caches (optimize:clear)
Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return response()->json(['status' => 'success', 'message' => Artisan::output()]);
})->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \App\Http\Middleware\EnsureRequiredEnvironmentIsConfigured::class,
]);

// Public route to create storage symlink (storage:link)
Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return response()->json(['status' => 'success', 'message' => Artisan::output()]);
})->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \App\Http\Middleware\EnsureRequiredEnvironmentIsConfigured::class,
]);

// Public installation route for first-time setup (migrate:fresh --seed)
Route::match(['GET', 'POST'], '/install/migrate-fresh-seed', function () {
    $installRouteEnabled = filter_var(env('INSTALL_ROUTE_ENABLED', false), FILTER_VALIDATE_BOOL);
    $expectedKey = (string) env('INSTALL_ROUTE_KEY', '');
    $providedKey = (string) request()->query('key', request()->input('key', ''));

    if (! $installRouteEnabled || $expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Installation endpoint is disabled or unauthorized.',
        ], 403);
    }

    try {
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

        return response()->json([
            'status' => 'success',
            'message' => Artisan::output(),
        ]);
    } catch (\Throwable $exception) {
        return response()->json([
            'status' => 'error',
            'message' => $exception->getMessage(),
        ], 500);
    }
})->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \App\Http\Middleware\EnsureRequiredEnvironmentIsConfigured::class,
]);
Route::get('/careers/{company:slug}', [CareerSiteController::class, 'index'])->name('career.index');
Route::get('/careers/{company:slug}/apply/{job}', [CareerSiteController::class, 'show'])->name('career.apply.entry');
Route::get('/careers/{company:slug}/jobs/{job}', [CareerSiteController::class, 'show'])->name('career.show');
Route::get('/careers/{company:slug}/feeds/jobs.xml', [CareerFeedController::class, 'jobs'])->name('career.feed.jobs');
Route::get('/careers/{company:slug}/feeds/indeed.xml', [CareerFeedController::class, 'indeed'])->name('career.feed.indeed');
Route::get('/careers/{company:slug}/feeds/syndication.xml', [CareerFeedController::class, 'syndication'])->name('career.feed.syndication');
Route::get('/careers/{company:slug}/jobs/{job}/track/{jobPosting}', JobPostingTrackingController::class)->name('career.multiposting.track');
Route::post('/careers/{company:slug}/jobs/{job}/apply', [CareerSiteController::class, 'apply'])
    ->middleware('throttle:10,1')
    ->name('career.apply');
Route::get('/careers/{company:slug}/jobs/{job}/confirmation', [CareerSiteController::class, 'confirmation'])->name('career.apply.confirmation');
Route::post('/careers/{company:slug}/jobs/{job}/save-data', [CareerSiteController::class, 'saveApplicationData'])->name('career.apply.save-data');
Route::get(
    '/candidate/email-verify/{user}/{company}/{application}',
    CandidateEmailVerificationLoginController::class
)->middleware(['signed', 'throttle:6,1'])->name('candidate.email.verify-login');

Route::get('/psy-test/{token}', [\App\Http\Controllers\PublicPsyTestController::class, 'show'])->name('public.psy-test.show');
Route::post('/psy-test/{token}', [\App\Http\Controllers\PublicPsyTestController::class, 'submit'])->name('public.psy-test.submit');


Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'fr'], true), 404);

    session(['locale' => $locale]);
    app()->setLocale($locale);

    return redirect()->back();
})->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth-login')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth-recovery')
        ->name('password.email');

    Route::get('/register', [CompanyRegistrationController::class, 'create'])->name('register');
    Route::get('/company-register', [CompanyRegistrationController::class, 'create'])->name('company.register');
    Route::post('/company-register', [CompanyRegistrationController::class, 'store'])->name('company.register.store');
    Route::get('/company-register/confirmation', [CompanyRegistrationController::class, 'confirmation'])->name('company.register.confirmation');
});

// Password reset links must remain accessible even when a browser is already authenticated.
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:auth-recovery')
    ->name('password.store');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/candidate/{company:slug}', [CandidatePortalController::class, 'show'])->name('candidate.portal');
    Route::get('/candidate/{company:slug}/applications', [CandidatePortalController::class, 'applications'])->name('candidate.applications');
    Route::get('/candidate/{company:slug}/updates', [CandidatePortalController::class, 'updates'])->name('candidate.updates');
    Route::get('/candidate/{company:slug}/status-tracker', [CandidatePortalController::class, 'statusTracker'])->name('candidate.status-tracker');
    Route::get('/candidate/{company:slug}/faq', [CandidatePortalController::class, 'faq'])->name('candidate.faq');
    Route::post('/candidate/{company:slug}/guide/ask', [CandidatePortalController::class, 'askGuide'])
        ->middleware('throttle:ai-endpoints')
        ->name('candidate.guide.ask');
    Route::post('/candidate/{company:slug}/password', [CandidatePortalController::class, 'updatePassword'])
        ->middleware('throttle:10,1')
        ->name('candidate.password.update');
    Route::get('/candidate/{company:slug}/account', [CandidatePortalController::class, 'account'])->name('candidate.account');
    Route::post('/candidate/{company:slug}/account/profile', [CandidatePortalController::class, 'updateProfile'])
        ->middleware('throttle:10,1')
        ->name('candidate.profile.update');
    Route::post('/candidate/{company:slug}/account/notification-preferences', [CandidatePortalController::class, 'updateNotificationPreferences'])
        ->middleware('throttle:10,1')
        ->name('candidate.notification-preferences.update');
    Route::post('/candidate/{company:slug}/account/locale', [CandidatePortalController::class, 'updateLocale'])
        ->middleware('throttle:10,1')
        ->name('candidate.locale.update');
    Route::post('/candidate/{company:slug}/account/delete', [CandidatePortalController::class, 'deleteAccount'])
        ->middleware('throttle:5,1')
        ->name('candidate.account.delete');
    Route::get('/candidate/{company:slug}/cv', [CandidatePortalController::class, 'cv'])->name('candidate.cv');
    Route::post('/candidate/{company:slug}/cv/upload', [CandidatePortalController::class, 'uploadCv'])
        ->middleware('throttle:5,1')
        ->name('candidate.cv.upload');
    Route::post('/candidate/{company:slug}/cv/data', [CandidatePortalController::class, 'updateCvData'])
        ->middleware('throttle:10,1')
        ->name('candidate.cv.data.update');
    Route::post('/candidate/{company:slug}/applications/{application}/reverse-feedback', [CandidatePortalController::class, 'storeReverseFeedback'])
        ->middleware('throttle:10,1')
        ->name('candidate.reverse-feedback.store');
    Route::post('/candidate/{company:slug}/applications/{application}/contract/sign', [CandidatePortalController::class, 'signContract'])
        ->middleware('throttle:10,1')
        ->name('candidate.contract.sign');
    Route::post('/candidate/{company:slug}/applications/{application}/onboarding-documents', [CandidatePortalController::class, 'uploadOnboardingDocument'])
        ->middleware('throttle:15,1')
        ->name('candidate.onboarding-documents.store');
    Route::post('/candidate/{company:slug}/applications/{application}/onboarding-tasks/{onboardingTask}/toggle', [CandidatePortalController::class, 'toggleOnboardingTask'])
        ->middleware('throttle:30,1')
        ->name('candidate.onboarding-tasks.toggle');
    Route::get('/candidate/{company:slug}/social-hub', [SocialHubController::class, 'candidateIndex'])
        ->name('candidate.social-hub.index');
    Route::post('/candidate/{company:slug}/social-hub/posts/{post}/reactions', [SocialHubController::class, 'candidateStoreReaction'])
        ->name('candidate.social-hub.reactions.store');
    Route::post('/candidate/{company:slug}/social-hub/posts/{post}/poll-vote', [SocialHubController::class, 'candidateStorePollVote'])
        ->name('candidate.social-hub.poll-votes.store');
    Route::post('/candidate/{company:slug}/social-hub/posts/{post}/comments', [SocialHubController::class, 'candidateStoreComment'])
        ->name('candidate.social-hub.comments.store');

    Route::get('/auth/company-dispatch', [CompanyContextController::class, 'dispatch'])->name('auth.company.dispatch');
    Route::get('/company-select', [CompanyContextController::class, 'select'])->name('company.select');
    Route::post('/company-select', [CompanyContextController::class, 'storeSelection'])->name('company.select.store');
    Route::post('/company-switch', [CompanyContextController::class, 'switch'])->name('company.switch');
    Route::get('/company-access-status', [CompanyContextController::class, 'accessStatus'])->name('company.access-status');
    Route::post('/candidate/{company:slug}/strategy-lab/{application}/submit', [StrategyLabController::class, 'submitFromPortal'])
        ->middleware('throttle:15,1')
        ->name('candidate.strategy-lab.submit');
    Route::get('/candidate/{company:slug}/video-stories/{application}', [VideoInterviewController::class, 'stories'])
        ->middleware('throttle:30,1')
        ->name('candidate.video-stories');
    Route::post('/candidate/{company:slug}/video-stories/{application}/questions/{videoQuestion}', [VideoInterviewController::class, 'submitStoryQuestion'])
        ->middleware('throttle:30,1')
        ->name('candidate.video-stories.submit');

    Route::middleware(['verified', 'ensure.company.context'])->group(function (): void {
        Route::middleware('can:access-recruitment-workspace')->group(function (): void {
            Route::get('/overview', [DashboardController::class, 'overview'])->name('home');
            Route::post('/overview/export', [ReportingExportController::class, 'storeOverview'])->name('home.export');
            Route::get('/candidates', [CandidateWorkspaceController::class, 'index'])->name('candidates.index');
            Route::post('/candidates/assistant/ask', [CandidateWorkspaceController::class, 'askAssistant'])
                ->middleware('throttle:ai-endpoints')
                ->name('candidates.assistant.ask');
            Route::post('/candidates/export', [ReportingExportController::class, 'storeCandidates'])->name('candidates.export');
            Route::get('/candidates/kanban', [CandidateWorkspaceController::class, 'kanban'])->name('candidates.kanban');
            Route::post('/candidates/{application}/kanban-transition', [CandidateWorkspaceController::class, 'transition'])->name('candidates.kanban.transition');
            Route::post('/candidates/{application}/comments', [CandidateWorkspaceController::class, 'storeComment'])->name('candidates.comments.store');
            Route::post('/candidates/{application}/move-stage', [CandidateWorkspaceController::class, 'moveStage'])->name('candidates.move-stage');
            Route::post('/candidates/{application}/schedule-interview', [CandidateWorkspaceController::class, 'scheduleInterview'])->name('candidates.schedule-interview');
            Route::post('/candidates/{application}/request-feedback', [CandidateWorkspaceController::class, 'requestFeedback'])->name('candidates.request-feedback');
            Route::post('/candidates/{application}/reject', [CandidateWorkspaceController::class, 'reject'])->name('candidates.reject');
            Route::post('/candidates/{application}/onboarding/offer', [CandidateWorkspaceController::class, 'saveOffer'])->name('candidates.onboarding.offer.save');
            Route::post('/candidates/{application}/onboarding/contract', [CandidateWorkspaceController::class, 'saveContract'])->name('candidates.onboarding.contract.save');
            Route::post('/candidates/{application}/onboarding/documents', [CandidateWorkspaceController::class, 'uploadOnboardingDocument'])->name('candidates.onboarding.documents.store');
            Route::post('/candidates/{application}/onboarding/schedule', [CandidateWorkspaceController::class, 'storeOnboardingSchedule'])->name('candidates.onboarding.schedule.store');
            Route::post('/candidates/{application}/onboarding/tasks', [CandidateWorkspaceController::class, 'storeOnboardingTask'])->name('candidates.onboarding.tasks.store');
            Route::post('/candidates/{application}/onboarding/tasks/{onboardingTask}/toggle', [CandidateWorkspaceController::class, 'toggleOnboardingTask'])->name('candidates.onboarding.tasks.toggle');
            Route::post('/candidates/{application}/request-analysis', [CandidateWorkspaceController::class, 'requestAnalysis'])
                ->middleware('throttle:ai-endpoints')
                ->name('candidates.request-analysis');
            Route::post('/candidates/{application}/strategy-lab/assign', [StrategyLabController::class, 'assign'])->name('candidates.strategy-lab.assign');
            Route::post('/candidates/{application}/strategy-lab/extend-deadline', [StrategyLabController::class, 'extendDeadline'])->name('candidates.strategy-lab.extend-deadline');
            Route::post('/candidates/{application}/strategy-lab/mark-reviewed', [StrategyLabController::class, 'markReviewed'])->name('candidates.strategy-lab.mark-reviewed');
            Route::post('/candidates/{application}/strategy-lab/final-decision', [StrategyLabController::class, 'setFinalDecision'])->name('candidates.strategy-lab.final-decision');
            Route::post('/candidates/{application}/video-report/retry', [VideoInterviewController::class, 'retryUnifiedReport'])
                ->middleware('throttle:ai-endpoints')
                ->name('candidates.video-report.retry');
            Route::get('/interviews', [InterviewController::class, 'index'])->name('interviews.index');
            Route::get('/interviews/{interview}', [InterviewController::class, 'show'])->name('interviews.show');
            Route::post('/interviews/{interview}/feedback', [InterviewController::class, 'storeFeedback'])->name('interviews.feedback.store');
            Route::get('/interviews/{interview}/invite', [InterviewController::class, 'invite'])->name('interviews.invite');
            Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
            Route::get('/referrals/create', [ReferralController::class, 'create'])->name('referrals.create');
            Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
            Route::post('/referrals/{referral}/convert', [ReferralController::class, 'convert'])->name('referrals.convert');
            Route::get('/social-hub', [SocialHubController::class, 'index'])->name('social-hub.index');
            Route::post('/social-hub/posts', [SocialHubController::class, 'store'])->name('social-hub.posts.store');
            Route::post('/social-hub/posts/{post}/reactions', [SocialHubController::class, 'storeReaction'])->name('social-hub.reactions.store');
            Route::post('/social-hub/posts/{post}/poll-vote', [SocialHubController::class, 'storePollVote'])->name('social-hub.poll-votes.store');
            Route::post('/social-hub/posts/{post}/comments', [SocialHubController::class, 'storeComment'])->name('social-hub.comments.store');
            Route::get('/analytics', [EmployerBrandController::class, 'index'])->name('analytics.index');
            Route::post('/analytics/alerts/{brandAlert}/resolve', [EmployerBrandController::class, 'resolveAlert'])->name('analytics.alerts.resolve');
            Route::get('/analytics/fairness', [FairnessDashboardController::class, 'index'])->name('analytics.fairness');
            Route::post('/analytics/fairness/alerts/{biasAlert}/resolve', [FairnessDashboardController::class, 'resolveAlert'])
                ->name('analytics.fairness.alerts.resolve');
            Route::get('/configuration', [DashboardController::class, 'configuration'])->name('configuration.index');
            Route::post('/admin/integrations/linkedin/connect', [CompanyIntegrationController::class, 'redirectToLinkedIn'])
                ->name('admin.integrations.linkedin.connect');
            Route::get('/admin/integrations/linkedin/callback', [CompanyIntegrationController::class, 'handleLinkedInCallback'])
                ->name('admin.integrations.linkedin.callback');
            Route::post('/admin/integrations/linkedin/test', [CompanyIntegrationController::class, 'testLinkedInConnection'])
                ->name('admin.integrations.linkedin.test');
            Route::post('/admin/integrations/linkedin/partner-settings', [CompanyIntegrationController::class, 'saveLinkedInPartnerSettings'])
                ->name('admin.integrations.linkedin.partner-settings');
            Route::post('/admin/integrations/linkedin/disconnect', [CompanyIntegrationController::class, 'disconnectLinkedIn'])
                ->name('admin.integrations.linkedin.disconnect');
            Route::view('/status', 'status')->name('status');
            Route::view('/components-demo', 'components-demo')->name('components.demo');
        });
        Route::get('/exports/{export}/download', [ReportingExportController::class, 'download'])->name('exports.download');
        Route::middleware('can:access-admin-pages')->group(function (): void {
            Route::post('/admin/chat', [App\Http\Controllers\Admin\ChatbotController::class, 'handleChat'])->name('admin.chat');
            Route::get('/admin/recruitment-needs', [App\Http\Controllers\Admin\RecruitmentNeedController::class, 'index'])->name('admin.recruitment-needs.index');
            Route::post('/admin/recruitment-needs/import', [App\Http\Controllers\Admin\RecruitmentNeedController::class, 'importCsv'])->name('admin.recruitment-needs.import');
            Route::put('/admin/recruitment-needs/{id}/inline', [App\Http\Controllers\Admin\RecruitmentNeedController::class, 'updateInline'])->name('admin.recruitment-needs.updateInline');
            Route::get('/admin/jobs', [JobController::class, 'index'])->name('jobs.index');
            Route::post('/admin/jobs', [JobController::class, 'store'])->name('jobs.store');
            Route::get('/admin/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
            Route::patch('/admin/jobs/{job}', [JobController::class, 'updateCore'])->name('jobs.update');
            Route::post('/admin/jobs/{job}/blocks', [JobController::class, 'saveBlocks'])->name('jobs.blocks.save');
            Route::post('/admin/jobs/{job}/pipeline', [JobController::class, 'savePipeline'])->name('jobs.pipeline.save');
            Route::post('/admin/jobs/{job}/publish-toggle', [JobController::class, 'publishToggle'])->name('jobs.publish-toggle');
            Route::post('/admin/jobs/{job}/weighting', [JobController::class, 'saveWeighting'])->name('jobs.weighting.save');
            Route::post('/admin/jobs/{job}/persona/generate', [JobController::class, 'generatePersona'])
                ->middleware('throttle:ai-endpoints')
                ->name('jobs.persona.generate');
            Route::post('/admin/jobs/{job}/persona/refresh', [JobController::class, 'refreshPersona'])
                ->middleware('throttle:ai-endpoints')
                ->name('jobs.persona.refresh');
            Route::post('/admin/jobs/{job}/multiposting/{platform}/toggle', [JobMultipostingController::class, 'toggle'])->name('jobs.multiposting.toggle');
            Route::post('/admin/jobs/{job}/multiposting/bulk', [JobMultipostingController::class, 'bulk'])->name('jobs.multiposting.bulk');
            Route::post('/admin/jobs/{job}/multiposting/{platform}/generate', [JobMultipostingController::class, 'generate'])->name('jobs.multiposting.generate');
            Route::post('/admin/jobs/{job}/multiposting/{platform}/save-content', [JobMultipostingController::class, 'saveContent'])->name('jobs.multiposting.save-content');
            Route::post('/admin/jobs/{job}/multiposting/{platform}/publish', [JobMultipostingController::class, 'publish'])->name('jobs.multiposting.publish');
            Route::post('/admin/jobs/{job}/multiposting/{platform}/retry', [JobMultipostingController::class, 'retry'])->name('jobs.multiposting.retry');
            Route::get('/admin/video-configs', [VideoConfigController::class, 'index'])->name('admin.video-configs.index');
            Route::post('/admin/video-configs', [VideoConfigController::class, 'store'])->name('admin.video-configs.store');
            Route::patch('/admin/video-configs/{videoConfig}', [VideoConfigController::class, 'update'])->name('admin.video-configs.update');
            Route::get('/admin/email-templates', [EmailTemplateController::class, 'index'])->name('admin.email-templates.index');
            Route::post('/admin/email-templates', [EmailTemplateController::class, 'upsert'])->name('admin.email-templates.upsert');
            Route::post('/admin/email-templates/outbox/{emailOutboxLog}/retry', [EmailTemplateController::class, 'retryOutbox'])
                ->name('admin.email-templates.retry-outbox');
            Route::get('/admin/sjt-scenarios', [SjtScenarioController::class, 'index'])->name('admin.sjt-scenarios.index');
            Route::post('/admin/sjt-scenarios', [SjtScenarioController::class, 'store'])->name('admin.sjt-scenarios.store');
            Route::patch('/admin/sjt-scenarios/{sjtScenario}', [SjtScenarioController::class, 'update'])->name('admin.sjt-scenarios.update');
            Route::delete('/admin/sjt-scenarios/{sjtScenario}', [SjtScenarioController::class, 'destroy'])->name('admin.sjt-scenarios.destroy');
            Route::get('/admin/exports', [ExportHistoryController::class, 'index'])->name('admin.exports.index');
            Route::get('/admin/health-checklist', [HealthChecklistController::class, 'index'])->name('admin.health.index');
            Route::post('/admin/health-checklist/retention', [HealthChecklistController::class, 'updateRetention'])->name('admin.health.retention.update');
            Route::post('/admin/health-checklist/retention/prune', [HealthChecklistController::class, 'runRetentionPrune'])->name('admin.health.retention.prune');

            // ATS AI Routes
            Route::prefix('/admin/ats')->name('ats.')->group(function (): void {
                Route::get('/', [AtsController::class, 'index'])->name('dashboard');
                Route::get('/jobs/{job}/upload-cv', [AtsController::class, 'uploadCvForm'])->name('upload-cv');
                Route::post('/jobs/{job}/upload-cv', [AtsController::class, 'storeCv'])->name('store-cv');
                Route::get('/jobs/{job}/candidates', [AtsController::class, 'candidates'])->name('candidates');
                Route::get('/candidates/{application}', [AtsController::class, 'showCandidate'])->name('show-candidate');
            });

            // PsyTests routes
            Route::prefix('/admin/psy-tests')->name('admin.psy-tests.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\PsyTestController::class, 'index'])->name('index');
                Route::post('/generate', [\App\Http\Controllers\Admin\PsyTestController::class, 'generate'])->name('generate');
                Route::get('/{psyTest}', [\App\Http\Controllers\Admin\PsyTestController::class, 'show'])->name('show');
            });
        });
        Route::middleware('can:access-candidate-assessments')->group(function (): void {
            Route::get('/candidate/assessments/sjt', [CandidateAssessmentController::class, 'index'])->name('candidate.assessments.sjt');
            Route::post('/candidate/assessments/sjt/{application}/{scenario}/draft', [CandidateAssessmentController::class, 'saveDraft'])
                ->middleware('throttle:ai-endpoints')
                ->name('candidate.assessments.sjt.draft');
            Route::post('/candidate/assessments/sjt/{application}/{scenario}/submit', [CandidateAssessmentController::class, 'submit'])
                ->middleware('throttle:ai-endpoints')
                ->name('candidate.assessments.sjt.submit');
            Route::post('/candidate/assessments/sjt/responses/{sjtResponse}/retry', [CandidateAssessmentController::class, 'retryScoring'])
                ->middleware('throttle:ai-endpoints')
                ->name('candidate.assessments.sjt.retry');
        });
    });

    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::patch('/profile/zoom-link', [InterviewController::class, 'updateZoomLink'])->name('profile.zoom-link.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('/media/avatar/{profile}', [MediaController::class, 'avatar'])
        ->middleware('signed')
        ->name('media.avatar');
    Route::get('/media/candidate-document/{candidateDocument}', [MediaController::class, 'candidateDocument'])
        ->name('media.candidate-document');
    Route::get('/media/contract/{contract}', [MediaController::class, 'contract'])
        ->name('media.contract');
    Route::get('/media/onboarding-document/{onboardingDocument}', [MediaController::class, 'onboardingDocument'])
        ->name('media.onboarding-document');
    Route::get('/media/strategy-lab-brief/{strategyLabBrief}', [MediaController::class, 'strategyLabBrief'])
        ->name('media.strategy-lab-brief');
    Route::get('/media/strategy-lab-submission/{strategyLabSubmission}', [MediaController::class, 'strategyLabSubmission'])
        ->name('media.strategy-lab-submission');
    Route::get('/media/video-response/{videoInterviewResponse}', [MediaController::class, 'videoResponse'])
        ->name('media.video-response');

    Route::get('/admin/users', [UserManagementController::class, 'index'])
        ->middleware(['ensure.company.context', 'can:viewAny,App\\Models\\User'])
        ->name('admin.users.index');    Route::post('/admin/users', [UserManagementController::class, 'store'])
        ->middleware(['ensure.company.context', 'can:create,App\Models\User'])
        ->name('admin.users.store');    Route::patch('/admin/users/{user}/role', [UserManagementController::class, 'updateRole'])
        ->middleware(['ensure.company.context', 'can:updateRole,user'])
        ->name('admin.users.update-role');
    Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware(['ensure.company.context', 'can:delete,user'])
        ->name('admin.users.destroy');
    Route::get('/admin/ai-diagnostics', [AiDiagnosticsController::class, 'index'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.ai-diagnostics.index');
    Route::post('/admin/ai-diagnostics/{aiRequest}/retry', [AiDiagnosticsController::class, 'retry'])
        ->middleware(['verified', 'can:access-admin-pages', 'throttle:ai-endpoints'])
        ->name('admin.ai-diagnostics.retry');
    Route::get('/admin/departments', [DepartmentController::class, 'index'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.departments.index');
    Route::post('/admin/departments', [DepartmentController::class, 'store'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.departments.store');
    Route::patch('/admin/departments/{department}', [DepartmentController::class, 'update'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.departments.update');
    Route::delete('/admin/departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.departments.destroy');

    Route::get('/admin/company-values', [CompanyValueController::class, 'index'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.values.index');
    Route::post('/admin/company-values', [CompanyValueController::class, 'store'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.values.store');
    Route::patch('/admin/company-values/{companyValue}', [CompanyValueController::class, 'update'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.values.update');
    Route::delete('/admin/company-values/{companyValue}', [CompanyValueController::class, 'destroy'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.values.destroy');

    Route::get('/admin/faqs', [FaqItemController::class, 'index'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.faqs.index');
    Route::post('/admin/faqs', [FaqItemController::class, 'store'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.faqs.store');
    Route::patch('/admin/faqs/{faqItem}', [FaqItemController::class, 'update'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.faqs.update');
    Route::delete('/admin/faqs/{faqItem}', [FaqItemController::class, 'destroy'])
        ->middleware(['verified', 'can:access-admin-pages'])
        ->name('admin.faqs.destroy');

    Route::get('/platform/console', PlatformConsoleController::class)
        ->middleware('can:access-platform-console')
        ->name('platform.console');
    Route::get('/platform/company-approvals', [CompanyApprovalController::class, 'index'])
        ->middleware('can:access-platform-console')
        ->name('platform.company-approvals');
    Route::get('/platform/company-approvals/{company}', [CompanyApprovalController::class, 'show'])
        ->middleware('can:access-platform-console')
        ->name('platform.company-approvals.show');
    Route::post('/platform/company-approvals/{company}/approve', [CompanyApprovalController::class, 'approve'])
        ->middleware('can:access-platform-console')
        ->name('platform.company-approvals.approve');
    Route::post('/platform/company-approvals/{company}/reject', [CompanyApprovalController::class, 'reject'])
        ->middleware('can:access-platform-console')
        ->name('platform.company-approvals.reject');

    Route::get('/platform/ai-diagnostics', [PlatformAiDiagnosticsController::class, 'index'])
        ->middleware('can:access-platform-console')
        ->name('platform.ai-diagnostics');
    Route::post('/platform/ai-diagnostics', [PlatformAiDiagnosticsController::class, 'store'])
        ->middleware(['can:access-platform-console', 'throttle:ai-endpoints'])
        ->name('platform.ai-diagnostics.store');
    Route::get('/platform/ai-diagnostics/{aiRequestId}', [PlatformAiDiagnosticsController::class, 'show'])
        ->middleware('can:access-platform-console')
        ->name('platform.ai-diagnostics.show');
    Route::post('/platform/ai-diagnostics/{aiRequestId}/retry', [PlatformAiDiagnosticsController::class, 'retry'])
        ->middleware(['can:access-platform-console', 'throttle:ai-endpoints'])
        ->name('platform.ai-diagnostics.retry');

    Route::prefix('superadmin')->middleware('can:access-platform-console')->group(function (): void {
        Route::get('/contact-inquiries', [PlatformContactInquiryController::class, 'index'])
            ->name('superadmin.contact-inquiries.index');
        Route::get('/contact-inquiries/{contactInquiry}', [PlatformContactInquiryController::class, 'show'])
            ->name('superadmin.contact-inquiries.show');
        Route::patch('/contact-inquiries/{contactInquiry}', [PlatformContactInquiryController::class, 'update'])
            ->name('superadmin.contact-inquiries.update');
    });
});
