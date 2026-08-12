<?php
// Wizard step 4 — Program Eligibility
$s4 = $data['step4'] ?? [];
$eligResult = $data['preliminary_eligibility'] ?? null;
?>
<?php include APP_ROOT . '/app/views/wizard/_progress.php'; ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div><h2>Step 4 — Program Eligibility</h2><div class="page-subtitle">Program selection and eligibility questions</div></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($eligResult): ?>
<div class="alert <?= $eligResult === 'pass' ? 'alert-success' : ($eligResult === 'flag' ? 'alert-warning' : 'alert-danger') ?> rounded-3 mb-3">
    <?php if ($eligResult === 'pass'): ?>
        <i class="bi bi-check-circle-fill me-2"></i><strong>Preliminary check passed.</strong> Please complete the remaining steps.
    <?php elseif ($eligResult === 'flag'): ?>
        <i class="bi bi-flag-fill me-2"></i><strong>Your application has been flagged</strong> for additional review. You may continue.
    <?php else: ?>
        <i class="bi bi-x-circle-fill me-2"></i><strong>Ineligible:</strong> <?= htmlspecialchars($data['stop_reason'] ?? 'You do not meet the basic eligibility requirements for this program.') ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/applications/wizard/4">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-clipboard-check"></span>Program Selection</div>
        <div class="card-body">
            <?php if (!empty($programs)): ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Select Program <span class="text-danger">*</span></label>
                    <select name="program_id" id="programSelect" class="form-select <?= isset($errors['program_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose a program…</option>
                        <?php $selProg = (int)($old['program_id'] ?? $s4['program_id'] ?? 0); ?>
                        <?php foreach ($programs as $prog): ?>
                        <option value="<?= $prog['program_id'] ?>" <?= $selProg === (int)$prog['program_id'] ? 'selected' : '' ?>
                                data-questions='<?= htmlspecialchars($prog['eligibility_questions'] ?? '[]') ?>'>
                            <?= htmlspecialchars($prog['program_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['program_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['program_id']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-clipboard-x"></i></div><div class="empty-state-text">No programs available</div></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3" id="dynamicQuestionsCard" style="display:none;">
        <div class="card-header"><span class="card-title-icon bi bi-question-circle"></span>Program-Specific Questions</div>
        <div class="card-body">
            <div id="dynamicQuestions" class="row g-3"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><span class="card-title-icon bi bi-shield-check"></span>General Eligibility</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_us_resident" value="1" id="usResident"
                               <?= !empty($old['is_us_resident'] ?? $s4['is_us_resident'] ?? '') ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="usResident">
                            I am a US citizen or qualified legal resident <span class="text-danger">*</span>
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="no_active_applications" value="1" id="noActiveApps"
                               <?= !empty($old['no_active_applications'] ?? $s4['no_active_applications'] ?? '') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="noActiveApps">
                            I confirm I do not have 3 or more active applications for the same program
                        </label>
                    </div>
                </div>

                <!-- Mandatory static eligibility questions (spec §1 Step 4) -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Are you currently receiving any federal assistance? <span class="text-danger">*</span></label>
                    <?php $curFed = $old['currently_receiving_federal'] ?? $s4['currently_receiving_federal'] ?? ''; ?>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="currently_receiving_federal" value="yes" id="fedYes"
                                   <?= $curFed === 'yes' ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="fedYes">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="currently_receiving_federal" value="no" id="fedNo"
                                   <?= $curFed === 'no' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fedNo">No</label>
                        </div>
                    </div>
                    <?php if (isset($errors['currently_receiving_federal'])): ?>
                        <div class="text-danger small mt-1"><?= htmlspecialchars($errors['currently_receiving_federal']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Have you received this specific benefit in the last 12 months? <span class="text-danger">*</span></label>
                    <?php $recBen = $old['received_benefit_12mo'] ?? $s4['received_benefit_12mo'] ?? ''; ?>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="received_benefit_12mo" value="yes" id="ben12Yes"
                                   <?= $recBen === 'yes' ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="ben12Yes">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="received_benefit_12mo" value="no" id="ben12No"
                                   <?= $recBen === 'no' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ben12No">No</label>
                        </div>
                    </div>
                    <?php if (isset($errors['received_benefit_12mo'])): ?>
                        <div class="text-danger small mt-1"><?= htmlspecialchars($errors['received_benefit_12mo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <?php
                    // Pre-fill from step 2: if num_dependents > 0 default to yes
                    $step2 = $data['step2'] ?? [];
                    $hasDep = (int)($step2['num_dependents'] ?? 0) > 0;
                    $depU18 = $old['has_dependents_under_18'] ?? $s4['has_dependents_under_18'] ?? ($hasDep ? 'yes' : '');
                    ?>
                    <label class="form-label fw-semibold">Do you have dependents under age 18? <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="has_dependents_under_18" value="yes" id="dep18Yes"
                                   <?= $depU18 === 'yes' ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="dep18Yes">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="has_dependents_under_18" value="no" id="dep18No"
                                   <?= $depU18 === 'no' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="dep18No">No</label>
                        </div>
                    </div>
                    <?php if (isset($errors['has_dependents_under_18'])): ?>
                        <div class="text-danger small mt-1"><?= htmlspecialchars($errors['has_dependents_under_18']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label">Additional Information / Special Circumstances</label>
                    <textarea name="special_circumstances" class="form-control" rows="3"
                              placeholder="Provide any additional context that may be relevant to your eligibility…"><?= htmlspecialchars($old['special_circumstances'] ?? $s4['special_circumstances'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/applications/wizard/3" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button type="submit" class="btn btn-brand flex-grow-1 py-2"><i class="bi bi-arrow-right me-1"></i>Continue to Step 5</button>
    </div>
</form>
</div>
</div>
<script>
(function(){
    var savedAnswers = <?= json_encode($old['program_answers'] ?? $s4['program_answers'] ?? []) ?>;
    var programSelect = document.getElementById('programSelect');
    var card = document.getElementById('dynamicQuestionsCard');
    var container = document.getElementById('dynamicQuestions');

    function renderQuestions(questions) {
        container.innerHTML = '';
        if (!questions || !questions.length) { card.style.display = 'none'; return; }
        card.style.display = '';
        questions.forEach(function(q, idx) {
            var saved = savedAnswers[q.key] || '';
            var col = document.createElement('div');
            col.className = 'col-md-6';
            var label = '<label class="form-label">' + q.label + (q.required ? ' <span class="text-danger">*</span>' : '') + '</label>';
            var input = '';
            if (q.type === 'select') {
                input = '<select name="program_answers[' + q.key + ']" class="form-select"' + (q.required ? ' required' : '') + '>';
                input += '<option value="">Select…</option>';
                (q.options || []).forEach(function(o){ input += '<option value="'+o+'"'+(saved===o?' selected':'')+'>'+o+'</option>'; });
                input += '</select>';
            } else if (q.type === 'checkbox') {
                input = '<div class="form-check"><input class="form-check-input" type="checkbox" name="program_answers['+q.key+']" value="1" id="pq_'+idx+'"'+(saved==='1'?' checked':'')+'><label class="form-check-label" for="pq_'+idx+'">'+q.label+'</label></div>';
                label = '';
            } else {
                input = '<input type="'+(q.type||'text')+'" name="program_answers['+q.key+']" class="form-control"'+(q.required?' required':'')+' value="'+saved+'">';
            }
            col.innerHTML = label + input;
            container.appendChild(col);
        });
    }

    function onProgramChange() {
        var opt = programSelect.options[programSelect.selectedIndex];
        if (!opt || !opt.value) { card.style.display = 'none'; return; }
        try {
            var qs = JSON.parse(opt.dataset.questions || '[]');
            renderQuestions(qs);
        } catch(e) { card.style.display = 'none'; }
    }

    programSelect.addEventListener('change', onProgramChange);
    onProgramChange();
})();
</script>
