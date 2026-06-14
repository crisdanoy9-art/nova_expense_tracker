<?php
// calendar.php — Interactive expense calendar
require_once __DIR__ . '/config/auth.php';
$user       = requireAuth();
$userId     = currentUserId();
$pageTitle  = 'Expense Calendar';
$activePage = 'calendar';

$now       = new DateTime();
$viewYear  = getInt('y', (int)$now->format('Y'));
$viewMonth = getInt('m', (int)$now->format('n') - 1); // 0-based for JS
if ($viewMonth < 0)  { $viewMonth = 11; $viewYear--; }
if ($viewMonth > 11) { $viewMonth = 0;  $viewYear++; }

$phpMonth = $viewMonth + 1; // 1-based for SQL

// All expenses for this month
$monthExpenses = dbQuery(
    "SELECT e.id, e.description, e.amount, e.expense_date, e.status, e.payment_method,
            c.name AS cat_name, c.emoji AS cat_emoji, c.color AS cat_color
     FROM expenses e
     JOIN categories c ON c.id = e.category_id
     WHERE e.user_id = ?
       AND EXTRACT(MONTH FROM e.expense_date) = ?
       AND EXTRACT(YEAR  FROM e.expense_date) = ?
     ORDER BY e.expense_date, e.created_at",
    [$userId, $phpMonth, $viewYear]
);

// Build day map
$dayMap = [];
foreach ($monthExpenses as $exp) {
    $d = (int)date('j', strtotime($exp['expense_date']));
    if (!isset($dayMap[$d])) $dayMap[$d] = ['total'=>0,'count'=>0,'colors'=>[],'expenses'=>[]];
    if ($exp['status'] !== 'failed') {
        $dayMap[$d]['total'] += (float)$exp['amount'];
        if (!in_array($exp['cat_color'], $dayMap[$d]['colors'])) $dayMap[$d]['colors'][] = $exp['cat_color'];
    }
    $dayMap[$d]['count']++;
    $dayMap[$d]['expenses'][] = $exp;
}

// Month summary
$monthTotal = array_sum(array_column($monthExpenses, 'amount'));
$txnCount   = count($monthExpenses);
$activeDays = count($dayMap);
$dailyAvg   = $activeDays > 0 ? $monthTotal / $activeDays : 0;

// Budget for this month
$budget = dbQueryOne('SELECT amount FROM budgets WHERE user_id=? AND category_id IS NULL AND month=? AND year=?', [$userId,$phpMonth,$viewYear]);
$budgetAmt = $budget ? (float)$budget['amount'] : 3000;

// Month names
$monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$prevM = $viewMonth === 0 ? ['m'=>11,'y'=>$viewYear-1] : ['m'=>$viewMonth-1,'y'=>$viewYear];
$nextM = $viewMonth === 11 ? ['m'=>0,'y'=>$viewYear+1] : ['m'=>$viewMonth+1,'y'=>$viewYear];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hd">
  <div class="page-title"><i class="bi bi-calendar3" style="color:var(--c-cyan)"></i> Expense Calendar</div>
  <a href="<?= APP_BASE ?>/add_expense.php" class="btn-glow" style="width:auto;padding:9px 18px"><i class="bi bi-plus"></i> Add Expense</a>
</div>

<!-- Calendar -->
<div class="card-g" style="margin-bottom:18px">
  <div class="card-body">
    <div class="cal-nav">
      <a href="?y=<?= $prevM['y'] ?>&m=<?= $prevM['m'] ?>" class="btn-outline btn-sm"><i class="bi bi-chevron-left"></i> Prev</a>
      <div class="cal-month-title"><?= $monthNames[$viewMonth] ?> <?= $viewYear ?></div>
      <a href="?y=<?= $nextM['y'] ?>&m=<?= $nextM['m'] ?>" class="btn-outline btn-sm">Next <i class="bi bi-chevron-right"></i></a>
    </div>

    <div class="cal-grid" id="cal-grid">
      <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
      <div class="cal-dh"><?= $dh ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Selected day detail panel (shown via JS) -->
<div id="cal-detail" style="display:none">
  <div class="cal-detail">
    <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
      <span id="detail-date-title">—</span>
      <a href="<?= APP_BASE ?>/add_expense.php" class="btn-glow btn-sm" style="width:auto"><i class="bi bi-plus"></i> Add</a>
    </div>
    <div id="detail-body"></div>
  </div>
</div>

<!-- Month summary bar -->
<div class="card-g" style="margin-top:18px">
  <div class="card-head"><div class="card-title">Month Summary — <?= $monthNames[$viewMonth] ?> <?= $viewYear ?></div></div>
  <div class="card-body">
    <div class="g3" style="margin-bottom:16px">
      <div style="text-align:center;padding:12px;background:rgba(0,229,255,.05);border:1px solid rgba(0,229,255,.1);border-radius:var(--r-md)">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px">Total Spent</div>
        <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:700;color:var(--c-cyan)">₱<?= number_format($monthTotal,2) ?></div>
      </div>
      <div style="text-align:center;padding:12px;background:rgba(168,85,247,.05);border:1px solid rgba(168,85,247,.1);border-radius:var(--r-md)">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px">Transactions</div>
        <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:700;color:var(--c-purple)"><?= $txnCount ?></div>
      </div>
      <div style="text-align:center;padding:12px;background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.1);border-radius:var(--r-md)">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px">Daily Average</div>
        <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:700;color:var(--c-green)">₱<?= number_format($dailyAvg,2) ?></div>
      </div>
    </div>
    <!-- Budget bar -->
    <div class="bbar-wrap">
      <div class="bbar-lbl">
        <span>Budget usage — ₱<?= number_format($monthTotal,2) ?> of ₱<?= number_format($budgetAmt,2) ?></span>
        <span style="color:<?= $monthTotal/$budgetAmt>=.9?'#f87171':($monthTotal/$budgetAmt>=.75?'#f59e0b':'var(--c-cyan)') ?>;font-weight:600"><?= round($monthTotal/$budgetAmt*100,1) ?>%</span>
      </div>
      <div class="bbar">
        <div class="bbar-fill" style="width:<?= min(100,round($monthTotal/$budgetAmt*100,1)) ?>%;background:linear-gradient(90deg,var(--c-cyan),var(--c-purple))"></div>
      </div>
    </div>
  </div>
</div>

<?php
$dayMapJson    = json_encode($dayMap);
$monthNamesJs  = json_encode($monthNames);
$currentYearJs = $viewYear;
$currentMonthJs = $viewMonth;

$extraJs = "
const DAY_MAP     = $dayMapJson;
const MONTH_NAMES = $monthNamesJs;
const CAL_YEAR    = $currentYearJs;
const CAL_MONTH   = $currentMonthJs;

document.addEventListener('DOMContentLoaded', () => {
  renderCalendar(CAL_YEAR, CAL_MONTH, DAY_MAP, (day, dateStr, data) => {
    showDayDetail(day, dateStr, data);
  });
});

function showDayDetail(day, dateStr, data) {
  const title = document.getElementById('detail-date-title');
  const body  = document.getElementById('detail-body');
  const panel = document.getElementById('cal-detail');
  title.textContent = MONTH_NAMES[CAL_MONTH] + ' ' + day + ', ' + CAL_YEAR;
  if (!data || !data.expenses || data.expenses.length === 0) {
    body.innerHTML = '<div style=\"color:var(--text3);font-size:13px;text-align:center;padding:16px 0\">No expenses on this day. <a href=\"/add_expense.php\" style=\"color:var(--c-cyan)\">Add one?</a></div>';
  } else {
    body.innerHTML = data.expenses.map(e => `
      <div style=\"display:flex;align-items:center;gap:11px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.04)\">
        <span style=\"font-size:20px\">\${e.cat_emoji}</span>
        <div style=\"flex:1;min-width:0\">
          <div style=\"font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis\">\${e.description}</div>
          <div style=\"font-size:11px;color:var(--text3)\">\${e.cat_name} · \${e.payment_method.replace(/_/g,' ')}</div>
        </div>
        <div style=\"text-align:right;flex-shrink:0\">
          <div style=\"font-size:14px;font-weight:700;color:\${e.cat_color}\">\$\${parseFloat(e.amount).toFixed(2)}</div>
          <span class=\"badge-status s-\${e.status}\" style=\"font-size:10px\">● \${e.status}</span>
        </div>
        <a href=\"/edit_expense.php?id=\${e.id}\" class=\"btn-icon edit\" title=\"Edit\"><i class=\"bi bi-pencil\"></i></a>
      </div>`).join('') +
      `<div style=\"text-align:right;padding-top:10px;font-size:13px;font-weight:600\">
        Day total: <span style=\"color:var(--c-cyan)\">\$\${data.total.toFixed(2)}</span>
      </div>`;
  }
  panel.style.display = 'block';
  panel.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
";
require_once __DIR__ . '/includes/footer.php';
