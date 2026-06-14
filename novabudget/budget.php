<?php
// budget.php — Monthly budget management
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Monthly Budget'; $activePage = 'budget';
$now = new DateTime(); 
$month = (int)$now->format('n'); 
$year = (int)$now->format('Y');

$months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $bMonth  = postInt('month', $month);
    $bYear   = postInt('year', $year);
    $bAmt    = postFloat('amount');
    $bThresh = postInt('alert_threshold', 80);
    $catId   = postStr('category_id') ?: null;

    if ($bAmt <= 0) { 
        flashSet('error', 'Enter a positive budget amount.'); 
    } else {
        try {
            // Manual Upsert Logic
            if ($catId === null) {
                $updated = dbExec(
                    "UPDATE budgets SET amount=?, alert_threshold=?, updated_at=NOW()
                     WHERE user_id=? AND category_id IS NULL AND month=? AND year=?",
                    [$bAmt, $bThresh, $userId, $bMonth, $bYear]
                );
            } else {
                $updated = dbExec(
                    "UPDATE budgets SET amount=?, alert_threshold=?, updated_at=NOW()
                     WHERE user_id=? AND category_id=?::uuid AND month=? AND year=?",
                    [$bAmt, $bThresh, $userId, $catId, $bMonth, $bYear]
                );
            }

            if ($updated === 0) {
                dbExec(
                    "INSERT INTO budgets (user_id, category_id, amount, month, year, alert_threshold)
                     VALUES (?, " . ($catId === null ? 'NULL' : '?::uuid') . ", ?, ?, ?, ?)",
                    $catId === null 
                        ? [$userId, $bAmt, $bMonth, $bYear, $bThresh] 
                        : [$userId, $catId, $bAmt, $bMonth, $bYear, $bThresh]
                );
            }
            flashSet('success', 'Budget saved for ' . $months[$bMonth] . ' ' . $bYear . '!');
        } catch (Exception $e) {
            error_log('Budget save error: ' . $e->getMessage());
            flashSet('error', 'Failed to save budget.');
        }
    }
    header('Location: ' . APP_BASE . '/budget.php'); exit;
}

// Data Fetching
$budgetOverall = dbQueryOne('SELECT amount, alert_threshold FROM budgets WHERE user_id=? AND category_id IS NULL AND month=? AND year=?', [$userId, $month, $year]);
$budgetAmt = $budgetOverall ? (float)$budgetOverall['amount'] : 3000;

$spentData = dbQueryOne("SELECT COALESCE(SUM(amount),0) AS s FROM expenses WHERE user_id=? AND status='completed' AND EXTRACT(MONTH FROM expense_date)=? AND EXTRACT(YEAR FROM expense_date)=?", [$userId, $month, $year]);
$totalSpent = (float)($spentData['s'] ?? 0);

$remaining = $budgetAmt - $totalSpent;
$usagePct = $budgetAmt > 0 ? round(($totalSpent / $budgetAmt) * 100, 1) : 0;
$thresh = $budgetOverall ? (int)$budgetOverall['alert_threshold'] : 80;

$catBudgets = dbQuery("SELECT c.name, c.emoji, c.color, b.amount AS budget_amt, COALESCE(SUM(e.amount),0) AS spent
     FROM categories c
     LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = ? AND b.month = ? AND b.year = ?
     LEFT JOIN expenses e ON e.category_id = c.id AND e.user_id = ? AND e.status = 'completed' AND EXTRACT(MONTH FROM e.expense_date) = ? AND EXTRACT(YEAR FROM e.expense_date) = ?
     WHERE c.user_id = ?
     GROUP BY c.id, c.name, c.emoji, c.color, b.amount 
     ORDER BY spent DESC",
    [$userId, $month, $year, $userId, $month, $year, $userId]);

$categories = dbQuery('SELECT id, name, emoji FROM categories WHERE user_id=? ORDER BY sort_order, name', [$userId]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hd"><div class="page-title"><i class="bi bi-wallet2" style="color:var(--c-green)"></i> Monthly Budget</div></div>

<?php if ($usagePct >= $thresh): ?>
<div class="alert-banner">
    <span style="font-size:22px">⚠</span>
    <div>
        <div style="font-weight:600; color:var(--c-amber)">Budget Alert</div>
        <div style="color:var(--text2); font-size:12px">You've used <?= $usagePct ?>% of your <?= $months[$month] ?> budget.</div>
    </div>
</div>
<?php endif; ?>

<div class="g2" style="margin-bottom:20px">
  <div class="card-g">
    <div class="card-head"><div class="card-title">Set Budget</div></div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="fc-label">Category <small style="color:var(--text3)">(optional)</small></label>
            <select class="fc" name="category_id">
                <option value="">— Overall Monthly Budget —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= h($c['id']) ?>"><?= h($c['emoji'].' '.$c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="g2 mb-3">
          <div><label class="fc-label">Month</label><select class="fc" name="month"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= $months[$m] ?></option><?php endfor; ?></select></div>
          <div><label class="fc-label">Year</label><select class="fc" name="year"><?php for($y=$year-1;$y<=$year+2;$y++): ?><option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
        </div>
        <div class="mb-3"><label class="fc-label">Budget Amount (PHP ₱)</label><div class="input-wrap"><span class="input-prefix">₱</span><input class="fc has-prefix" type="number" name="amount" value="<?= $budgetAmt ?>" min="1" step="1"></div></div>
        <div class="mb-3"><label class="fc-label">Alert at <span id="thresh-val"><?= $thresh ?></span>% usage</label><input type="range" class="form-range" name="alert_threshold" id="thresh-range" min="50" max="100" value="<?= $thresh ?>" oninput="document.getElementById('thresh-val').textContent=this.value" style="accent-color:var(--c-amber)"></div>
        <button type="submit" class="btn-glow" style="width:auto; padding:11px 22px"><i class="bi bi-check-circle"></i> Save Budget</button>
      </form>
    </div>
  </div>

  <div class="card-g">
    <div class="card-head"><div class="card-title">Overview — <?= $months[$month] ?> <?= $year ?></div></div>
    <div class="card-body">
      <div style="text-align:center; margin-bottom:18px">
        <div style="font-size:11px; color:var(--text3); margin-bottom:4px">Remaining Budget</div>
        <div style="font-family:'Syne',sans-serif; font-size:34px; font-weight:800; color:<?= $remaining < 0 ? 'var(--c-red)' : 'var(--c-cyan)' ?>">
            <?= $remaining < 0 ? '-' : '' ?>₱<?= number_format(abs($remaining), 2) ?>
        </div>
        <div style="font-size:12px; color:var(--text3); margin-top:4px">₱<?= number_format($totalSpent, 2) ?> spent of ₱<?= number_format($budgetAmt, 2) ?></div>
      </div>

      <div class="bbar-wrap">
        <div class="bbar-lbl"><span>Overall</span><span style="color:<?= $usagePct >= $thresh ? '#f59e0b' : 'var(--c-cyan)' ?>; font-weight:600"><?= $usagePct ?>%</span></div>
        <div class="bbar"><div class="bbar-fill" style="width:<?= min(100, $usagePct) ?>%; background:<?= $usagePct >= 90 ? '#f87171' : ($usagePct >= $thresh ? '#f59e0b' : 'linear-gradient(90deg,var(--c-cyan),var(--c-purple))') ?>"></div></div>
      </div>

      <?php foreach (array_filter($catBudgets, fn($c) => (float)$c['spent'] > 0) as $cb): 
        $cp = $cb['budget_amt'] > 0 ? min(100, round(($cb['spent'] / $cb['budget_amt']) * 100)) : 0; ?>
        <div class="bbar-wrap">
          <div class="bbar-lbl">
            <span style="font-size:12px"><?= h($cb['emoji'].' '.$cb['name']) ?></span>
            <span style="font-size:11px; color:<?= h($cb['color']) ?>; font-weight:600">
                ₱<?= number_format((float)$cb['spent'], 0) ?>
                <?= $cb['budget_amt'] > 0 ? ' / ₱' . number_format((float)$cb['budget_amt'], 0) : '' ?>
            </span>
          </div>
          <div class="bbar"><div class="bbar-fill" style="width:<?= $cp ?>%; background:<?= h($cb['color']) ?>; opacity:.85"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>