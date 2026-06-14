<?php
// dashboard.php — Main dashboard
require_once __DIR__ . '/config/auth.php';
$user     = requireAuth();
$userId   = currentUserId();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$now   = new DateTime();
$month = (int)$now->format('n');
$year  = (int)$now->format('Y');
$search = getStr('q');

// ── Stats ──────────────────────────────────────
$stats = dbQueryOne(
    "SELECT
       COUNT(*)                                             AS total_count,
       COALESCE(SUM(amount) FILTER (WHERE status='completed'), 0) AS total_spent,
       COUNT(*) FILTER (WHERE status='completed')           AS completed_count,
       COUNT(*) FILTER (WHERE status='pending')             AS pending_count
     FROM expenses
     WHERE user_id = ?
       AND EXTRACT(MONTH FROM expense_date) = ?
       AND EXTRACT(YEAR  FROM expense_date) = ?",
    [$userId, $month, $year]
);

$prevStats = dbQueryOne(
    "SELECT COALESCE(SUM(amount),0) AS prev_spent
     FROM expenses
     WHERE user_id = ? AND status='completed'
       AND EXTRACT(MONTH FROM expense_date) = ?
       AND EXTRACT(YEAR  FROM expense_date) = ?",
    [$userId, $month == 1 ? 12 : $month - 1, $month == 1 ? $year - 1 : $year]
);

$budget = dbQueryOne(
    'SELECT amount, alert_threshold FROM budgets WHERE user_id = ? AND category_id IS NULL AND month = ? AND year = ?',
    [$userId, $month, $year]
);
$budgetAmt = $budget ? (float)$budget['amount'] : 3000.00;
$remaining = $budgetAmt - (float)$stats['total_spent'];
$usagePct  = $budgetAmt > 0 ? round(($stats['total_spent'] / $budgetAmt) * 100, 1) : 0;

// MoM change
$prevSpent  = (float)($prevStats['prev_spent'] ?? 0);
$momChange  = $prevSpent > 0 ? round((($stats['total_spent'] - $prevSpent) / $prevSpent) * 100, 1) : 0;

// ── Monthly trend (12 months) ──────────────────
$trend = dbQuery(
    "SELECT EXTRACT(MONTH FROM expense_date) AS m,
            EXTRACT(YEAR  FROM expense_date) AS y,
            COALESCE(SUM(amount),0) AS total
     FROM expenses
     WHERE user_id = ? AND status='completed'
       AND expense_date >= NOW() - INTERVAL '12 months'
     GROUP BY m, y ORDER BY y, m",
    [$userId]
);

// ── Category breakdown this month ─────────────
$catBreakdown = dbQuery(
    "SELECT c.name, c.emoji, c.color,
            COALESCE(SUM(e.amount),0) AS total,
            COUNT(e.id)               AS cnt
     FROM categories c
     LEFT JOIN expenses e ON e.category_id = c.id
       AND e.user_id = ? AND e.status='completed'
       AND EXTRACT(MONTH FROM e.expense_date) = ?
       AND EXTRACT(YEAR  FROM e.expense_date) = ?
     WHERE c.user_id = ?
     GROUP BY c.id, c.name, c.emoji, c.color
     HAVING COALESCE(SUM(e.amount),0) > 0
     ORDER BY total DESC",
    [$userId, $month, $year, $userId]
);

// ── Recent transactions (with optional search) ─
$limit = 10;
$page  = max(1, getInt('page', 1));
$offset = ($page - 1) * $limit;
$catFilter  = getStr('cat');
$statFilter = getStr('status');

$where  = 'e.user_id = ?';
$params = [$userId];
if ($search)     { $where .= " AND (e.description ILIKE ? OR c.name ILIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catFilter)  { $where .= ' AND c.name = ?'; $params[] = $catFilter; }
if ($statFilter) { $where .= ' AND e.status = ?'; $params[] = $statFilter; }

$totalRows = dbQueryOne("SELECT COUNT(*) AS n FROM expenses e JOIN categories c ON c.id = e.category_id WHERE $where", $params)['n'];
$transactions = dbQuery(
    "SELECT e.id, e.description, e.amount, e.expense_date, e.payment_method,
            e.status, e.notes, e.receipt_url, e.is_recurring, e.tags,
            c.name AS cat_name, c.emoji AS cat_emoji, c.color AS cat_color
     FROM expenses e
     JOIN categories c ON c.id = e.category_id
     WHERE $where
     ORDER BY e.expense_date DESC, e.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);
$totalPages = max(1, ceil($totalRows / $limit));

// ── Budget alert ───────────────────────────────
$alertThreshold = $budget ? (int)$budget['alert_threshold'] : 80;
$showBudgetAlert = $usagePct >= $alertThreshold;

// ── Categories list for filter ─────────────────
$allCats = dbQuery('SELECT name FROM categories WHERE user_id = ? ORDER BY sort_order', [$userId]);

require_once __DIR__ . '/includes/header.php';

// Build chart data JSON
$trendLabels = []; $trendData = [];
$months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
foreach ($trend as $t) { $trendLabels[] = $months[(int)$t['m']]; $trendData[] = round((float)$t['total'], 2); }
$catLabels = []; $catData = []; $catColors = [];
foreach ($catBreakdown as $c) { $catLabels[] = $c['emoji'].' '.explode(' ',$c['name'])[0]; $catData[] = round((float)$c['total'],2); $catColors[] = $c['color'].'b0'; }
?>

<?php if ($showBudgetAlert): ?>
<div class="alert-banner">
  <span style="font-size:22px">⚠</span>
  <div>
    <div style="font-weight:600;color:var(--c-amber)">Budget Alert</div>
    <div style="color:var(--text2);font-size:12px">You've used <?= $usagePct ?>% of your <?= $months[$month] ?> budget (₱<?= number_format($stats['total_spent'],2) ?> of ₱<?= number_format($budgetAmt,2) ?>).</div>
  </div>
</div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stat-grid">
  <div class="stat-c s1">
    <div class="stat-ico"><i class="bi bi-currency-dollar"></i></div>
    <div class="stat-lbl">Total Expenses</div>
    <div class="stat-val">₱<?= number_format($stats['total_spent'],0) ?></div>
    <div class="stat-chg <?= $momChange >= 0 ? 'up' : 'down' ?>">
      <i class="bi bi-arrow-<?= $momChange >= 0 ? 'up' : 'down' ?>-short"></i>
      <?= abs($momChange) ?>% vs last month
    </div>
  </div>
  <div class="stat-c s2">
    <div class="stat-ico"><i class="bi bi-shield-check"></i></div>
    <div class="stat-lbl">Monthly Budget</div>
    <div class="stat-val">₱<?= number_format($budgetAmt,0) ?></div>
    <div class="stat-chg neutral"><?= $now->format('F Y') ?></div>
  </div>
  <div class="stat-c s3">
    <div class="stat-ico"><i class="bi bi-piggy-bank"></i></div>
    <div class="stat-lbl">Remaining</div>
    <div class="stat-val <?= $remaining < 0 ? 'down' : '' ?>">₱<?= number_format(max(0,$remaining),0) ?></div>
    <div class="stat-chg <?= $remaining < 0 ? 'down' : 'up' ?>">
      <i class="bi bi-arrow-<?= $remaining < 0 ? 'down' : 'up' ?>-short"></i>
      <?= round(100 - $usagePct, 1) ?>% budget left
    </div>
  </div>
  <div class="stat-c s4">
    <div class="stat-ico"><i class="bi bi-receipt"></i></div>
    <div class="stat-lbl">Transactions</div>
    <div class="stat-val"><?= number_format($stats['total_count']) ?></div>
    <div class="stat-chg neutral"><?= $stats['pending_count'] ?> pending</div>
  </div>
</div>

<!-- CHARTS ROW -->
<div class="g-main" style="margin-bottom:18px">
  <div class="card-g">
    <div class="card-head">
      <div class="card-title"><i class="bi bi-graph-up" style="color:var(--c-cyan)"></i> Monthly Trend</div>
      <span class="tag" style="background:rgba(0,229,255,.1);color:var(--c-cyan)"><?= $year ?></span>
    </div>
    <div class="card-body"><div class="ch-wrap" style="height:230px"><canvas id="ch-trend"></canvas></div></div>
  </div>
  <div class="card-g">
    <div class="card-head"><div class="card-title"><i class="bi bi-pie-chart" style="color:var(--c-purple)"></i> Distribution</div></div>
    <div class="card-body">
      <div class="ch-wrap" style="height:185px"><canvas id="ch-donut"></canvas></div>
      <div id="donut-leg" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px"></div>
    </div>
  </div>
</div>

<!-- AI + CATEGORY BAR -->
<div class="g2" style="margin-bottom:18px">
  <div class="ai-panel">
    <div style="display:flex;align-items:center;gap:11px;margin-bottom:12px">
      <div class="ai-ico">✦</div>
      <div><div style="font-size:13.5px;font-weight:600">AI Spending Insight</div><div style="font-size:11px;color:var(--text3)">Powered by Claude</div></div>
      <button class="btn-outline btn-sm" style="margin-left:auto" onclick="getInsight()"><i class="bi bi-stars"></i> Analyze</button>
    </div>
    <div class="ai-txt" id="ai-dash">Click <strong style="color:var(--c-purple)">Analyze</strong> for personalized AI insights about your spending.</div>
  </div>
  <div class="card-g">
    <div class="card-head"><div class="card-title"><i class="bi bi-bar-chart" style="color:var(--c-amber)"></i> By Category</div></div>
    <div class="card-body"><div class="ch-wrap" style="height:200px"><canvas id="ch-cat"></canvas></div></div>
  </div>
</div>

<!-- TRANSACTIONS TABLE -->
<div class="card-g">
  <div class="card-head">
    <div class="card-title"><i class="bi bi-clock-history" style="color:var(--c-amber)"></i> Transactions</div>
    <form method="GET" action="<?= APP_BASE ?>/dashboard.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div style="position:relative">
        <i class="bi bi-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:12px;pointer-events:none"></i>
        <input class="fc" style="padding-left:28px;padding-top:7px;padding-bottom:7px;width:170px" name="q" value="<?= h($search) ?>" placeholder="Search…">
      </div>
      <select class="fc" style="width:130px;padding:7px 28px 7px 10px" name="cat">
        <option value="">All Categories</option>
        <?php foreach ($allCats as $ac): ?>
        <option value="<?= h($ac['name']) ?>" <?= $catFilter===$ac['name']?'selected':'' ?>><?= h($ac['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="fc" style="width:120px;padding:7px 28px 7px 10px" name="status">
        <option value="">All Status</option>
        <option value="completed" <?= $statFilter==='completed'?'selected':'' ?>>Completed</option>
        <option value="pending"   <?= $statFilter==='pending'?'selected':'' ?>>Pending</option>
        <option value="failed"    <?= $statFilter==='failed'?'selected':'' ?>>Failed</option>
        <option value="refunded"  <?= $statFilter==='refunded'?'selected':'' ?>>Refunded</option>
      </select>
      <button type="submit" class="btn-outline btn-sm"><i class="bi bi-funnel"></i> Filter</button>
      <?php if ($search || $catFilter || $statFilter): ?>
      <a href="<?= APP_BASE ?>/dashboard.php" class="btn-outline btn-sm"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tbl-wrap">
    <table class="tbl" id="txn-tbl">
      <thead>
        <tr>
          <th onclick="sortTable('txn-tbl',0)" class="sortable">#</th>
          <th>Category</th>
          <th onclick="sortTable('txn-tbl',2)" class="sortable">Description</th>
          <th onclick="sortTable('txn-tbl',3)" class="sortable">Amount</th>
          <th onclick="sortTable('txn-tbl',4)" class="sortable">Date</th>
          <th>Method</th>
          <th>Status</th>
          <th>Receipt</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
        <tr><td colspan="9" style="text-align:center;padding:28px;color:var(--text3)">
          <?= $search ? 'No results for "'.h($search).'"' : 'No transactions yet. <a href="<?= APP_BASE ?>/add_expense.php" style="color:var(--c-cyan)">Add your first</a>!' ?>
        </td></tr>
        <?php else: foreach ($transactions as $txn):
          $methodMap = ['credit_card'=>'Credit Card','debit_card'=>'Debit Card','bank_transfer'=>'Bank Transfer','cash'=>'Cash','digital_wallet'=>'Digital Wallet','crypto'=>'Crypto','other'=>'Other'];
        ?>
        <tr>
          <td style="font-size:10px;color:var(--text3);font-family:monospace"><?= substr(h($txn['id']),0,8) ?></td>
          <td><span class="badge-cat"><?= h($txn['cat_emoji']) ?> <?= h($txn['cat_name']) ?></span></td>
          <td style="max-width:190px">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($txn['description']) ?></div>
            <?php if (!empty($txn['tags'])): $tags = is_array($txn['tags']) ? $txn['tags'] : json_decode($txn['tags'],true); ?>
            <div><?php foreach((array)$tags as $tag): ?><span class="tag-pill"><?= h($tag) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($txn['is_recurring']): ?><span class="recurring-badge"><i class="bi bi-arrow-repeat"></i> Recurring</span><?php endif; ?>
          </td>
          <td style="font-weight:600;color:<?= $txn['status']==='refunded'?'var(--c-blue)':($txn['status']==='failed'?'var(--c-red)':'var(--text1)') ?>">
            ₱<?= number_format((float)$txn['amount'],2) ?>
          </td>
          <td style="color:var(--text3);white-space:nowrap"><?= h($txn['expense_date']) ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= h($methodMap[$txn['payment_method']] ?? $txn['payment_method']) ?></td>
          <td><span class="badge-status s-<?= h($txn['status']) ?>">● <?= ucfirst(h($txn['status'])) ?></span></td>
          <td>
            <?php if ($txn['receipt_url']): ?>
            <a href="<?= APP_BASE ?>/<?= h($txn['receipt_url']) ?>" target="_blank" title="View receipt"><img src="/<?= h($txn['receipt_url']) ?>" class="receipt-thumb" alt="Receipt"></a>
            <?php else: ?><span style="color:var(--text3);font-size:11px">—</span><?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <a href="<?= APP_BASE ?>/edit_expense.php?id=<?= h($txn['id']) ?>" class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="POST" action="<?= APP_BASE ?>/delete_expense.php" style="display:inline" id="del-<?= h($txn['id']) ?>">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= h($txn['id']) ?>">
              <input type="hidden" name="redirect" value="/dashboard.php">
              <button type="button" class="btn-icon del" title="Delete"
                onclick="confirmDelete('Delete this transaction? This cannot be undone.','del-<?= h($txn['id']) ?>')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pg-wrap">
    <span class="pg-info">Showing <?= min($limit, $totalRows) ?> of <?= $totalRows ?> transactions</span>
    <div class="pg-btns">
      <button class="pg-btn" <?= $page<=1?'disabled':'' ?> onclick="location='?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($catFilter) ?>&status=<?= urlencode($statFilter) ?>'">← Prev</button>
      <?php for ($p=1; $p<=$totalPages; $p++): if ($totalPages<=6 || $p===1 || $p===$totalPages || abs($p-$page)<=1): ?>
      <button class="pg-btn <?= $p===$page?'active-pg':'' ?>" onclick="location='?page=<?= $p ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($catFilter) ?>&status=<?= urlencode($statFilter) ?>'"><?= $p ?></button>
      <?php elseif (abs($p-$page)===2): ?><span style="color:var(--text3);padding:5px 3px">…</span><?php endif; endfor; ?>
      <button class="pg-btn" <?= $page>=$totalPages?'disabled':'' ?> onclick="location='?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($catFilter) ?>&status=<?= urlencode($statFilter) ?>'">Next →</button>
    </div>
  </div>
</div>

<?php
$extraJs = "
const TREND_LABELS  = " . json_encode($trendLabels) . ";
const TREND_DATA    = " . json_encode($trendData)   . ";
const CAT_LABELS    = " . json_encode($catLabels)   . ";
const CAT_DATA      = " . json_encode($catData)     . ";
const CAT_COLORS    = " . json_encode($catColors)   . ";
const AI_CONTEXT    = " . json_encode("Month: {$months[$month]} {$year}. Budget: \${$budgetAmt}. Spent: \${$stats['total_spent']} ({$usagePct}%). Transactions: {$stats['total_count']}.") . ";

document.addEventListener('DOMContentLoaded', () => {
  // Trend chart
  const tCtx = document.getElementById('ch-trend').getContext('2d');
  const grad = tCtx.createLinearGradient(0,0,0,230);
  grad.addColorStop(0,'rgba(0,229,255,.25)'); grad.addColorStop(1,'rgba(0,229,255,0)');
  makeChart('ch-trend',{type:'line',data:{labels:TREND_LABELS,datasets:[{data:TREND_DATA,borderColor:'#00e5ff',backgroundColor:grad,borderWidth:2,pointBackgroundColor:'#00e5ff',pointRadius:4,pointHoverRadius:7,tension:.4,fill:true,spanGaps:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>'₱'+ctx.raw.toFixed(2)}}},scales:{x:{...CHART_SCALE_OPTS.x},y:{...CHART_SCALE_OPTS.y,ticks:{...CHART_SCALE_OPTS.y.ticks,callback:v=>'₱'+v.toLocaleString()}}}}});

  // Donut chart
  const donutTotal = CAT_DATA.reduce((s,v)=>s+v,0);
  makeChart('ch-donut',{type:'doughnut',data:{labels:CAT_LABELS,datasets:[{data:CAT_DATA,backgroundColor:CAT_COLORS,borderWidth:2,borderColor:'#05080f',hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>` \${ctx.label}: \$\${ctx.raw.toFixed(2)} (\${donutTotal>0?((ctx.raw/donutTotal)*100).toFixed(1):0}%)`}}}}});
  const legEl = document.getElementById('donut-leg');
  if(legEl) legEl.innerHTML=CAT_LABELS.map((l,i)=>`<span style=\"display:flex;align-items:center;gap:4px;font-size:11px;color:#94a3b8\"><span style=\"width:9px;height:9px;border-radius:2px;background:\${CAT_COLORS[i].slice(0,7)}\"></span>\${l}</span>`).join('');

  // Category bar
  makeChart('ch-cat',{type:'bar',data:{labels:CAT_LABELS,datasets:[{data:CAT_DATA,backgroundColor:CAT_COLORS,borderRadius:6,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>'₱'+ctx.raw.toFixed(2)}}},scales:{x:{...CHART_SCALE_OPTS.x,ticks:{...CHART_SCALE_OPTS.x.ticks,callback:v=>'₱'+v}},y:{...CHART_SCALE_OPTS.y,grid:{color:'rgba(255,255,255,.03)'}}}}});
});

async function getInsight() {
  await callClaude(
    'You are a personal finance advisor. Give 3 concise bullet-point insights (plain text only, no markdown).',
    'Analyze: ' + AI_CONTEXT,
    { outputEl: 'ai-dash', maxTokens: 600 }
  );
}
";
require_once __DIR__ . '/includes/footer.php';
