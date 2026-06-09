<?php
    $authUser = auth()->user();
    $companyOptions = collect();
    $activeCompanyId = session('active_company_id');
    $activeCompanyName = null;
    $activeCompanySlug = null;
    $isCandidateRole = false;

    if ($authUser && ! $authUser->isSuperadmin()) {
        $companyOptions = $authUser->memberships()
            ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
            ->with('company:id,name,status')
            ->get()
            ->filter(fn ($membership) => $membership->company && $membership->company->status === \App\Models\Company::STATUS_ACTIVE);

        if (is_string($activeCompanyId) && $activeCompanyId !== '') {
            $activeCompanyName = \App\Models\Company::query()
                ->whereKey($activeCompanyId)
                ->value('name');
            $activeCompanySlug = \App\Models\Company::query()
                ->whereKey($activeCompanyId)
                ->value('slug');

            $activeRole = $authUser->memberships()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
                ->value('company_role');
            $isCandidateRole = $activeRole === \App\Models\CompanyMembership::ROLE_CANDIDATE;
        }
    }

    $searchAction = $isCandidateRole
        ? ($activeCompanySlug ? route('candidate.faq', ['company' => $activeCompanySlug]) : route('profile.edit'))
        : route('candidates.index');
    $searchPlaceholder = $isCandidateRole
        ? __('ui.topbar.candidate_search_placeholder')
        : __('ui.topbar.search_placeholder');
    $currentQuery = (string) request('q', '');
?>

<header data-app-top-bar class="z-20 px-4 pt-4 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-[1480px] rounded-3xl border border-white/80 bg-white/85 p-3 shadow-[0_22px_44px_-30px_rgba(30,41,59,0.45)] backdrop-blur-2xl">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 transition-weightless hover:bg-slate-50"
                @click="toggleLeftSidebar()"
            >
                <svg class="size-4 text-slate-700" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-linecap="round" />
                </svg>
                <span class="hidden sm:inline"><?php echo e(__('ui.topbar.left_toggle')); ?></span>
            </button>

            <form method="GET" action="<?php echo e($searchAction); ?>" class="relative min-w-[200px] flex-1">
                <?php if(auth()->user()?->isSuperadmin() && request('company_id')): ?>
                    <input type="hidden" name="company_id" value="<?php echo e(request('company_id')); ?>">
                <?php endif; ?>
                <input
                    type="search"
                    name="q"
                    value="<?php echo e($currentQuery); ?>"
                    placeholder="<?php echo e($searchPlaceholder); ?>"
                    class="w-full rounded-xl border border-slate-200 bg-white/90 py-2.5 pl-10 pr-4 text-sm text-slate-700 focus:border-primary-400 focus:ring-primary-300"
                >
                <svg class="pointer-events-none absolute left-3 top-3 size-4 text-slate-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M9 15a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0 0 6 2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </form>

            <div class="hidden lg:flex items-center gap-2">
                <span class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                    <?php echo e(now()->format('D, M j')); ?>

                </span>
                <span class="rounded-xl border border-success-200 bg-success-50 px-3 py-2 text-xs font-semibold text-success-800">
                    <?php echo e($authUser?->isSuperadmin() ? __('ui.topbar.platform_console') : ($activeCompanyName ?: __('ui.dashboard.unknown'))); ?>

                </span>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition-weightless"
                x-bind:class="rightSidebarEnabled ? 'border-primary-300 bg-primary-50 text-primary-800 hover:bg-primary-100' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'"
                @click="toggleRightSidebar()"
                :aria-disabled="(!rightSidebarEnabled).toString()"
            >
                <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 4h12v12H4zM10 4v12" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="hidden sm:inline"><?php echo e(__('ui.topbar.right_toggle')); ?></span>
            </button>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <?php if(auth()->guard()->check()): ?>
                    <?php if($authUser->isSuperadmin()): ?>
                        <a href="<?php echo e(route('platform.console')); ?>" class="rounded-xl border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 transition-weightless hover:bg-aura-50"><?php echo e(__('ui.topbar.platform_console')); ?></a>
                    <?php elseif($companyOptions->count() > 1): ?>
                        <form method="POST" action="<?php echo e(route('company.switch')); ?>" class="min-w-[220px]">
                            <?php echo csrf_field(); ?>
                            <select name="company_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm" onchange="this.form.submit()">
                                <?php $__currentLoopData = $companyOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membership): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($membership->company_id); ?>" <?php if((string) session('active_company_id') === (string) $membership->company_id): echo 'selected'; endif; ?>>
                                        <?php echo e($membership->company->name); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </form>
                    <?php endif; ?>


                <?php endif; ?>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="<?php echo e(route('locale.switch', ['locale' => 'en'])); ?>" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50"><?php echo e(__('ui.locale.en')); ?></a>
                <a href="<?php echo e(route('locale.switch', ['locale' => 'fr'])); ?>" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50"><?php echo e(__('ui.locale.fr')); ?></a>
                <?php if(auth()->guard()->guest()): ?>
                    <a href="<?php echo e(route('login')); ?>" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50"><?php echo e(__('auth.login_title')); ?></a>
                <?php else: ?>
                    <span class="text-xs font-medium text-slate-700 sm:text-sm"><?php echo e($authUser->profile?->full_name ?: $authUser->email); ?></span>
                    <form method="POST" action="<?php echo e(route('logout')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50"><?php echo e(__('auth.logout')); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/top-bar.blade.php ENDPATH**/ ?>