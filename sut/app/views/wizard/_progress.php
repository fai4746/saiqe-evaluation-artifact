<?php
$currentStep = $step ?? 1;
$steps = ['Personal Info','Household','Income & Assets','Eligibility','Documents','Review'];
?>
<div class="wizard-progress mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">Step <?= $currentStep ?> of 6</span>
        <span class="text-muted small"><?= htmlspecialchars($steps[$currentStep - 1]) ?></span>
    </div>
    <div class="progress" style="height:6px;">
        <div class="progress-bar bg-brand" role="progressbar"
             style="width:<?= round(($currentStep / 6) * 100) ?>%"
             aria-valuenow="<?= $currentStep ?>" aria-valuemin="0" aria-valuemax="6"></div>
    </div>
    <div class="d-flex justify-content-between mt-2">
        <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
        <div class="wizard-step-dot <?= $n < $currentStep ? 'done' : ($n === $currentStep ? 'active' : '') ?>"
             title="<?= htmlspecialchars($label) ?>">
            <?php if ($n < $currentStep): ?>
                <i class="bi bi-check"></i>
            <?php else: ?>
                <?= $n ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
