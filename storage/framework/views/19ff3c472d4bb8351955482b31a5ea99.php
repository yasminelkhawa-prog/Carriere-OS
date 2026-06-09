<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => null,
    'subtitle' => null,
    'bodyClass' => null,
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
    'subtitle' => null,
    'bodyClass' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<section <?php echo e($attributes->class(['glass-card p-5'])); ?>>
    <?php if($title): ?>
        <h2 class="text-2xl font-semibold text-slate-900"><?php echo e($title); ?></h2>
    <?php endif; ?>

    <?php if($subtitle): ?>
        <p class="mt-2 text-sm text-slate-700"><?php echo e($subtitle); ?></p>
    <?php endif; ?>

    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
        'mt-6' => $title || $subtitle,
        $bodyClass,
    ]); ?>">
        <?php echo e($slot); ?>

    </div>
</section>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/glass-card.blade.php ENDPATH**/ ?>