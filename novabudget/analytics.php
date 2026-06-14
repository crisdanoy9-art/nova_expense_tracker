<?php
// analytics.php — Deep analytics & AI Q&A
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Analytics'; $activePage = 'analytics';
$now = new DateTime();
$year = (int)$now->format('Y');
$month = (int)$now->format('n');

// Lifetime stats
$life = dbQueryOne(
    "SELECT COUNT(*) AS txn_count, COALESCE(SUM(amount),0) AS total,
            COALESCE(AVG(amount),0) AS avg_txn,
            COALESCE(MAX(amount),0) AS max_txn,
            COALESCE(MIN(amount) FILTER(WHERE status='completed'),0) AS min_txn
     FROM expenses WHERE user_id=? AND status='completed'",
    [$userId]
);

// Year-over-year monthly data
$yoy = dbQuery(
    "SELECT EXTRACT(YEAR FROM expense_date)::INT AS yr,
            EXTRACT(MONTH FROM expense_date)::INT AS mo,
            COALESCE(SUM(amount),0) AS total
     FROM expenses WHERE user_id=? AND status='completed'
       AND expense_date >= NOW()-INTERVAL '2 years'
     GROUP BY yr,mo ORDER BY yr,mo",
    [$userId]
);
$yoyThis  = array_fill(1,12,0);
$yoyPrev  = array_fill(1,12,0);
foreach ($yoy as $r) {
    if ((int)$r['yr'] === $year)     $yoyThis[(int)$r['mo']] = round((float)$r['total'],2);
    if ((int)$r['yr'] === $year - 1) $yoyPrev[(int)$r['mo']] = round((float)$r['total'],2);
}

// Weekday distribution
$weekdayData = dbQuery(
    "SELECT TO_CHAR(expense_date,'Dy') AS wd,
            EXTRACT(DOW FROM expense_date)::INT AS dow,
            COALESCE(SUM(amount),0) AS total
     FROM expenses WHERE user_id=? AND status='completed'
     GROUP BY wd, dow ORDER BY dow",
    [$userId]
);

// Daily spending last 30 days (velocity)
$velocity = dbQuery(
    "SELECT expense_date::text AS day, COALESCE(SUM(amount),0) AS total
     FROM expenses WHERE user_id=? AND status='completed'
       AND expense_date >= CURRENT_DATE - INTERVAL '30 days'
     GROUP BY expense_date ORDER BY expense_date",
    [$userId]
);
$velLabels=[]; $velData=[];
// Fill all 30 days
for ($i=29;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $velLabels[] = date('M j', strtotime($d));
    $found = array_filter($velocity, fn($r)=>$r['day']===$d);
    $velData[]  = $found ? round((float)array_values($found)[0]['total'],2) : 0;
}

// Top category this month
$topCat = dbQueryOne(
    "SELECT c.name, SUM(e.amount) AS total FROM expenses e
     JOIN categories c ON c.id=e.category_id
     WHERE e.user_id=? AND e.status='completed'
       AND EXTRACT(MONTH FROM e.expense_date)=? AND EXTRACT(YEAR FROM e.expense_date)=?
     GROUP BY c.name ORDER BY total DESC LIMIT 1",
    [$userId,$month,$year]
);

// Payment method breakdown
$payMethods = dbQuery(
    "SELECT payment_method, COUNT(*) AS cnt, SUM(amount) AS total
     FROM expenses WHERE user_id=? AND status='completed'
     GROUP BY payment_method ORDER BY total DESC",
    [$userId]
);

// Build AI context
$catTotals = dbQuery(
    "SELECT c.name, COALESCE(SUM(e.amount),0) AS total FROM categories c
     LEFT JOIN expenses e ON e.category_id=c.id AND e.user_id=? AND e.status='completed'
     WHERE c.user_id=? GROUP BY c.name ORDER BY total DESC LIMIT 6",
    [$userId,$userId]
);
$aiContext = "Total spent all-time: $" . number_format((float)$life['total'],2) .
    ". Transactions: {$life['txn_count']}. Avg transaction: $" . number_format((float)$life['avg_txn'],2) .
    ". This month: " . $now->format('F Y') . ". Top categories: " .
    implode(', ', array_map(fn($c)=>"{$c['name']} \${$c['total']}", $catTotals));

$months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$mLabels = array_slice($months,1);

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-hd"><div class="page-title"><i class="bi bi-graph-up-arrow" style="color:var(--c-purple)"></i> Analytics</div></div>

<!-- AI Q&A -->
<div class="ai-panel" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:11px;margin-bottom:12px">
    <div class="ai-ico">✦</div>
    <div><div style="font-size:13.5px;font-weight:600">Deep AI Analysis</div><div style="font-size:11px;color:var(--text3)">Ask anything about your finances</div></div>
  </div>
  <div style="display:flex;gap:9px">
    <input class="fc" id="ai-q" placeholder='e.g. "Which category should I cut?" or "How to save $500/month?"' style="flex:1">
    <button class="btn-glow" style="width:auto;padding:10px 18px;white-space:nowrap" onclick="askAI()">
      <i class="bi bi-stars"></i> Ask AI
    </button>
  </div>
  <div class="ai-txt" id="ai-an" style="margin-top:12px;display:none"></div>
</div>

<!-- Stats -->
<div class="stat-grid" style="margin-bottom:20px">
  <div class="stat-c s1">
    <div class="stat-ico"><i class="bi bi-currency-dollar"></i></div>
    <div class="stat-lbl">Lifetime Total</div>
    <div class="stat-val">₱<?= number_format((float)$life['total'],0) ?></div>
    <div class="stat-chg neutral"><?= number_format($life['txn_count']) ?> transactions</div>
  </div>
  <div class="stat-c s2">
    <div class="stat-ico"><i class="bi bi-calculator"></i></div>
    <div class="stat-lbl">Avg Transaction</div>
    <div class="stat-val">₱<?= number_format((float)$life['avg_txn'],2) ?></div>
    <div class="stat-chg neutral">per expense</div>
  </div>
  <div class="stat-c s3">
    <div class="stat-ico"><i class="bi bi-trophy"></i></div>
    <div class="stat-lbl">Top Category</div>
    <div class="stat-val" style="font-size:17px"><?= h($topCat['name'] ?? '—') ?></div>
    <div class="stat-chg neutral">this month</div>
  </div>
  <div class="stat-c s4">
    <div class="stat-ico"><i class="bi bi-arrow-up-circle"></i></div>
    <div class="stat-lbl">Largest Expense</div>
    <div class="stat-val">₱<?= number_format((float)$life['max_txn'],2) ?></div>
    <div class="stat-chg neutral">single transaction</div>
  </div>
</div>

<!-- Charts row -->
<div class="g2" style="margin-bottom:20px">
  <div class="card-g">
    <div class="card-head"><div class="card-title">Year-over-Year Comparison</div>
      <div style="display:flex;gap:8px">
        <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#94a3b8"><span style="width:10px;height:3px;background:#00e5ff;border-radius:2px;display:inline-block"></span><?= $year ?></span>
        <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#94a3b8"><span style="width:10px;height:3px;background:rgba(168,85,247,.7);border-radius:2px;display:inline-block"></span><?= $year-1 ?></span>
      </div>
    </div>
    <div class="card-body"><div class="ch-wrap" style="height:230px"><canvas id="ch-yoy"></canvas></div></div>
  </div>
  <div class="card-g">
    <div class="card-head"><div class="card-title">Spending Velocity (30 days)</div></div>
    <div class="card-body"><div class="ch-wrap" style="height:230px"><canvas id="ch-vel"></canvas></div></div>
  </div>
</div>

<div class="g2">
  <div class="card-g">
    <div class="card-head"><div class="card-title">Spending by Day of Week</div></div>
    <div class="card-body"><div class="ch-wrap" style="height:200px"><canvas id="ch-dow"></canvas></div></div>
  </div>
  <div class="card-g">
    <div class="card-head"><div class="card-title">Payment Methods</div></div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Method</th><th>Transactions</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($payMethods as $pm):
            $ml = ['credit_card'=>'💳 Credit Card','debit_card'=>'💳 Debit Card','bank_transfer'=>'🏦 Bank Transfer','cash'=>'💵 Cash','digital_wallet'=>'📱 Digital Wallet','crypto'=>'₿ Crypto','other'=>'Other'];
          ?>
          <tr>
            <td><?= $ml[$pm['payment_method']] ?? h($pm['payment_method']) ?></td>
            <td style="color:var(--text3)"><?= $pm['cnt'] ?></td>
            <td style="font-weight:600;color:var(--c-cyan)">₱<?= number_format((float)$pm['total'],2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraJs = "
const YOY_THIS = " . json_encode(array_values($yoyThis)) . ";
const YOY_PREV = " . json_encode(array_values($yoyPrev)) . ";
const VEL_LABELS=" . json_encode($velLabels) . ";
const VEL_DATA  =" . json_encode($velData)   . ";
const DOW_LABELS=" . json_encode(array_column($weekdayData,'wd'))    . ";
const DOW_DATA  =" . json_encode(array_map(fn($r)=>round((float)$r['total'],2),$weekdayData)) . ";
const M_LABELS  =" . json_encode($mLabels)   . ";
const AI_CTX    =" . json_encode($aiContext)  . ";

document.addEventListener('DOMContentLoaded', () => {
  makeChart('ch-yoy',{type:'line',data:{labels:M_LABELS,datasets:[{label:'" . $year . "',data:YOY_THIS,borderColor:'#00e5ff',backgroundColor:'rgba(0,229,255,.08)',fill:true,tension:.4,borderWidth:2,pointRadius:3},{label:'" . ($year-1) . "',data:YOY_PREV,borderColor:'rgba(168,85,247,.7)',backgroundColor:'rgba(168,85,247,.05)',fill:true,tension:.4,borderWidth:2,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS}},scales:{x:{...CHART_SCALE_OPTS.x},y:{...CHART_SCALE_OPTS.y,ticks:{...CHART_SCALE_OPTS.y.ticks,callback:v=>'₱'+v.toLocaleString()}}}}});
  makeChart('ch-vel',{type:'bar',data:{labels:VEL_LABELS,datasets:[{data:VEL_DATA,backgroundColor:VEL_DATA.map(v=>v>200?'rgba(248,113,113,.7)':v>100?'rgba(245,158,11,.7)':'rgba(34,197,94,.7)'),borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>'₱'+ctx.raw.toFixed(2)}}},scales:{x:{grid:{color:'rgba(255,255,255,.03)'},ticks:{color:'#475569',font:{size:9},maxRotation:0,autoSkip:true,maxTicksLimit:6}},y:{...CHART_SCALE_OPTS.y,ticks:{...CHART_SCALE_OPTS.y.ticks,callback:v=>'₱'+v}}}}});
  makeChart('ch-dow',{type:'bar',data:{labels:DOW_LABELS,datasets:[{data:DOW_DATA,backgroundColor:'rgba(168,85,247,.65)',borderRadius:6,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>'₱'+ctx.raw.toFixed(2)}}},scales:{x:{grid:{color:'rgba(255,255,255,.03)'},ticks:{color:'#475569'}},y:{...CHART_SCALE_OPTS.y,ticks:{...CHART_SCALE_OPTS.y.ticks,callback:v=>'₱'+v}}}}});
});

async function askAI() {
  const q = document.getElementById('ai-q')?.value.trim();
  if (!q) { showToast('Please enter a question.','warn'); return; }
  await callClaude(
    'You are an expert financial analyst. Give specific, actionable answers in plain text (no markdown).',
    AI_CTX + '\n\nQuestion: ' + q,
    { outputEl:'ai-an', maxTokens:700 }
  );
}
";
require_once __DIR__ . '/includes/footer.php';
