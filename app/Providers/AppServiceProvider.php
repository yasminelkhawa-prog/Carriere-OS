<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\CandidateDocument;
use App\Models\CandidateSurvey;
use App\Models\InterviewFeedback;
use App\Models\Job;
use App\Models\ReverseFeedback;
use App\Observers\ApplicationObserver;
use App\Observers\ApplicationScoringObserver;
use App\Observers\CandidateDocumentObserver;
use App\Observers\CandidateSurveyObserver;
use App\Observers\InterviewFeedbackObserver;
use App\Observers\JobObserver;
use App\Observers\ReverseFeedbackObserver;
use App\Listeners\LogEmailSent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', static function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(8)->by($request->ip().'|'.$email),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-recovery', static function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(5)->by($request->ip().'|'.$email),
                Limit::perMinute(12)->by($request->ip()),
            ];
        });

        RateLimiter::for('ai-endpoints', static function (Request $request): array {
            $actorKey = (string) optional($request->user())->id;
            if ($actorKey === '') {
                $actorKey = $request->ip();
            }

            return [
                Limit::perMinute(20)->by('ai-user:'.$actorKey),
                Limit::perMinute(60)->by('ai-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('public-contact', static function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(6)->by('contact-ip:'.$request->ip()),
                Limit::perHour(30)->by('contact-email:'.$email),
            ];
        });

        ReverseFeedback::observe(ReverseFeedbackObserver::class);
        InterviewFeedback::observe(InterviewFeedbackObserver::class);
        CandidateSurvey::observe(CandidateSurveyObserver::class);
        CandidateDocument::observe(CandidateDocumentObserver::class);
        Application::observe(ApplicationObserver::class);
        ApplicationScoring::observe(ApplicationScoringObserver::class);
        Job::observe(JobObserver::class);

        Event::listen(MessageSent::class, LogEmailSent::class);
    }
}
