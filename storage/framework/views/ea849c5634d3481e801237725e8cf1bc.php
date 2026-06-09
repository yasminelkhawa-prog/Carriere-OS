<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'type' => 'warning',
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
    'type' => 'warning',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $styles = [
        'info' => 'border-primary-200/70 bg-primary-100/90 text-primary-900',
        'success' => 'border-success-200/60 bg-success-100/90 text-success-900',
        'warning' => 'border-danger-200/70 bg-danger-100/90 text-danger-900',
        'error' => 'border-danger-200/70 bg-danger-100/90 text-danger-900',
    ];

    $labels = [
        'info' => 'INFO',
        'success' => 'OK',
        'warning' => 'WARN',
        'error' => 'ERROR',
    ];
?>

<div x-transition role="alert" <?php echo e($attributes->class(['rounded-xl border px-4 py-3 text-sm shadow-lg backdrop-blur', $styles[$type] ?? $styles['warning']])); ?>>
    <div class="flex items-start gap-2">
        <span class="rounded-md border border-current/25 bg-white/40 px-1.5 py-0.5 text-[10px] font-bold tracking-wide">
            <?php echo e($labels[$type] ?? $labels['warning']); ?>

        </span>
        <div class="min-w-0 flex-1">
            <?php echo e($slot); ?>

        </div>
    </div>
</div>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/toast-alert.blade.php ENDPATH**/ ?>