<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_active_access($user);
$companyId = $user['company_id'];
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $assignedTo = trim($_POST['assigned_to'] ?? '');
        $dueDate = $_POST['due_date'] ?: null;
        if ($title !== '') {
            $stmt = $conn->prepare('INSERT INTO remediation_items (company_id, title, details, assigned_to, due_date, status) VALUES (?, ?, ?, ?, ?, "open")');
            $stmt->bind_param('issss', $companyId, $title, $details, $assignedTo, $dueDate);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'update_status') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $status = $_POST['status'] ?? 'open';
        if (in_array($status, ['open', 'in_progress', 'done'], true)) {
            $stmt = $conn->prepare('UPDATE remediation_items SET status = ? WHERE id = ? AND company_id = ?');
            $stmt->bind_param('sii', $status, $itemId, $companyId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: /remediation.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM remediation_items WHERE company_id = ? ORDER BY FIELD(status,"open","in_progress","done"), due_date IS NULL, due_date ASC, created_at DESC');
$stmt->bind_param('i', $companyId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Remediation Plan';
require __DIR__ . '/includes/header.php';
?>
<h1>Remediation Plan</h1>
<p class="helper">Items are created automatically from questionnaire gaps ("No" or "Partial" answers). You can also add your own.</p>

<div class="panel" style="margin-bottom:20px;">
    <h2>Add an item</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" required>
        <label for="details">Details</label>
        <textarea id="details" name="details"></textarea>
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label for="assigned_to">Assigned to</label>
                <input type="text" id="assigned_to" name="assigned_to">
            </div>
            <div style="flex:1;">
                <label for="due_date">Due date</label>
                <input type="date" id="due_date" name="due_date">
            </div>
        </div>
        <div style="margin-top:16px;"><button type="submit">Add item</button></div>
    </form>
</div>

<div class="panel">
    <table>
        <thead>
            <tr><th>Title</th><th>Assigned to</th><th>Due</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr><td colspan="5" class="helper">No remediation items yet — complete the questionnaire to generate some, or add one above.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <div><?= e($item['title']) ?></div>
                    <?php if ($item['details']): ?><div class="helper" style="margin-top:2px;"><?= e($item['details']) ?></div><?php endif; ?>
                </td>
                <td><?= e($item['assigned_to'] ?: '—') ?></td>
                <td><?= e($item['due_date'] ?: '—') ?></td>
                <td><span class="status-badge status-<?= e($item['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $item['status']))) ?></span></td>
                <td>
                    <form method="post" style="display:flex;gap:6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="btn-small">
                            <option value="open" <?= $item['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="in_progress" <?= $item['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                            <option value="done" <?= $item['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
