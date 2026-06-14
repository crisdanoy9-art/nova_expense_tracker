<?php
// add_expense.php — Add new expense
require_once __DIR__ . '/config/auth.php';
$user       = requireAuth();
$userId     = currentUserId();
$pageTitle  = 'Add Expense';
$activePage = 'add_expense';
$errors     = [];
$formData   = ['category_id'=>'','description'=>'','amount'=>'','payment_method'=>'credit_card','expense_date'=>date('Y-m-d'),'status'=>'completed','notes'=>'','tags'=>'','is_recurring'=>0,'recurrence'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $formData = [
        'category_id'    => postStr('category_id'),
        'description'    => postStr('description'),
        'amount'         => postStr('amount'),
        'payment_method' => postStr('payment_method'),
        'expense_date'   => postStr('expense_date'),
        'status'         => postStr('status'),
        'notes'          => postStr('notes'),
        'tags'           => postStr('tags'),
        'is_recurring'   => postInt('is_recurring'),
        'recurrence'     => postStr('recurrence'),
    ];

    // Validate
    if (!$formData['category_id'] || !isValidUuid($formData['category_id'])) $errors['category_id'] = 'Please select a category.';
    if (!$formData['description'])       $errors['description']   = 'Description is required.';
    if (!is_numeric($formData['amount']) || (float)$formData['amount'] <= 0) $errors['amount'] = 'Enter a valid positive amount.';
    if (!$formData['expense_date'])      $errors['expense_date']  = 'Date is required.';
    $validMethods  = ['cash','credit_card','debit_card','bank_transfer','digital_wallet','crypto','other'];
    $validStatuses = ['completed','pending','failed'];
    if (!in_array($formData['payment_method'], $validMethods))  $errors['payment_method'] = 'Invalid payment method.';
    if (!in_array($formData['status'], $validStatuses))         $errors['status']         = 'Invalid status.';

    if (empty($errors)) {
        // Parse tags
        $tags = array_filter(array_map('trim', explode(',', $formData['tags'])));
        $tagsJson = !empty($tags) ? json_encode(array_values($tags)) : null;

        // Handle receipt upload
        $receiptUrl = handleUpload('receipt');

        dbExec(
            "INSERT INTO expenses
               (user_id, category_id, description, amount, payment_method,
                expense_date, status, notes, receipt_url, is_recurring, recurrence, tags)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?::jsonb)",
            [
                $userId,
                $formData['category_id'],
                $formData['description'],
                (float)$formData['amount'],
                $formData['payment_method'],
                $formData['expense_date'],
                $formData['status'],
                $formData['notes'] ?: null,
                $receiptUrl,
                $formData['is_recurring'] ? 'true' : 'false',
                $formData['is_recurring'] && $formData['recurrence'] ? $formData['recurrence'] : null,
                $tagsJson,
            ]
        );
        dbExec('INSERT INTO audit_log (user_id, action, table_name) VALUES (?,?,?)', [$userId,'insert','expenses']);
        flashSet('success', 'Expense added successfully!');
        header('Location: ' . APP_BASE . '/dashboard.php');
        exit;
    }
}

$categories = dbQuery(
    'SELECT id, name, emoji, color FROM categories WHERE user_id = ? ORDER BY sort_order, name',
    [$userId]
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hd">
  <div class="page-title"><i class="bi bi-plus-circle" style="color:var(--c-cyan)"></i> Add New Expense</div>
</div>

<div style="max-width:700px">

  <!-- AI SMART ENTRY -->
  <div class="ai-panel" style="margin-bottom:18px">
    <div style="display:flex;align-items:center;gap:11px;margin-bottom:12px">
      <div class="ai-ico">✦</div>
      <div><div style="font-size:13.5px;font-weight:600">AI Smart Entry</div>
      <div style="font-size:11px;color:var(--text3)">Describe your expense in plain English</div></div>
    </div>
    <div style="display:flex;gap:9px">
      <input class="fc" id="ai-input" placeholder='e.g. "Spent $45 on groceries at Walmart yesterday"' style="flex:1">
      <button class="btn-glow" style="width:auto;padding:10px 18px;white-space:nowrap" onclick="aiParse()">
        <i class="bi bi-stars"></i> Parse
      </button>
    </div>
    <div class="ai-txt" id="ai-smart" style="margin-top:10px;min-height:0;display:none"></div>
  </div>

  <!-- EXPENSE FORM -->
  <div class="card-g">
    <div class="card-head"><div class="card-title">Expense Details</div></div>
    <div class="card-body">
      <form method="POST" action="<?= APP_BASE ?>/add_expense.php" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Category *</label>
            <select class="fc <?= isset($errors['category_id'])?'err':'' ?>" name="category_id" id="cat-sel">
              <option value="">Select category…</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= h($cat['id']) ?>"
                data-color="<?= h($cat['color']) ?>"
                <?= $formData['category_id']===$cat['id']?'selected':'' ?>>
                <?= h($cat['emoji'].' '.$cat['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?><div class="field-err" style="display:block"><?= h($errors['category_id']) ?></div><?php endif; ?>
          </div>
          <div>
            <label class="fc-label">Amount (<?= h($user['currency']??'PHP') ?>) *</label>
            <div class="input-wrap">
              <span class="input-prefix">$</span>
              <input class="fc has-prefix <?= isset($errors['amount'])?'err':'' ?>" type="number"
                name="amount" id="exp-amount" value="<?= h($formData['amount']) ?>"
                min="0.01" step="0.01" placeholder="0.00">
            </div>
            <?php if (isset($errors['amount'])): ?><div class="field-err" style="display:block"><?= h($errors['amount']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="fc-label">Description *</label>
          <input class="fc <?= isset($errors['description'])?'err':'' ?>" type="text"
            name="description" id="exp-desc" value="<?= h($formData['description']) ?>"
            placeholder="What did you spend on?" maxlength="255">
          <?php if (isset($errors['description'])): ?><div class="field-err" style="display:block"><?= h($errors['description']) ?></div><?php endif; ?>
        </div>

        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Payment Method</label>
            <select class="fc" name="payment_method">
              <?php foreach (['credit_card'=>'💳 Credit Card','debit_card'=>'💳 Debit Card','bank_transfer'=>'🏦 Bank Transfer','cash'=>'💵 Cash','digital_wallet'=>'📱 Digital Wallet','crypto'=>'₿ Crypto','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $formData['payment_method']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="fc-label">Date *</label>
            <input class="fc <?= isset($errors['expense_date'])?'err':'' ?>" type="date"
              name="expense_date" id="exp-date" value="<?= h($formData['expense_date']) ?>">
            <?php if (isset($errors['expense_date'])): ?><div class="field-err" style="display:block"><?= h($errors['expense_date']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Status</label>
            <select class="fc" name="status">
              <option value="completed" <?= $formData['status']==='completed'?'selected':'' ?>>✅ Completed</option>
              <option value="pending"   <?= $formData['status']==='pending'?'selected':'' ?>>⏳ Pending</option>
              <option value="failed"    <?= $formData['status']==='failed'?'selected':'' ?>>❌ Failed</option>
            </select>
          </div>
          <div>
            <label class="fc-label">Tags <small style="color:var(--text3)">(comma-separated)</small></label>
            <input class="fc" type="text" name="tags" value="<?= h($formData['tags']) ?>"
              placeholder="work, personal, urgent…">
          </div>
        </div>

        <div class="mb-3">
          <label class="fc-label">Notes (optional)</label>
          <textarea class="fc" name="notes" placeholder="Additional notes or details…"><?= h($formData['notes']) ?></textarea>
        </div>

        <!-- RECEIPT UPLOAD -->
        <div class="mb-3">
          <label class="fc-label">Receipt / Attachment</label>
          <label class="fc-file" for="receipt-upload">
            <i class="bi bi-cloud-upload" style="font-size:22px;color:var(--c-cyan);display:block;margin-bottom:6px"></i>
            <div>Click to upload or drag & drop</div>
            <div style="font-size:11px;color:var(--text3);margin-top:3px">JPG, PNG, WEBP, PDF — max <?= UPLOAD_MAX_MB ?>MB</div>
            <div id="upload-name" style="font-size:12px;color:var(--c-cyan);margin-top:5px"></div>
          </label>
          <input type="file" name="receipt" id="receipt-upload" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none" onchange="showFileName(this)">
        </div>

        <!-- RECURRING -->
        <div class="mb-3" style="background:rgba(20,184,166,.05);border:1px solid rgba(20,184,166,.15);border-radius:var(--r-sm);padding:14px">
          <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-size:13.5px;font-weight:500">
            <input type="checkbox" name="is_recurring" id="is-recurring" value="1"
              style="accent-color:var(--c-teal);width:16px;height:16px" <?= $formData['is_recurring']?'checked':'' ?>
              onchange="document.getElementById('recurrence-row').style.display=this.checked?'block':'none'">
            <i class="bi bi-arrow-repeat" style="color:var(--c-teal)"></i> This is a recurring expense
          </label>
          <div id="recurrence-row" style="margin-top:12px;<?= $formData['is_recurring']?'':'display:none' ?>">
            <label class="fc-label">Repeat every</label>
            <select class="fc" name="recurrence" style="max-width:200px">
              <option value="daily"   <?= $formData['recurrence']==='daily'?'selected':'' ?>>Daily</option>
              <option value="weekly"  <?= $formData['recurrence']==='weekly'?'selected':'' ?>>Weekly</option>
              <option value="monthly" <?= $formData['recurrence']==='monthly'?'selected':'' ?> selected>Monthly</option>
              <option value="yearly"  <?= $formData['recurrence']==='yearly'?'selected':'' ?>>Yearly</option>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button type="submit" class="btn-glow" style="width:auto;padding:11px 24px">
            <i class="bi bi-plus-circle"></i> Add Expense
          </button>
          <a href="<?= APP_BASE ?>/dashboard.php" class="btn-outline"><i class="bi bi-x"></i> Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$catJson = json_encode(array_map(fn($c)=>['id'=>$c['id'],'name'=>$c['name']],$categories));
$extraJs = "
const AI_CATS = $catJson;

function showFileName(input) {
  const el = document.getElementById('upload-name');
  if (el) el.textContent = input.files[0]?.name || '';
}

async function aiParse() {
  const input = document.getElementById('ai-input')?.value.trim();
  if (!input) { showToast('Please describe your expense first.','warn'); return; }
  const cats = AI_CATS.map(c=>c.name).join(', ');
  const today = new Date().toISOString().split('T')[0];
  const result = await callClaude(
    'You are a JSON data extractor. Return ONLY a valid JSON object with keys: category (one of the provided list), amount (number), description (string), date (YYYY-MM-DD). No markdown, no explanation.',
    `Categories: \${cats}. Today: \${today}. Input: \"\${input}\"`,
    { outputEl: 'ai-smart', maxTokens: 300 }
  );
  if (!result) return;
  try {
    const d = JSON.parse(result.replace(/\`\`\`json|\`\`\`/g,'').trim());
    if (d.amount) document.getElementById('exp-amount').value = d.amount;
    if (d.description) document.getElementById('exp-desc').value = d.description;
    if (d.date) document.getElementById('exp-date').value = d.date;
    if (d.category) {
      const catSel = document.getElementById('cat-sel');
      const opts = Array.from(catSel.options);
      const found = opts.find(o => o.text.toLowerCase().includes(d.category.toLowerCase().split(' ')[0]));
      if (found) catSel.value = found.value;
    }
    document.getElementById('ai-smart').innerHTML = '<span style=\"color:var(--c-green)\">✓ Fields filled from AI.</span>';
  } catch(e) {}
}
";
require_once __DIR__ . '/includes/footer.php';
