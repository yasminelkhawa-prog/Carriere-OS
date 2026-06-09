<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'href' => '#',
    'label' => '',
    'icon' => 'overview',
    'active' => false,
    'dot' => false,
    'compact' => false,
    'collapsible' => false,
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
    'href' => '#',
    'label' => '',
    'icon' => 'overview',
    'active' => false,
    'dot' => false,
    'compact' => false,
    'collapsible' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
$baseClasses = $compact
    ? 'group portal-menu-item flex items-center gap-2 rounded-xl px-3 py-2 font-medium transition-weightless'
    : 'group portal-menu-item flex items-center gap-3 rounded-2xl px-4 py-3 font-medium transition-weightless';

$activeClasses = $compact
    ? 'bg-aura-200 text-aura-900'
    : 'bg-aura-700 text-white shadow-lg shadow-aura-300';

    $inactiveClasses = $compact
        ? 'text-slate-800 hover:bg-aura-100/70'
        : 'text-slate-700 hover:bg-white/85 hover:text-slate-900 hover:shadow-sm';
?>

<a
    href="<?php echo e($href); ?>"
    data-portal-menu-item
    x-bind:title="<?php echo \Illuminate\Support\Js::from($collapsible && ! $compact)->toHtml() ?> && leftSidebarCollapsed ? <?php echo \Illuminate\Support\Js::from($label)->toHtml() ?> : ''"
    <?php echo e($attributes->class([$baseClasses, $active ? $activeClasses : $inactiveClasses])->merge([
        'x-bind:class' => $collapsible && ! $compact ? "leftSidebarCollapsed ? 'justify-center gap-0 px-2 py-3' : ''" : '',
    ])); ?>

>
    <?php if (isset($component)) { $__componentOriginal7c570875a05dcae58638b35ccf957e4e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7c570875a05dcae58638b35ccf957e4e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.nav-icon','data' => ['name' => $icon,'class' => $compact ? 'size-4' : 'size-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.nav-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($compact ? 'size-4' : 'size-5')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7c570875a05dcae58638b35ccf957e4e)): ?>
<?php $attributes = $__attributesOriginal7c570875a05dcae58638b35ccf957e4e; ?>
<?php unset($__attributesOriginal7c570875a05dcae58638b35ccf957e4e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7c570875a05dcae58638b35ccf957e4e)): ?>
<?php $component = $__componentOriginal7c570875a05dcae58638b35ccf957e4e; ?>
<?php unset($__componentOriginal7c570875a05dcae58638b35ccf957e4e); ?>
<?php endif; ?>
    <?php if($collapsible && ! $compact): ?>
        <span class="truncate" x-cloak x-show="!leftSidebarCollapsed"><?php echo e($label); ?></span>
    <?php else: ?>
        <span class="truncate"><?php echo e($label); ?></span>
    <?php endif; ?>
    <?php if($dot): ?>
        <span
            class="ms-auto inline-flex size-2.5 rounded-full bg-danger-500 ring-2 ring-white"
            aria-hidden="true"
            <?php if($collapsible && ! $compact): ?>
                x-bind:class="leftSidebarCollapsed ? 'ms-0 mt-1' : 'ms-auto'"
            <?php endif; ?>
        ></span>
    <?php endif; ?>
</a>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/ui/nav-link.blade.php ENDPATH**/ ?>