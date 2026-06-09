@extends('layouts.public')

@section('title', __('public_site.about.meta_title').' | '.config('app.name'))

@section('content')
    <section class="relative overflow-hidden rounded-3xl border border-white/70 bg-white/85 p-8 shadow-sm sm:p-10">
        <div class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-aura-300/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-20 bottom-0 h-64 w-64 rounded-full bg-success-300/30 blur-3xl"></div>

        <div class="relative max-w-3xl">
            <p class="text-xs uppercase tracking-[0.22em] text-aura-700/90">About numa</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                Building careers with clarity, speed, and confidence.
            </h1>
            <p class="mt-4 text-base leading-relaxed text-slate-700 sm:text-lg">
                numa is a modern hiring platform designed for ambitious teams and thoughtful candidates.
                We help organizations run structured, fair, and human-centric recruitment from job launch to final offer.
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('public.jobs.index') }}" class="rounded-xl bg-success-600 px-5 py-3 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    View Open Positions
                </a>
                <a href="{{ route('public.contact') }}" class="rounded-xl border border-aura-300/55 bg-white px-5 py-3 text-sm font-semibold text-aura-900 transition-weightless hover:bg-aura-50">
                    Contact Our Team
                </a>
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <article class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm sm:p-7">
            <h2 class="text-2xl font-semibold text-slate-900">Who we are</h2>
            <div class="mt-4 space-y-4 text-sm leading-relaxed text-slate-700 sm:text-base">
                <p>
                    We built numa to remove friction from hiring for both sides of the table.
                    Recruiters need dependable workflows and actionable visibility.
                    Candidates deserve a transparent experience with timely communication and meaningful feedback.
                </p>
                <p>
                    Our platform brings sourcing, screening, collaboration, scheduling, evaluations, and reporting into one
                    connected experience so growing organizations can hire better without compromising quality.
                </p>
            </div>
        </article>

        <article class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm sm:p-7">
            <h2 class="text-2xl font-semibold text-slate-900">Why this matters</h2>
            <div class="mt-4 space-y-4 text-sm leading-relaxed text-slate-700 sm:text-base">
                <p>
                    Hiring is one of the highest-impact decisions a company makes.
                    Better process quality leads to better teams, faster execution, and stronger candidate trust.
                </p>
                <p>
                    numa combines operational rigor with candidate empathy so every hiring decision is grounded in
                    data, fairness principles, and real business needs.
                </p>
            </div>
        </article>
    </section>

    <section class="mt-8 grid gap-4 md:grid-cols-2">
        <article class="rounded-2xl border border-aura-200/60 bg-aura-50/55 p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-aura-800">Mission</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-700">
                To streamline recruitment with transparency, speed, and fairness for every candidate and every hiring team.
            </p>
        </article>
        <article class="rounded-2xl border border-primary-200/60 bg-primary-50/55 p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-primary-900">Vision</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-700">
                To become the trusted digital hiring operating system for organizations building high-performing teams.
            </p>
        </article>
    </section>

    <section class="mt-8 rounded-3xl border border-white/70 bg-white/85 p-6 shadow-sm sm:p-8">
        <h2 class="text-2xl font-semibold text-slate-900">What makes numa different</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 class="text-sm font-semibold text-slate-900">Structured hiring workflows</h3>
                <p class="mt-2 text-sm text-slate-700">From job design to offer stage, every step is organized, auditable, and team-friendly.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 class="text-sm font-semibold text-slate-900">Candidate-first communication</h3>
                <p class="mt-2 text-sm text-slate-700">Clear updates, consistent touchpoints, and an experience that respects candidate time.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 class="text-sm font-semibold text-slate-900">Fairness-aware decision support</h3>
                <p class="mt-2 text-sm text-slate-700">Bias-reduction features and aggregated auditing insights for responsible hiring practices.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 class="text-sm font-semibold text-slate-900">Operational clarity at scale</h3>
                <p class="mt-2 text-sm text-slate-700">Dashboards, exports, and controls that keep recruiting teams aligned as they grow.</p>
            </article>
        </div>
    </section>

    <section class="mt-8 rounded-3xl border border-aura-300/40 bg-gradient-to-r from-aura-600 to-aura-700 p-7 text-white shadow-sm sm:p-9">
        <h2 class="text-3xl font-semibold tracking-tight">Ready to discover your next opportunity?</h2>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-aura-100 sm:text-base">
            Explore open roles, learn more about hiring teams, and apply through a process built for speed and clarity.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('public.jobs.index') }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-aura-800 transition-weightless hover:bg-aura-50">
                Browse Jobs
            </a>
            <a href="{{ route('login') }}" class="rounded-xl border border-white/45 px-5 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-white/10">
                Login to Workspace
            </a>
        </div>
    </section>
@endsection

