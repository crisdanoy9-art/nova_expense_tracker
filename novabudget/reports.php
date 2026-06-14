<?php
// reports.php — Reports & Export
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Reports'; $activePage = 'reports';
$now = new DateTime();

// Handle CSV export
if (getStr('export') === 'csv') {
    $from   = getStr('from') ?: date('Y-01-01');
    $to     = getStr('to')   ?: date('Y-m-d');
    $rows   = dbQuery(
        "SELECT e.expense_date, c.name AS category, e.description, e.amount,
                e.payment_method, e.status, e.notes
         FROM expenses e JOIN categories c ON c.id=e.category_id
         WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ?
         ORDER BY e.expense_date DESC",
        [$userId, $from, $to]
    );
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="novabudget-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Date','Category','Description','Amount','Payment Method','Status','Notes']);
    foreach ($rows as $r) fputcsv($out, [$r['expense_date'],$r['category'],$r['description'],number_format((float)$r['amount'],2),$r['payment_method'],$r['status'],$r['notes']]);
    fclose($out);
    exit;
}
if (getStr('export') === 'json') {
    $from = getStr('from') ?: date('Y-01-01');
    $to   = getStr('to')   ?: date('Y-m-d');
    $rows = dbQuery(
        "SELECT e.*, c.name AS cat_name, c.emoji FROM expenses e
         JOIN categories c ON c.id=e.category_id
         WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC",
        [$userId,$from,$to]
    );
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="novabudget-' . date('Y-m-d') . '.json"');
    echo json_encode(['exported_at'=>date('c'),'user'=>$user['name'],'expenses'=>$rows], JSON_PRETTY_PRINT);
    exit;
}

// Monthly totals for chart (last 12 months)
$trend = dbQuery(
    "SELECT EXTRACT(YEAR FROM expense_date)::INT AS yr,
            EXTRACT(MONTH FROM expense_date)::INT AS mo,
            SUM(amount) AS total
     FROM expenses WHERE user_id=? AND status='completed'
       AND expense_date >= NOW()-INTERVAL '12 months'
     GROUP BY yr,mo ORDER BY yr,mo",
    [$userId]
);
$months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$tLabels=[]; $tData=[];
foreach ($trend as $t) { $tLabels[]=($months[$t['mo']].' '.substr($t['yr'],2)); $tData[]=round((float)$t['total'],2); }

// Category totals
$catTotals = dbQuery(
    "SELECT c.name, c.emoji, c.color, COALESCE(SUM(e.amount),0) AS total, COUNT(e.id) AS cnt
     FROM categories c LEFT JOIN expenses e ON e.category_id=c.id AND e.user_id=? AND e.status='completed'
     WHERE c.user_id=? GROUP BY c.name,c.emoji,c.color HAVING SUM(e.amount)>0 ORDER BY total DESC",
    [$userId,$userId]
);

// Top 5 biggest expenses ever
$topExpenses = dbQuery(
    "SELECT e.description, e.amount, e.expense_date, c.name AS cat_name, c.emoji
     FROM expenses e JOIN categories c ON c.id=e.category_id
     WHERE e.user_id=? AND e.status='completed' ORDER BY e.amount DESC LIMIT 5",
    [$userId]
);

require_once __DIR__ . '/includes/header.php';
$fromDefault = date('Y-01-01'); $toDefault = date('Y-m-d');
?>
<div class="page-hd">
  <div class="page-title"><i class="bi bi-bar-chart-line" style="color:var(--c-cyan)"></i> Reports</div>
</div>

<div class="g2" style="margin-bottom:20px">
  <div class="card-g">
    <div class="card-head"><div class="card-title">Generate & Export</div></div>
    <div class="card-body">
      <form method="GET" action="<?= APP_BASE ?>/reports.php" id="rep-form">
        <div class="mb-3"><label class="fc-label">Report Type</label>
          <select class="fc" name="type">
            <option>Monthly Summary</option><option>Category Breakdown</option>
            <option>Weekly Analysis</option><option>Annual Overview</option>
          </select>
        </div>
        <div class="g2 mb-3">
          <div><label class="fc-label">From</label><input type="date" class="fc" name="from" value="<?= h(getStr('from',$fromDefault)) ?>"></div>
          <div><label class="fc-label">To</label><input type="date" class="fc" name="to" value="<?= h(getStr('to',$toDefault)) ?>"></div>
        </div>
        <div style="display:flex;gap:9px;flex-wrap:wrap">
          <button type="submit" class="btn-glow" style="width:auto;padding:10px 18px"><i class="bi bi-eye"></i> Preview</button>
          <a href="<?= APP_BASE ?>/reports.php?export=csv&from=<?= h(getStr('from',$fromDefault)) ?>&to=<?= h(getStr('to',$toDefault)) ?>" class="btn-outline btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
          <a href="<?= APP_BASE ?>/reports.php?export=json&from=<?= h(getStr('from',$fromDefault)) ?>&to=<?= h(getStr('to',$toDefault)) ?>" class="btn-outline btn-sm"><i class="bi bi-braces"></i> JSON</a>
          <button type="button" class="btn-outline btn-sm" onclick="printPage()"><i class="bi bi-printer"></i> Print</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card-g">
    <div class="card-head"><div class="card-title">Quick Reports</div></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <?php
        $qr = [
          ['📅','Daily',   date('Y-m-d'),        date('Y-m-d')],
          ['📊','Weekly',  date('Y-m-d',strtotime('-7 days')), date('Y-m-d')],
          ['🗓','Monthly', date('Y-m-01'),        date('Y-m-t')],
          ['📈','Annual',  date('Y-01-01'),        date('Y-12-31')],
        ];
        foreach ($qr as $r): ?>
        <a href="<?= APP_BASE ?>/reports.php?export=csv&from=<?= $r[2] ?>&to=<?= $r[3] ?>" class="report-card">
          <div style="font-size:26px;margin-bottom:6px"><?= $r[0] ?></div>
          <div style="font-size:13px;font-weight:600"><?= $r[1] ?></div>
          <div style="font-size:11px;color:var(--text3)"><?= $r[2] ?> → <?= $r[3] ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Charts -->
<div class="g2" style="margin-bottom:20px">
  <div class="card-g">
    <div class="card-head"><div class="card-title">12-Month Trend</div></div>
    <div class="card-body"><div class="ch-wrap" style="height:240px"><canvas id="ch-trend"></canvas></div></div>
  </div>
  <div class="card-g">
    <div class="card-head"><div class="card-title">Category Totals (All Time)</div></div>
    <div class="card-body"><div class="ch-wrap" style="height:240px"><canvas id="ch-cats"></canvas></div></div>
  </div>
</div>

<!-- Top expenses + category table -->
<div class="g2">
  <div class="card-g">
    <div class="card-head"><div class="card-title"><i class="bi bi-trophy" style="color:var(--c-amber)"></i> Top 5 Largest Expenses</div></div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Description</th><th>Category</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($topExpenses as $e): ?>
          <tr>
            <td><?= h($e['description']) ?></td>
            <td><span class="badge-cat"><?= h($e['emoji'].' '.$e['cat_name']) ?></span></td>
            <td style="font-weight:600;color:var(--c-cyan)">₱<?= number_format((float)$e['amount'],2) ?></td>
            <td style="color:var(--text3)"><?= h($e['expense_date']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card-g">
    <div class="card-head"><div class="card-title">Category Summary (All Time)</div></div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Category</th><th>Total</th><th>Transactions</th></tr></thead>
        <tbody>
          <?php foreach ($catTotals as $c): ?>
          <tr>
            <td><span class="badge-cat" style="border-color:<?= h($c['color']) ?>20"><?= h($c['emoji'].' '.$c['name']) ?></span></td>
            <td style="font-weight:600;color:<?= h($c['color']) ?>">₱<?= number_format((float)$c['total'],2) ?></td>
            <td style="color:var(--text3)"><?= $c['cnt'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraJs = "
const T_LABELS = " . json_encode($tLabels) . ";
const T_DATA   = " . json_encode($tData)   . ";
const C_LABELS = " . json_encode(array_column($catTotals,'emoji')  ) . ";
const C_NAMES  = " . json_encode(array_column($catTotals,'name')   ) . ";
const C_DATA   = " . json_encode(array_map(fn($c)=>round((float)$c['total'],2),$catTotals)) . ";
const C_COLORS = " . json_encode(array_column($catTotals,'color')  ) . ";

document.addEventListener('DOMContentLoaded', () => {
  makeChart('ch-trend',{type:'line',data:{labels:T_LABELS,datasets:[{data:T_DATA,borderColor:'#00e5ff',backgroundColor:'rgba(0,229,255,.1)',borderWidth:2,pointRadius:4,tension:.4,fill:true}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>'₱'+ctx.raw.toFixed(2)}}},scales:{x:{...CHART_SCALE_OPTS.x},y:{...CHART_SCALE_OPTS.y,ticks:{...CHART_SCALE_OPTS.y.ticks,callback:v=>'₱'+v.toLocaleString()}}}}});
  makeChart('ch-cats',{type:'bar',data:{labels:C_LABELS.map((e,i)=>e+' '+C_NAMES[i].split(' ')[0]),datasets:[{data:C_DATA,backgroundColor:C_COLORS.map(c=>c+'b0'),borderRadius:6,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false},tooltip:{...CHART_TOOLTIP_OPTS,callbacks:{label:ctx=>'₱'+ctx.raw.toFixed(2)}}},scales:{x:{...CHART_SCALE_OPTS.x,ticks:{...CHART_SCALE_OPTS.x.ticks,callback:v=>'₱'+v}},y:{...CHART_SCALE_OPTS.y,grid:{color:'rgba(255,255,255,.03)'}}}}});
});
";
require_once __DIR__ . '/includes/footer.php';
