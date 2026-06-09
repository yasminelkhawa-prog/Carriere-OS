<?php
    $today = now();
    $monthStart = $today->copy()->startOfMonth();
    $monthEnd = $today->copy()->endOfMonth();
    $calendarStart = $monthStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $calendarEnd = $monthEnd->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

    $calendarDays = collect();
    for ($cursor = $calendarStart->copy(); $cursor->lte($calendarEnd); $cursor->addDay()) {
        $calendarDays->push($cursor->copy());
    }

    $authUser = auth()->user();
    $profile = $authUser?->profile;
    $displayName = $profile?->full_name ?? $authUser?->email ?? __('ui.right_sidebar.profile_default_name');
    $activeCompanyId = session('active_company_id');
    $activeRole = null;
    if (
        $authUser
        && ! $authUser->isSuperadmin()
        && is_string($activeCompanyId)
        && $activeCompanyId !== ''
    ) {
        $activeRole = $authUser->memberships()
            ->where('company_id', $activeCompanyId)
            ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
            ->value('company_role');
    }

    $roleLabel = $authUser?->isSuperadmin()
        ? __('ui.nav.platform_console')
        : __('admin.roles.'.((string) ($activeRole ?: \App\Models\CompanyMembership::ROLE_CANDIDATE)));
    $initials = collect(explode(' ', trim((string) $displayName)))
        ->filter()
        ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $avatarUrl = $profile?->avatar_url
        ? \Illuminate\Support\Facades\URL::temporarySignedRoute('media.avatar', now()->addMinutes(10), ['profile' => $profile->getKey()])
        : null;

    $reminders = collect([
        [
            'title' => __('ui.right_sidebar.reminders.interviews_title'),
            'meta' => __('ui.right_sidebar.reminders.interviews_meta'),
            'level' => 'urgent',
        ],
        [
            'title' => __('ui.right_sidebar.reminders.pipeline_title'),
            'meta' => __('ui.right_sidebar.reminders.pipeline_meta'),
            'level' => 'warning',
        ],
        [
            'title' => __('ui.right_sidebar.reminders.outreach_title'),
            'meta' => __('ui.right_sidebar.reminders.outreach_meta'),
            'level' => 'info',
        ],
    ]);

    if (session('error')) {
        $reminders->prepend([
            'title' => __('ui.right_sidebar.reminders.system_alert'),
            'meta' => (string) session('error'),
            'level' => 'urgent',
        ]);
    }

    if (session('status')) {
        $reminders->prepend([
            'title' => __('ui.right_sidebar.reminders.system_update'),
            'meta' => (string) session('status'),
            'level' => 'success',
        ]);
    }

    $levelClasses = [
        'urgent' => 'border-danger-300/70 bg-danger-50 text-danger-900',
        'warning' => 'border-amber-300/70 bg-amber-50 text-amber-900',
        'info' => 'border-primary-300/70 bg-primary-50 text-primary-900',
        'success' => 'border-success-300/70 bg-success-50 text-success-900',
    ];
?>

<section class="space-y-4">
    <div class="rounded-3xl border border-white/80 bg-white/80 p-4 shadow-[0_24px_52px_-36px_rgba(30,41,59,0.45)] backdrop-blur-2xl">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500"><?php echo e(__('ui.right_sidebar.profile_label')); ?></p>
        <div class="mt-3 flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-3">
            <?php if($avatarUrl): ?>
                <img src="<?php echo e($avatarUrl); ?>" alt="<?php echo e($displayName); ?>" class="size-11 rounded-full object-cover">
            <?php else: ?>
                <div class="flex size-11 items-center justify-center rounded-full bg-aura-100 text-sm font-semibold text-aura-800"><?php echo e($initials !== '' ? $initials : 'N'); ?></div>
            <?php endif; ?>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900"><?php echo e($displayName); ?></p>
                <p class="text-xs text-slate-600"><?php echo e($roleLabel); ?></p>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-white/80 bg-white/80 p-4 shadow-[0_24px_52px_-36px_rgba(30,41,59,0.45)] backdrop-blur-2xl">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900"><?php echo e(__('ui.right_sidebar.calendar_title')); ?></h3>
            <span class="rounded-full bg-success-100 px-2 py-0.5 text-[11px] font-semibold text-success-800"><?php echo e(__('ui.right_sidebar.today_chip')); ?></span>
        </div>
        <p class="mt-1 text-xs text-slate-600"><?php echo e($today->format('F Y')); ?></p>

        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px]">
            <?php $__currentLoopData = [__('ui.right_sidebar.weekdays.mon'), __('ui.right_sidebar.weekdays.tue'), __('ui.right_sidebar.weekdays.wed'), __('ui.right_sidebar.weekdays.thu'), __('ui.right_sidebar.weekdays.fri'), __('ui.right_sidebar.weekdays.sat'), __('ui.right_sidebar.weekdays.sun')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $weekdayLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="font-semibold uppercase tracking-wide text-slate-500"><?php echo e($weekdayLabel); ?></span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="mt-2 grid grid-cols-7 gap-1">
            <?php $__currentLoopData = $calendarDays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $isCurrentMonth = $day->month === $today->month;
                    $isToday = $day->isSameDay($today);
                ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'flex h-8 items-center justify-center rounded-lg text-xs font-medium',
                    'bg-danger-500 text-white shadow-sm' => $isToday,
                    'bg-white text-slate-800' => ! $isToday && $isCurrentMonth,
                    'text-slate-400' => ! $isCurrentMonth,
                ]); ?>">
                    <?php echo e($day->day); ?>

                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <div class="rounded-3xl border border-white/80 bg-white/80 p-4 shadow-[0_24px_52px_-36px_rgba(30,41,59,0.45)] backdrop-blur-2xl">
        <h3 class="text-sm font-semibold text-slate-900"><?php echo e(__('ui.right_sidebar.notifications_title')); ?></h3>
        <ul class="mt-3 space-y-2.5">
            <?php $__currentLoopData = $reminders->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reminder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $level = (string) ($reminder['level'] ?? 'info');
                ?>
                <li class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'rounded-2xl border p-2.5 text-xs',
                    $levelClasses[$level] ?? $levelClasses['info'],
                ]); ?>">
                    <p class="font-semibold"><?php echo e((string) $reminder['title']); ?></p>
                    <p class="mt-0.5 opacity-90"><?php echo e((string) $reminder['meta']); ?></p>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
</section>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/right-sidebar.blade.php ENDPATH**/ ?>