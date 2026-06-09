<aside <?php echo e($attributes->class([
    'hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:p-4 lg:transform-gpu lg:transition-[width] lg:ease-out',
])->merge([
    'x-bind:class' => "(leftSidebarResizing ? 'lg:duration-75 ' : 'lg:duration-300 ') + (leftSidebarCollapsed ? 'pointer-events-none lg:opacity-0' : 'pointer-events-auto lg:opacity-100')",
    'x-bind:style' => 'leftSidebarWidthStyle()',
])); ?>>
    <div class="flex h-full w-full flex-col rounded-3xl border border-white/80 bg-white/75 shadow-[0_24px_70px_-36px_rgba(76,29,149,0.55)] backdrop-blur-2xl">
        <div class="shrink-0 border-b border-slate-200/80 px-6 py-6 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'px-3 py-4' : 'px-6 py-6'">
            <a
                href="<?php echo e(route('auth.company.dispatch')); ?>"
                class="inline-flex items-center rounded-2xl border border-slate-200/70 bg-white/85 px-3 py-2 transition-weightless hover:bg-white"
                x-bind:class="leftSidebarCollapsed ? 'w-full justify-center px-2' : ''"
                aria-label="numa portal home"
            >
                <img
                    src="<?php echo e(asset('images/numa-logo-clean.png')); ?>"
                    alt="<?php echo e(config('app.name')); ?> logo"
                    class="h-10 w-auto object-contain transition-all duration-300"
                    x-bind:class="leftSidebarCollapsed ? 'h-8' : 'h-10'"
                    loading="eager"
                    decoding="async"
                >
            </a>
        </div>

        <?php
            $activeCompanyId = session('active_company_id');
            $activeCompanySlug = null;
            $activeRole = null;
            $isCandidateRole = false;

            if (auth()->check() && ! auth()->user()->isSuperadmin() && is_string($activeCompanyId) && $activeCompanyId !== '') {
                $activeCompanySlug = \App\Models\Company::query()
                    ->whereKey($activeCompanyId)
                    ->value('slug');

                $activeRole = auth()->user()->memberships()
                    ->where('company_id', $activeCompanyId)
                    ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
                    ->value('company_role');

                $isCandidateRole = $activeRole === \App\Models\CompanyMembership::ROLE_CANDIDATE;
            }

            $menuNotificationDots = collect(session('sidebar_notification_dots', []));
            $overviewDot = (bool) $menuNotificationDots->get('overview', session()->has('status') || session()->has('error'));

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

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'px-2' : 'px-3'">
            <nav class="space-y-1.5 text-sm">
                <?php if(auth()->guard()->check()): ?>
                    <?php if(auth()->user()->isSuperadmin()): ?>
                        <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('platform.console'),'label' => __('ui.nav.platform_console'),'icon' => 'platform_console','active' => request()->routeIs('platform.console'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('platform.console')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.platform_console')),'icon' => 'platform_console','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('platform.console')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('platform.company-approvals'),'label' => __('ui.nav.company_approvals'),'icon' => 'company_approvals','active' => request()->routeIs('platform.company-approvals*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('platform.company-approvals')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.company_approvals')),'icon' => 'company_approvals','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('platform.company-approvals*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('superadmin.contact-inquiries.index'),'label' => __('ui.nav.contact_inquiries'),'icon' => 'contact_inquiries','active' => request()->routeIs('superadmin.contact-inquiries*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('superadmin.contact-inquiries.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.contact_inquiries')),'icon' => 'contact_inquiries','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('superadmin.contact-inquiries*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('platform.ai-diagnostics'),'label' => __('ui.nav.ai_diagnostics'),'icon' => 'ai_diagnostics','active' => request()->routeIs('platform.ai-diagnostics*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('platform.ai-diagnostics')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.ai_diagnostics')),'icon' => 'ai_diagnostics','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('platform.ai-diagnostics*')),'collapsible' => true]); ?>
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
                        <?php if($isCandidateRole): ?>
                            <?php if($activeCompanySlug): ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.portal', ['company' => $activeCompanySlug]),'label' => __('ui.nav.candidate_dashboard'),'icon' => 'overview','active' => request()->routeIs(...$candidateDashboardRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.portal', ['company' => $activeCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_dashboard')),'icon' => 'overview','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateDashboardRoutePatterns)),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.applications', ['company' => $activeCompanySlug]),'label' => __('ui.nav.candidate_applications'),'icon' => 'candidates','active' => request()->routeIs(...$candidateApplicationsRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.applications', ['company' => $activeCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_applications')),'icon' => 'candidates','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateApplicationsRoutePatterns)),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.updates', ['company' => $activeCompanySlug]),'label' => __('ui.nav.candidate_updates'),'icon' => 'contact_inquiries','active' => request()->routeIs(...$candidateUpdatesRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.updates', ['company' => $activeCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_updates')),'icon' => 'contact_inquiries','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateUpdatesRoutePatterns)),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.faq', ['company' => $activeCompanySlug]),'label' => __('ui.nav.faqs'),'icon' => 'faqs','active' => request()->routeIs(...$candidateFaqRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.faq', ['company' => $activeCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.faqs')),'icon' => 'faqs','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateFaqRoutePatterns)),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.social-hub.index', ['company' => $activeCompanySlug]),'label' => __('ui.nav.social_hub'),'icon' => 'social_hub','active' => request()->routeIs(...$candidateSocialRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.social-hub.index', ['company' => $activeCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.social_hub')),'icon' => 'social_hub','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateSocialRoutePatterns)),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.assessments.sjt'),'label' => __('ui.nav.assessments'),'icon' => 'assessments','active' => request()->routeIs(...$candidateAssessmentsRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.assessments.sjt')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.assessments')),'icon' => 'assessments','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateAssessmentsRoutePatterns)),'collapsible' => true]); ?>
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
                            <?php if($activeCompanySlug): ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidate.account', ['company' => $activeCompanySlug]),'label' => __('ui.nav.candidate_account'),'icon' => 'profile','active' => request()->routeIs(...$candidateAccountRoutePatterns),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidate.account', ['company' => $activeCompanySlug])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidate_account')),'icon' => 'profile','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs(...$candidateAccountRoutePatterns)),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('home'),'label' => 'Dashboard','icon' => 'overview','active' => request()->routeIs('home'),'dot' => $overviewDot,'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('home')),'label' => 'Dashboard','icon' => 'overview','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('home')),'dot' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($overviewDot),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.recruitment-needs.index'),'label' => 'TB Recrutement','icon' => 'company_approvals','active' => request()->routeIs('admin.recruitment-needs.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.recruitment-needs.index')),'label' => 'TB Recrutement','icon' => 'company_approvals','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.recruitment-needs.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('jobs.index'),'label' => __('ui.nav.jobs'),'icon' => 'jobs','active' => request()->routeIs('jobs.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('jobs.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.jobs')),'icon' => 'jobs','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('jobs.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidates.index'),'label' => __('ui.nav.candidates'),'icon' => 'candidates','active' => request()->routeIs('candidates.index'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidates.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidates')),'icon' => 'candidates','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('candidates.index')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('candidates.kanban'),'label' => __('ui.nav.candidates_kanban'),'icon' => 'candidates_kanban','active' => request()->routeIs('candidates.kanban*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('candidates.kanban')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.candidates_kanban')),'icon' => 'candidates_kanban','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('candidates.kanban*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('interviews.index'),'label' => __('ui.nav.interviews'),'icon' => 'interviews','active' => request()->routeIs('interviews.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('interviews.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.interviews')),'icon' => 'interviews','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('interviews.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('referrals.index'),'label' => __('ui.nav.referrals'),'icon' => 'referrals','active' => request()->routeIs('referrals.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('referrals.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.referrals')),'icon' => 'referrals','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('referrals.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('social-hub.index'),'label' => __('ui.nav.social_hub'),'icon' => 'social_hub','active' => request()->routeIs('social-hub.*', 'candidate.social-hub.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('social-hub.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.social_hub')),'icon' => 'social_hub','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('social-hub.*', 'candidate.social-hub.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('analytics.index'),'label' => __('ui.nav.analytics'),'icon' => 'analytics','active' => request()->routeIs('analytics.index', 'analytics.alerts.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('analytics.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.analytics')),'icon' => 'analytics','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('analytics.index', 'analytics.alerts.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('analytics.fairness'),'label' => __('ui.nav.fairness'),'icon' => 'fairness','active' => request()->routeIs('analytics.fairness*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('analytics.fairness')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.fairness')),'icon' => 'fairness','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('analytics.fairness*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('configuration.index'),'label' => __('ui.nav.configuration'),'icon' => 'configuration','active' => request()->routeIs('configuration.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('configuration.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.configuration')),'icon' => 'configuration','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('configuration.*')),'collapsible' => true]); ?>
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
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', \App\Models\User::class)): ?>
                                <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.users.index'),'label' => __('ui.nav.user_management'),'icon' => 'user_management','active' => request()->routeIs('admin.users.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.users.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.user_management')),'icon' => 'user_management','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.users.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.ai-diagnostics.index'),'label' => __('ui.nav.ai_diagnostics'),'icon' => 'ai_diagnostics','active' => request()->routeIs('admin.ai-diagnostics.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.ai-diagnostics.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.ai_diagnostics')),'icon' => 'ai_diagnostics','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.ai-diagnostics.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.email-templates.index'),'label' => __('ui.nav.communication_engine'),'icon' => 'communication_engine','active' => request()->routeIs('admin.email-templates.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.email-templates.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.communication_engine')),'icon' => 'communication_engine','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.email-templates.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.video-configs.index'),'label' => __('ui.nav.video_configs'),'icon' => 'video_configs','active' => request()->routeIs('admin.video-configs.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.video-configs.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.video_configs')),'icon' => 'video_configs','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.video-configs.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.sjt-scenarios.index'),'label' => __('ui.nav.sjt_scenarios'),'icon' => 'sjt_scenarios','active' => request()->routeIs('admin.sjt-scenarios.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.sjt-scenarios.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.sjt_scenarios')),'icon' => 'sjt_scenarios','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.sjt-scenarios.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.exports.index'),'label' => __('ui.nav.exports_history'),'icon' => 'exports_history','active' => request()->routeIs('admin.exports.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.exports.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.exports_history')),'icon' => 'exports_history','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.exports.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.health.index'),'label' => __('ui.nav.health_checklist'),'icon' => 'health_checklist','active' => request()->routeIs('admin.health.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.health.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.health_checklist')),'icon' => 'health_checklist','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.health.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.departments.index'),'label' => __('ui.nav.departments'),'icon' => 'departments','active' => request()->routeIs('admin.departments.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.departments.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.departments')),'icon' => 'departments','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.departments.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.values.index'),'label' => __('ui.nav.company_values'),'icon' => 'company_values','active' => request()->routeIs('admin.values.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.values.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.company_values')),'icon' => 'company_values','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.values.*')),'collapsible' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('admin.faqs.index'),'label' => __('ui.nav.faqs'),'icon' => 'faqs','active' => request()->routeIs('admin.faqs.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.faqs.index')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.faqs')),'icon' => 'faqs','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.faqs.*')),'collapsible' => true]); ?>
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
                    <?php if (isset($component)) { $__componentOriginal230d78629742508075cd03dd9439398e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal230d78629742508075cd03dd9439398e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-link','data' => ['href' => route('profile.edit'),'label' => __('ui.nav.profile'),'icon' => 'profile','active' => request()->routeIs('profile.*'),'collapsible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('profile.edit')),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.nav.profile')),'icon' => 'profile','active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('profile.*')),'collapsible' => true]); ?>
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
            </nav>
        </div>

        <?php if(auth()->guard()->check()): ?>
            <?php
                $profile = auth()->user()->profile;
                $displayName = $profile?->full_name ?? auth()->user()->email;
                $initials = collect(explode(' ', trim($displayName)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                $avatarUrl = $profile?->avatar_url
                    ? \Illuminate\Support\Facades\URL::temporarySignedRoute('media.avatar', now()->addMinutes(10), ['profile' => $profile->getKey()])
                    : null;
            ?>
            <div class="shrink-0 border-t border-slate-200/80 p-4 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'p-2' : 'p-4'">
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/85 p-3 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'justify-center p-2' : 'p-3'">
                    <?php if($avatarUrl): ?>
                        <img src="<?php echo e($avatarUrl); ?>" alt="<?php echo e($displayName); ?>" class="size-10 rounded-full object-cover">
                    <?php else: ?>
                        <div class="flex size-10 items-center justify-center rounded-full bg-aura-100 text-sm font-semibold text-aura-800"><?php echo e($initials); ?></div>
                    <?php endif; ?>
                    <div class="min-w-0" x-cloak x-show="!leftSidebarCollapsed">
                        <p class="truncate text-sm font-semibold text-slate-900"><?php echo e($displayName); ?></p>
                        <p class="text-xs uppercase tracking-wide text-slate-600">
                            <?php echo e(auth()->user()->isSuperadmin() ? __('ui.nav.platform_console') : __('admin.roles.'.($activeRole ?? \App\Models\User::ROLE_CANDIDATE))); ?>

                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <button
        type="button"
        class="absolute inset-y-6 -right-2 z-40 hidden w-4 cursor-col-resize items-center justify-center rounded-full lg:flex"
        x-bind:class="leftSidebarCollapsed ? 'pointer-events-none opacity-0' : 'opacity-100'"
        @mousedown.prevent="startLeftSidebarResize($event)"
        @touchstart.prevent="startLeftSidebarResize($event)"
        aria-label="Resize sidebar"
        title="Resize sidebar"
    >
        <span
            class="h-24 w-1 rounded-full border transition-weightless"
            x-bind:class="leftSidebarResizing ? 'border-primary-300 bg-primary-100 shadow-[0_0_0_4px_rgba(59,130,246,0.10)]' : 'border-white/90 bg-white/95 shadow-[0_10px_24px_-12px_rgba(30,41,59,0.55)]'"
        ></span>
    </button>

</aside>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/sidebar-nav.blade.php ENDPATH**/ ?>