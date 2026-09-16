<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_active_access($user);
$companyId = $user['company_id'];
$conn = db();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $qStmt = $conn->prepare('SELECT id FROM questions');
    $qStmt->execute();
    $allQuestionIds = array_column($qStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $qStmt->close();

    foreach ($allQuestionIds as $qid) {
        $answerKey = "answer_$qid";
        $noteKey = "note_$qid";
        if (!isset($_POST[$answerKey])) continue;

        $answer = $_POST[$answerKey];
        if (!in_array($answer, ['yes', 'partial', 'no'], true)) continue;
        $notes = trim($_POST[$noteKey] ?? '');

        $stmt = $conn->prepare('INSERT INTO responses (company_id, question_id, answer, notes, updated_by)
                                 VALUES (?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE answer = VALUES(answer), notes = VALUES(notes), updated_by = VALUES(updated_by)');
        $stmt->bind_param('iissi', $companyId, $qid, $answer, $notes, $user['id']);
        $stmt->execute();
        $stmt->close();
    }

    sync_remediation_items($companyId);
    $scores = compute_scores($companyId);
    record_score_snapshot($companyId, $scores['overall']);

    $saved = true;
}

// Load questions grouped by category, with existing answers
$sql = "SELECT c.id AS cat_id, c.name AS cat_name, c.sort_order AS cat_sort,
               q.id AS q_id, q.prompt, q.guidance, q.sort_order AS q_sort,
               r.answer, r.notes
        FROM categories c
        JOIN questions q ON q.category_id = c.id
        LEFT JOIN responses r ON r.question_id = q.id AND r.company_id = ?
        ORDER BY c.sort_order, q.sort_order";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $companyId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['cat_id']]['name'] = $row['cat_name'];
    $grouped[$row['cat_id']]['questions'][] = $row;
}

$pageTitle = 'Questionnaire';
require __DIR__ . '/includes/header.php';
?>
<h1>NDPR Compliance Questionnaire</h1>
<p class="helper">Answer each question based on your organization's current practice. Save at any point — you can come back and finish later.</p>

<?php if ($saved): ?><div class="success-box">Your answers have been saved.</div><?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <?php foreach ($grouped as $cat): ?>
        <div class="q-category">
            <h2><?= e($cat['name']) ?></h2>
            <?php foreach ($cat['questions'] as $q): ?>
                <div class="q-item">
                    <div class="q-prompt"><?= e($q['prompt']) ?></div>
                    <?php if ($q['guidance']): ?><div class="q-guidance"><?= e($q['guidance']) ?></div><?php endif; ?>
                    <div class="q-options" data-qid="<?= $q['q_id'] ?>">
                        <?php foreach (['yes' => 'Yes', 'partial' => 'Partially', 'no' => 'No'] as $val => $label): ?>
                            <label class="<?= $q['answer'] === $val ? 'checked' : '' ?>">
                                <input type="radio" name="answer_<?= $q['q_id'] ?>" value="<?= $val ?>" <?= $q['answer'] === $val ? 'checked' : '' ?>>
                                <?= $label ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="note_<?= $q['q_id'] ?>" placeholder="Optional note (evidence, context, owner)" value="<?= e($q['notes'] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div style="position:sticky;bottom:0;background:var(--bg);padding:16px 0;border-top:1px solid var(--border);">
        <button type="submit">Save answers</button>
    </div>
</form>

<script>
document.querySelectorAll('.q-options').forEach(function(group){
    group.querySelectorAll('input[type=radio]').forEach(function(input){
        input.addEventListener('change', function(){
            group.querySelectorAll('label').forEach(function(l){ l.classList.remove('checked'); });
            input.closest('label').classList.add('checked');
        });
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
