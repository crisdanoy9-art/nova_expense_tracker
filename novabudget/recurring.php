<?php
// recurring.php — Recurring expenses manager
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Recurring Expenses'; $activePage = 'recurring';

// Toggle recurring on/off
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = postStr('id');
    if ($id && isValidUuid($id)) {
        $exp = dbQueryOne('SELECT id, is_recurring FROM expenses WHERE id=? AND user_id=?',[$id,$userId]);
        if ($exp) {
            $newVal = $exp['is_recurring'] ? 'false' : 'true';
            dbExec('UPDATE expenses SET is_recurring=? WHERE id=? AND user_id=?',[$newVal,$id,$userId]);
            flashSet('success','Recurring status updated.');
        }
    }
    header('Location: ' . APP_BASE . '/recurring.php'); exit;
}

$recurring = dbQuery(
    "SELECT e.id, e.description, e.amount, e.expense_date, e.recurrence, e.payment_method,
            c.name AS cat_name, c.emoji AS cat_emoji, c.color AS cat_color
     FROM expenses e JOIN categories c ON c.id=e.category_id
     WHERE e.user_id=? AND e.is_recurring=TRUE
     ORDER BY e.expense_date DESC",
    [$userId]
);
$monthlyTotal = array_sum(array_map(function($r){
    $m = $r['recurrence'];
    $a = (float)$r['amount'];
    return $m==='daily'?$a*30:($m==='weekly'?$a*4:($m==='yearly'?round($a/12,2):$a));
},$recurring));

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-hd">
  <div class="page-title"><i class="bi bi-arrow-repeat" style="color:var(--c-teal)"></i> Recurring Expenses</div>
  <a href="<?= APP_BASE ?>/add_expense.php" class="btn-glow" style="width:auto;padding:9px 18px"><i class="bi bi-plus"></i> Add Recurring</a>
</div>

<?php if (!empty($recurring)): ?>
<div class="card-g" style="margin-bottom:18px;border-color:rgba(20,184,166,.2)">
  <div class="card-body" style="display:flex;align-items:center;gap:14px">
    <div style="width:44px;height:44px;border-radius:50%;background:rgba(20,184,166,.12);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">🔄</div>
    <div>
      <div style="font-size:13px;color:var(--text2)">Estimated monthly recurring cost</div>
      <div style="font-family:'Syne',sans-serif;font-size:26px;font-weight:700;color:var(--c-teal)">₱<?= number_format($monthlyTotal,2) ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card-g">
  <div class="card-head">
    <div class="card-title">Active Recurring Expenses</div>
    <span class="tag" style="background:rgba(20,184,166,.1);color:var(--c-teal)"><?= count($recurring) ?> active</span>
  </div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Expense</th><th>Category</th><th>Amount</th><th>Frequency</th><th>Last Date</th><th>Method</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($recurring)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text3)">No recurring expenses yet. <a href="<?= APP_BASE ?>/add_expense.php" style="color:var(--c-cyan)">Add one?</a></td></tr>
        <?php else: foreach ($recurring as $r): ?>
        <tr>
          <td style="font-weight:500"><?= h($r['description']) ?></td>
          <td><span class="badge-cat"><?= h($r['cat_emoji'].' '.$r['cat_name']) ?></span></td>
          <td style="font-weight:600;color:var(--c-teal)">₱<?= number_format((float)$r['amount'],2) ?></td>
          <td><span class="recurring-badge"><i class="bi bi-arrow-repeat"></i> <?= ucfirst(h($r['recurrence']??'monthly')) ?></span></td>
          <td style="color:var(--text3)"><?= h($r['expense_date']) ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= h($r['payment_method']) ?></td>
          <td style="display:flex;gap:5px">
            <a href="<?= APP_BASE ?>/edit_expense.php?id=<?= h($r['id']) ?>" class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= h($r['id']) ?>">
              <button type="submit" class="btn-icon" style="color:var(--c-amber)" title="Toggle recurring"><i class="bi bi-toggle-on"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
