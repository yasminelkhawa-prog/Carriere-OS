<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label',
    'name' => null,
    'required' => false,
    'help' => null,
    'error' => null,
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
    'label',
    'name' => null,
    'required' => false,
    'help' => null,
    'error' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $hasError = (bool) ($error || ($name && $errors->has($name)));
?>

<div <?php echo e($attributes->class(['space-y-2', 'form-field-invalid' => $hasError])); ?>>
    <label class="block text-sm font-medium text-slate-800">
        <?php echo e($label); ?>

        <?php if($required): ?>
            <span class="text-danger-700">*</span>
        <?php endif; ?>
    </label>

    <?php echo e($slot); ?>


    <?php if($help): ?>
        <p class="text-xs text-slate-600"><?php echo e($help); ?></p>
    <?php endif; ?>

    <?php if($hasError): ?>
        <p class="inline-flex items-center gap-1 text-xs font-medium text-danger-700">
            <span aria-hidden="true">!</span>
            <span><?php echo e($error ?: $errors->first($name)); ?></span>
        </p>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/components/form-field.blade.php ENDPATH**/ ?>