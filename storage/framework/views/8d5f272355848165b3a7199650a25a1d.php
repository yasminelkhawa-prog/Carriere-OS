<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => null,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'title' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <title><?php echo e($title ?? config('app.name')); ?></title>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    </head>
    <body class="aura-background min-h-full" data-select2-warning="<?php echo e(__('ui.toasts.select2_fallback')); ?>">
        <div
            class="min-h-screen lg:flex"
            x-data="{
                sidebarOpen: false,
                leftSidebarCollapsed: false,
                leftSidebarWidthPx: 320,
                leftSidebarMinWidthPx: 240,
                leftSidebarMaxWidthPx: 520,
                leftSidebarCollapsedWidthPx: 0,
                leftSidebarResizing: false,
                rightSidebarOpen: true,
                rightSidebarEnabled: <?php echo \Illuminate\Support\Js::from(request()->routeIs('home'))->toHtml() ?>,
                isXlViewport: false,
                toasts: [],
                clampLeftSidebarWidth(value) {
                    const numeric = Number(value);
                    if (Number.isNaN(numeric)) {
                        return this.leftSidebarWidthPx;
                    }
                    return Math.min(this.leftSidebarMaxWidthPx, Math.max(this.leftSidebarMinWidthPx, Math.round(numeric)));
                },
                leftSidebarWidthStyle() {
                    const width = this.leftSidebarCollapsed ? this.leftSidebarCollapsedWidthPx : this.leftSidebarWidthPx;
                    return `width: ${width}px;`;
                },
                leftSidebarMarginStyle() {
                    if (!this.isDesktop()) {
                        return 'margin-left: 0px;';
                    }
                    const margin = this.leftSidebarCollapsed ? this.leftSidebarCollapsedWidthPx : this.leftSidebarWidthPx;
                    return `margin-left: ${margin}px;`;
                },
                syncViewportFlags() {
                    this.isXlViewport = window.matchMedia('(min-width: 1280px)').matches;
                },
                initLayoutState() {
                    this.syncViewportFlags();

                    try {
                        const savedLeftSidebar = window.localStorage.getItem('numa:left-sidebar-collapsed');
                        const savedLeftSidebarWidth = window.localStorage.getItem('numa:left-sidebar-width');
                        const savedRightSidebar = window.localStorage.getItem('numa:right-sidebar-open');

                        this.leftSidebarCollapsed = savedLeftSidebar === '1';
                        if (savedLeftSidebarWidth !== null && savedLeftSidebarWidth !== '') {
                            this.leftSidebarWidthPx = this.clampLeftSidebarWidth(savedLeftSidebarWidth);
                        }
                        this.rightSidebarOpen = savedRightSidebar === null ? true : savedRightSidebar === '1';
                    } catch (error) {
                        this.leftSidebarCollapsed = false;
                        this.leftSidebarWidthPx = 320;
                        this.rightSidebarOpen = true;
                    }

                    if (!this.rightSidebarEnabled) {
                        this.rightSidebarOpen = false;
                    }
                },
                persistLayoutState() {
                    try {
                        window.localStorage.setItem('numa:left-sidebar-collapsed', this.leftSidebarCollapsed ? '1' : '0');
                        window.localStorage.setItem('numa:left-sidebar-width', String(this.leftSidebarWidthPx));
                        window.localStorage.setItem('numa:right-sidebar-open', this.rightSidebarOpen ? '1' : '0');
                    } catch (error) {}
                },
                isDesktop() {
                    return window.matchMedia('(min-width: 1024px)').matches;
                },
                startLeftSidebarResize(event) {
                    if (!this.isDesktop() || this.leftSidebarCollapsed) {
                        return;
                    }
                    this.leftSidebarResizing = true;
                    document.body.style.cursor = 'col-resize';
                    document.body.classList.add('select-none');
                    this.onLeftSidebarResizeMove(event);
                },
                onLeftSidebarResizeMove(event) {
                    if (!this.leftSidebarResizing) {
                        return;
                    }
                    let clientX = null;
                    if (event && event.touches && event.touches.length > 0) {
                        clientX = event.touches[0].clientX;
                        event.preventDefault();
                    } else if (event && typeof event.clientX === 'number') {
                        clientX = event.clientX;
                    }
                    if (clientX === null) {
                        return;
                    }
                    this.leftSidebarWidthPx = this.clampLeftSidebarWidth(clientX);
                },
                stopLeftSidebarResize() {
                    if (!this.leftSidebarResizing) {
                        return;
                    }
                    this.leftSidebarResizing = false;
                    document.body.style.cursor = '';
                    document.body.classList.remove('select-none');
                    this.persistLayoutState();
                },
                toggleLeftSidebar() {
                    if (this.isDesktop()) {
                        if (this.leftSidebarResizing) {
                            this.stopLeftSidebarResize();
                        }
                        this.leftSidebarCollapsed = !this.leftSidebarCollapsed;
                        this.persistLayoutState();
                        return;
                    }

                    this.rightSidebarOpen = false;
                    this.sidebarOpen = !this.sidebarOpen;
                },
                toggleRightSidebar() {
                    if (!this.rightSidebarEnabled) {
                        return;
                    }

                    this.sidebarOpen = false;
                    this.rightSidebarOpen = !this.rightSidebarOpen;
                    this.persistLayoutState();
                },
                addToast(detail) {
                    const id = Date.now() + Math.random();
                    this.toasts.push({
                        id,
                        message: detail?.message ?? '',
                        type: detail?.type ?? 'warning',
                    });
                    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3800);
                }
            }"
            x-init="
                initLayoutState();
                window.addEventListener('app:toast', (event) => addToast(event.detail));
                window.addEventListener('mousemove', (event) => onLeftSidebarResizeMove(event));
                window.addEventListener('mouseup', () => stopLeftSidebarResize());
                window.addEventListener('touchmove', (event) => onLeftSidebarResizeMove(event), { passive: false });
                window.addEventListener('touchend', () => stopLeftSidebarResize());
                window.addEventListener('blur', () => stopLeftSidebarResize());
                window.addEventListener('resize', () => {
                    syncViewportFlags();
                    if (isDesktop()) {
                        sidebarOpen = false;
                    } else {
                        stopLeftSidebarResize();
                    }
                });
            "
        >
            <?php if (isset($component)) { $__componentOriginala84898f20479e38f2bc0cbb2808b7dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala84898f20479e38f2bc0cbb2808b7dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sidebar-nav','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sidebar-nav'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala84898f20479e38f2bc0cbb2808b7dee)): ?>
<?php $attributes = $__attributesOriginala84898f20479e38f2bc0cbb2808b7dee; ?>
<?php unset($__attributesOriginala84898f20479e38f2bc0cbb2808b7dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala84898f20479e38f2bc0cbb2808b7dee)): ?>
<?php $component = $__componentOriginala84898f20479e38f2bc0cbb2808b7dee; ?>
<?php unset($__componentOriginala84898f20479e38f2bc0cbb2808b7dee); ?>
<?php endif; ?>
            <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-aura-900/20 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>
            <aside x-cloak x-show="sidebarOpen" class="fixed inset-y-0 left-0 z-50 w-72 border-r border-white/70 bg-white/85 p-5 backdrop-blur-2xl lg:hidden">
                <button type="button" class="mb-5 rounded-xl border border-aura-300/40 bg-white/80 px-3 py-1.5 text-xs uppercase tracking-wider text-slate-900 transition-weightless hover:bg-white" @click="sidebarOpen = false">
                    <?php echo e(__('ui.nav.close')); ?>

                </button>
                <a
                    href="<?php echo e(route('auth.company.dispatch')); ?>"
                    class="mb-4 inline-flex items-center rounded-2xl border border-slate-200/70 bg-white/85 px-3 py-2 transition-weightless hover:bg-white"
                    aria-label="numa portal home"
                >
                    <img
                        src="<?php echo e(asset('images/numa-logo-clean.png')); ?>"
                        alt="<?php echo e(config('app.name')); ?> logo"
                        class="h-9 w-auto object-contain"
                        loading="eager"
                        decoding="async"
                    >
                </a>
                <nav class="space-y-1 text-sm">
                    <?php if(auth()->guard()->check()): ?>
                        <?php
                            $mobileActiveCompanyId = session('active_company_id');
                            $mobileActiveCompanySlug = null;
                            $mobileActiveRole = null;
                            $mobileIsCandidateRole = false;

                            if (! auth()->user()->isSuperadmin() && is_string($mobileActiveCompanyId) && $mobileActiveCompanyId !== '') {
                                $mobileActiveCompanySlug = \App\Models\Company::query()
                                    ->whereKey($mobileActiveCompanyId)
                                    ->value('slug');

                                $mobileActiveRole = auth()->user()->memberships()
                                    ->where('company_id', $mobileActiveCompanyId)
                                    ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
                                    ->value('company_role');

                                $mobileIsCandidateRole = $mobileActiveRole === \App\Models\CompanyMembership::ROLE_CANDIDATE;
                            }

                            $mobileMenuNotificationDots = collect(session('sidebar_notification_dots', []));
                            $mobileOverviewDot = (bool) $mobileMenuNotificationDots->get('overview', session()->has('status') || session()->has('error'));

                            $candidateDashboardRoutePatterns = [
                                'candidate.portal',
                            ];
                            $candidateApplicationsRoutePatterns = [
                                'candidate.applications',
                                'candidate.guide.ask',
                                'candidate.reverse-feedback.*',
                                'candidate.contract.*',
                                'candidate.onboarding-documents.*',
                                'candidate.onboarding-tasks.*',
                                'candidate.strategy-lab.*',
                            ];
                            $candidateUpdatesRoutePatterns = [
                                'candidate.updates',
                            ];
                            $candidateFaqRoutePatterns = [
                                'candidate.faq',
                            ];
                            $candidateSocialRoutePatterns = [
                                'candidate.social-hub.*',
                            ];
                            $candidateAccountRoutePatterns = [
                                'candidate.account',
                            ];
                            $candidateAssessmentsRoutePatterns = [
                                'candidate.assessments.*',
                                'candidate.video-stories*',
                            ];
                        ?>
                        <?php if(auth()->user()->isSuperadmin()): ?>
                            <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('platform.console'),'label' => __('ui.nav.platform_console'),'icon' => 'platform_console','compact' => true,'active' => request()->routeIs('platform.console'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('platform.console')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.platform_console')),'icon' => 'platform_console','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('platform.console')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('platform.company-approvals'),'label' => __('ui.nav.company_approvals'),'icon' => 'company_approvals','compact' => true,'active' => request()->routeIs('platform.company-approvals*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('platform.company-approvals')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.company_approvals')),'icon' => 'company_approvals','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('platform.company-approvals*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('superadmin.contact-inquiries.index'),'label' => __('ui.nav.contact_inquiries'),'icon' => 'contact_inquiries','compact' => true,'active' => request()->routeIs('superadmin.contact-inquiries*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('superadmin.contact-inquiries.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.contact_inquiries')),'icon' => 'contact_inquiries','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('superadmin.contact-inquiries*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('platform.ai-diagnostics'),'label' => __('ui.nav.ai_diagnostics'),'icon' => 'ai_diagnostics','compact' => true,'active' => request()->routeIs('platform.ai-diagnostics*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('platform.ai-diagnostics')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.ai_diagnostics')),'icon' => 'ai_diagnostics','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('platform.ai-diagnostics*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                        <?php else: ?>
                            <?php if($mobileIsCandidateRole): ?>
                                <?php if($mobileActiveCompanySlug): ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.portal', ['company' => $mobileActiveCompanySlug]),'label' => __('ui.nav.candidate_dashboard'),'icon' => 'overview','compact' => true,'active' => request()->routeIs(...$candidateDashboardRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.portal', ['company' => $mobileActiveCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_dashboard')),'icon' => 'overview','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateDashboardRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.applications', ['company' => $mobileActiveCompanySlug]),'label' => __('ui.nav.candidate_applications'),'icon' => 'candidates','compact' => true,'active' => request()->routeIs(...$candidateApplicationsRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.applications', ['company' => $mobileActiveCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_applications')),'icon' => 'candidates','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateApplicationsRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.updates', ['company' => $mobileActiveCompanySlug]),'label' => __('ui.nav.candidate_updates'),'icon' => 'contact_inquiries','compact' => true,'active' => request()->routeIs(...$candidateUpdatesRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.updates', ['company' => $mobileActiveCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_updates')),'icon' => 'contact_inquiries','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateUpdatesRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.faq', ['company' => $mobileActiveCompanySlug]),'label' => __('ui.nav.faqs'),'icon' => 'faqs','compact' => true,'active' => request()->routeIs(...$candidateFaqRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.faq', ['company' => $mobileActiveCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.faqs')),'icon' => 'faqs','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateFaqRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.social-hub.index', ['company' => $mobileActiveCompanySlug]),'label' => __('ui.nav.social_hub'),'icon' => 'social_hub','compact' => true,'active' => request()->routeIs(...$candidateSocialRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.social-hub.index', ['company' => $mobileActiveCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.social_hub')),'icon' => 'social_hub','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateSocialRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php endif; ?>
                                <?php if(auth()->user()->can('access-candidate-assessments')): ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.assessments.sjt'),'label' => __('ui.nav.assessments'),'icon' => 'assessments','compact' => true,'active' => request()->routeIs(...$candidateAssessmentsRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.assessments.sjt')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.assessments')),'icon' => 'assessments','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateAssessmentsRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php endif; ?>
                                <?php if($mobileActiveCompanySlug): ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.account', ['company' => $mobileActiveCompanySlug]),'label' => __('ui.nav.candidate_account'),'icon' => 'profile','compact' => true,'active' => request()->routeIs(...$candidateAccountRoutePatterns),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.account', ['company' => $mobileActiveCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_account')),'icon' => 'profile','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateAccountRoutePatterns)),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('home'),'label' => __('ui.nav.overview'),'icon' => 'overview','compact' => true,'active' => request()->routeIs('home'),'dot' => $mobileOverviewDot,'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('home')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.overview')),'icon' => 'overview','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('home')),'dot' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($mobileOverviewDot),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('access-admin-pages')): ?>
                                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('jobs.index'),'label' => __('ui.nav.jobs'),'icon' => 'jobs','compact' => true,'active' => request()->routeIs('jobs.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('jobs.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.jobs')),'icon' => 'jobs','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('jobs.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidates.index'),'label' => __('ui.nav.candidates'),'icon' => 'candidates','compact' => true,'active' => request()->routeIs('candidates.index'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidates.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidates')),'icon' => 'candidates','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('candidates.index')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidates.kanban'),'label' => __('ui.nav.candidates_kanban'),'icon' => 'candidates_kanban','compact' => true,'active' => request()->routeIs('candidates.kanban*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidates.kanban')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidates_kanban')),'icon' => 'candidates_kanban','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('candidates.kanban*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('interviews.index'),'label' => __('ui.nav.interviews'),'icon' => 'interviews','compact' => true,'active' => request()->routeIs('interviews.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('interviews.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.interviews')),'icon' => 'interviews','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('interviews.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('referrals.index'),'label' => __('ui.nav.referrals'),'icon' => 'referrals','compact' => true,'active' => request()->routeIs('referrals.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('referrals.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.referrals')),'icon' => 'referrals','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('referrals.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('social-hub.index'),'label' => __('ui.nav.social_hub'),'icon' => 'social_hub','compact' => true,'active' => request()->routeIs('social-hub.*', 'candidate.social-hub.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('social-hub.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.social_hub')),'icon' => 'social_hub','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('social-hub.*', 'candidate.social-hub.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('analytics.index'),'label' => __('ui.nav.analytics'),'icon' => 'analytics','compact' => true,'active' => request()->routeIs('analytics.index', 'analytics.alerts.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('analytics.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.analytics')),'icon' => 'analytics','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('analytics.index', 'analytics.alerts.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('analytics.fairness'),'label' => __('ui.nav.fairness'),'icon' => 'fairness','compact' => true,'active' => request()->routeIs('analytics.fairness*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('analytics.fairness')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.fairness')),'icon' => 'fairness','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('analytics.fairness*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('configuration.index'),'label' => __('ui.nav.configuration'),'icon' => 'configuration','compact' => true,'active' => request()->routeIs('configuration.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('configuration.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.configuration')),'icon' => 'configuration','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('configuration.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('profile.edit'),'label' => __('ui.nav.profile'),'icon' => 'profile','compact' => true,'active' => request()->routeIs('profile.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('profile.edit')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.profile')),'icon' => 'profile','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('profile.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                        <?php if(! $mobileIsCandidateRole): ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', \App\Models\User::class)): ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.users.index'),'label' => __('ui.nav.user_management'),'icon' => 'user_management','compact' => true,'active' => request()->routeIs('admin.users.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.users.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.user_management')),'icon' => 'user_management','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.users.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                            <?php endif; ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('access-admin-pages')): ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.ai-diagnostics.index'),'label' => __('ui.nav.ai_diagnostics'),'icon' => 'ai_diagnostics','compact' => true,'active' => request()->routeIs('admin.ai-diagnostics.*', 'platform.ai-diagnostics*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.ai-diagnostics.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.ai_diagnostics')),'icon' => 'ai_diagnostics','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.ai-diagnostics.*', 'platform.ai-diagnostics*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.email-templates.index'),'label' => __('ui.nav.communication_engine'),'icon' => 'communication_engine','compact' => true,'active' => request()->routeIs('admin.email-templates.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.email-templates.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.communication_engine')),'icon' => 'communication_engine','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.email-templates.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.video-configs.index'),'label' => __('ui.nav.video_configs'),'icon' => 'video_configs','compact' => true,'active' => request()->routeIs('admin.video-configs.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.video-configs.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.video_configs')),'icon' => 'video_configs','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.video-configs.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.sjt-scenarios.index'),'label' => __('ui.nav.sjt_scenarios'),'icon' => 'sjt_scenarios','compact' => true,'active' => request()->routeIs('admin.sjt-scenarios.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.sjt-scenarios.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.sjt_scenarios')),'icon' => 'sjt_scenarios','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.sjt-scenarios.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.health.index'),'label' => __('ui.nav.health_checklist'),'icon' => 'health_checklist','compact' => true,'active' => request()->routeIs('admin.health.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.health.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.health_checklist')),'icon' => 'health_checklist','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.health.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.departments.index'),'label' => __('ui.nav.departments'),'icon' => 'departments','compact' => true,'active' => request()->routeIs('admin.departments.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.departments.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.departments')),'icon' => 'departments','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.departments.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.values.index'),'label' => __('ui.nav.company_values'),'icon' => 'company_values','compact' => true,'active' => request()->routeIs('admin.values.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.values.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.company_values')),'icon' => 'company_values','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.values.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.faqs.index'),'label' => __('ui.nav.faqs'),'icon' => 'faqs','compact' => true,'active' => request()->routeIs('admin.faqs.*'),'@click' => 'sidebarOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.faqs.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.faqs')),'icon' => 'faqs','compact' => true,'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.faqs.*')),'@click' => 'sidebarOpen = false']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $attributes = $__attributesOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__attributesOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal230d78629742508075cd03dd9439398e)): ?>
<?php $component = $__componentOriginal230d78629742508075cd03dd9439398e; ?>
<?php unset($__componentOriginal230d78629742508075cd03dd9439398e); ?>
<?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="mt-4 flex gap-2">
                        <a href="<?php echo e(route('locale.switch', ['locale' => 'en'])); ?>" class="rounded-lg border border-aura-200/50 bg-white/70 px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-white"><?php echo e(__('ui.locale.en')); ?></a>
                        <a href="<?php echo e(route('locale.switch', ['locale' => 'fr'])); ?>" class="rounded-lg border border-aura-200/50 bg-white/70 px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-white"><?php echo e(__('ui.locale.fr')); ?></a>
                    </div>
                </nav>
            </aside>

            <div x-cloak x-show="rightSidebarEnabled && rightSidebarOpen && !isXlViewport" class="fixed inset-0 z-40 bg-slate-900/20 backdrop-blur-sm xl:hidden" @click="rightSidebarOpen = false"></div>
            <aside
                x-cloak
                x-show="rightSidebarEnabled && rightSidebarOpen && !isXlViewport"
                x-transition:enter="transform transition duration-250 ease-out"
                x-transition:enter-start="translate-x-8 opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transform transition duration-200 ease-in"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-8 opacity-0"
                class="fixed inset-y-0 right-0 z-50 w-[22rem] max-w-[94vw] border-l border-white/80 bg-white/90 p-4 backdrop-blur-2xl xl:hidden"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-600"><?php echo e(__('ui.right_sidebar.title')); ?></h2>
                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition-weightless hover:bg-slate-50" @click="rightSidebarOpen = false">
                        <?php echo e(__('ui.nav.close')); ?>

                    </button>
                </div>
                <?php if (isset($component)) { $__componentOriginal9a9942538d738d045237b705335695e1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9a9942538d738d045237b705335695e1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.right-sidebar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('right-sidebar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9a9942538d738d045237b705335695e1)): ?>
<?php $attributes = $__attributesOriginal9a9942538d738d045237b705335695e1; ?>
<?php unset($__attributesOriginal9a9942538d738d045237b705335695e1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9a9942538d738d045237b705335695e1)): ?>
<?php $component = $__componentOriginal9a9942538d738d045237b705335695e1; ?>
<?php unset($__componentOriginal9a9942538d738d045237b705335695e1); ?>
<?php endif; ?>
            </aside>

            <div
                class="flex min-h-screen flex-1 flex-col transition-[margin-left] ease-out min-w-0"
                x-bind:class="leftSidebarResizing ? 'duration-75' : 'duration-300'"
                x-bind:style="leftSidebarMarginStyle()"
            >
                <?php if (isset($component)) { $__componentOriginaleb97fe2d2a21304911c5baf409644ddc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleb97fe2d2a21304911c5baf409644ddc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.top-bar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('top-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleb97fe2d2a21304911c5baf409644ddc)): ?>
<?php $attributes = $__attributesOriginaleb97fe2d2a21304911c5baf409644ddc; ?>
<?php unset($__attributesOriginaleb97fe2d2a21304911c5baf409644ddc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleb97fe2d2a21304911c5baf409644ddc)): ?>
<?php $component = $__componentOriginaleb97fe2d2a21304911c5baf409644ddc; ?>
<?php unset($__componentOriginaleb97fe2d2a21304911c5baf409644ddc); ?>
<?php endif; ?>

                <div class="flex flex-1 gap-4 px-4 pb-6 sm:px-6 lg:px-8">
                    <main class="min-w-0 flex-1 overflow-y-auto pt-6">
                        <div class="mx-auto w-full max-w-[1480px]">
                            <?php echo e($slot); ?>

                        </div>
                    </main>

                    <aside
                        x-cloak
                        x-show="rightSidebarEnabled && rightSidebarOpen && isXlViewport"
                        x-transition:enter="transform transition duration-250 ease-out"
                        x-transition:enter-start="translate-x-4 opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transform transition duration-180 ease-in"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-4 opacity-0"
                        class="hidden xl:block xl:w-[22rem] xl:shrink-0 xl:pt-6"
                    >
                        <div class="sticky top-24">
                            <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-slate-600"><?php echo e(__('ui.right_sidebar.title')); ?></h2>
                            <?php if (isset($component)) { $__componentOriginal9a9942538d738d045237b705335695e1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9a9942538d738d045237b705335695e1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.right-sidebar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('right-sidebar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9a9942538d738d045237b705335695e1)): ?>
<?php $attributes = $__attributesOriginal9a9942538d738d045237b705335695e1; ?>
<?php unset($__attributesOriginal9a9942538d738d045237b705335695e1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9a9942538d738d045237b705335695e1)): ?>
<?php $component = $__componentOriginal9a9942538d738d045237b705335695e1; ?>
<?php unset($__componentOriginal9a9942538d738d045237b705335695e1); ?>
<?php endif; ?>
                        </div>
                    </aside>
                </div>

                <footer class="border-t border-white/70 bg-white/70 px-4 py-3 text-center text-xs text-slate-600 sm:px-6 lg:px-8">
                    <p class="font-medium uppercase tracking-[0.18em] text-slate-500"><?php echo e(__('ui.brand.developed_by')); ?></p>
                </footer>
            </div>

            <div class="fixed inset-x-0 bottom-4 z-[60] space-y-2 px-4 sm:right-4 sm:left-auto sm:w-[26rem]">
                <template x-for="toast in toasts" :key="toast.id">
                    <?php if (isset($component)) { $__componentOriginal20219e5d4a8f384b085d3f31e43f063a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal20219e5d4a8f384b085d3f31e43f063a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast-alert','data' => ['xBind:type' => 'toast.type','xText' => 'toast.message']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['x-bind:type' => 'toast.type','x-text' => 'toast.message']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal20219e5d4a8f384b085d3f31e43f063a)): ?>
<?php $attributes = $__attributesOriginal20219e5d4a8f384b085d3f31e43f063a; ?>
<?php unset($__attributesOriginal20219e5d4a8f384b085d3f31e43f063a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal20219e5d4a8f384b085d3f31e43f063a)): ?>
<?php $component = $__componentOriginal20219e5d4a8f384b085d3f31e43f063a; ?>
<?php unset($__componentOriginal20219e5d4a8f384b085d3f31e43f063a); ?>
<?php endif; ?>
                </template>
            </div>
        </div>
        
        <?php echo $__env->yieldPushContent('scripts'); ?>
    </body>
</html>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/shell-layout.blade.php ENDPATH**/ ?>