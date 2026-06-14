<?php
// edit_expense.php — Edit an existing expense
require_once __DIR__ . '/config/auth.php';
$user       = requireAuth();
$userId     = currentUserId();
$pageTitle  = 'Edit Expense';
$activePage = 'dashboard';
$errors     = [];

$id = getStr('id');
if (!$id || !isValidUuid($id)) { flashSet('error','Invalid expense ID.'); header('Location: ' . APP_BASE . '/dashboard.php'); exit; }

// Fetch expense — ensure it belongs to current user
$expense = dbQueryOne(
    "SELECT e.*, c.name AS cat_name FROM expenses e
     JOIN categories c ON c.id = e.category_id
     WHERE e.id = ? AND e.user_id = ?",
    [$id, $userId]
);
if (!$expense) { flashSet('error','Expense not found.'); header('Location: ' . APP_BASE . '/dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $catId   = postStr('category_id');
    $desc    = postStr('description');
    $amount  = postStr('amount');
    $method  = postStr('payment_method');
    $date    = postStr('expense_date');
    $status  = postStr('status');
    $notes   = postStr('notes');
    $tags    = postStr('tags');
    $isRecur = postInt('is_recurring');
    $recur   = postStr('recurrence');

    if (!$catId || !isValidUuid($catId))    $errors['category_id']   = 'Select a category.';
    if (!$desc)                             $errors['description']   = 'Description is required.';
    if (!is_numeric($amount)||(float)$amount<=0) $errors['amount']  = 'Enter a valid amount.';
    if (!$date)                             $errors['expense_date']  = 'Date is required.';

    if (empty($errors)) {
        $tagList = array_filter(array_map('trim', explode(',', $tags)));
        $tagsJson = !empty($tagList) ? json_encode(array_values($tagList)) : null;
        $newReceipt = handleUpload('receipt');
        $receiptUrl = $newReceipt ?: $expense['receipt_url'];

        dbExec(
            "UPDATE expenses SET category_id=?, description=?, amount=?, payment_method=?,
             expense_date=?, status=?, notes=?, receipt_url=?, is_recurring=?,
             recurrence=?, tags=?::jsonb, updated_at=NOW()
             WHERE id=? AND user_id=?",
            [$catId, $desc, (float)$amount, $method, $date, $status,
             $notes?:null, $receiptUrl, $isRecur?'true':'false',
             $isRecur&&$recur?$recur:null, $tagsJson, $id, $userId]
        );
        dbExec('INSERT INTO audit_log (user_id,action,table_name,record_id) VALUES (?,?,?,?::uuid)', [$userId,'update','expenses',$id]);
        flashSet('success', 'Expense updated successfully!');
        header('Location: ' . APP_BASE . '/dashboard.php');
        exit;
    }
    $expense = array_merge($expense,['category_id'=>$catId,'description'=>$desc,'amount'=>$amount,'payment_method'=>$method,'expense_date'=>$date,'status'=>$status,'notes'=>$notes]);
}

$categories = dbQuery('SELECT id, name, emoji, color FROM categories WHERE user_id=? ORDER BY sort_order, name', [$userId]);
$currentTags = is_string($expense['tags']) ? implode(', ', json_decode($expense['tags'],true)??[]) : '';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-hd">
  <div class="page-title"><i class="bi bi-pencil-square" style="color:var(--c-cyan)"></i> Edit Expense</div>
</div>
<div style="max-width:680px">
  <div class="card-g">
    <div class="card-head">
      <div class="card-title">Update Details</div>
      <span style="font-size:11px;color:var(--text3)">ID: <?= substr(h($expense['id']),0,12) ?>…</span>
    </div>
    <div class="card-body">
      <form method="POST" action="<?= APP_BASE ?>/edit_expense.php?id=<?= h($id) ?>" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>
        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Category *</label>
            <select class="fc <?= isset($errors['category_id'])?'err':'' ?>" name="category_id">
              <?php foreach ($categories as $cat): ?>
              <option value="<?= h($cat['id']) ?>" <?= $expense['category_id']===$cat['id']?'selected':'' ?>><?= h($cat['emoji'].' '.$cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if(isset($errors['category_id'])): ?><div class="field-err" style="display:block"><?= h($errors['category_id']) ?></div><?php endif; ?>
          </div>
          <div>
            <label class="fc-label">Amount *</label>
            <div class="input-wrap">
              <span class="input-prefix">$</span>
              <input class="fc has-prefix <?= isset($errors['amount'])?'err':'' ?>" type="number" name="amount" value="<?= h($expense['amount']) ?>" min="0.01" step="0.01">
            </div>
            <?php if(isset($errors['amount'])): ?><div class="field-err" style="display:block"><?= h($errors['amount']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="fc-label">Description *</label>
          <input class="fc <?= isset($errors['description'])?'err':'' ?>" type="text" name="description" value="<?= h($expense['description']) ?>" maxlength="255">
          <?php if(isset($errors['description'])): ?><div class="field-err" style="display:block"><?= h($errors['description']) ?></div><?php endif; ?>
        </div>
        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Payment Method</label>
            <select class="fc" name="payment_method">
              <?php foreach (['credit_card'=>'💳 Credit Card','debit_card'=>'💳 Debit Card','bank_transfer'=>'🏦 Bank Transfer','cash'=>'💵 Cash','digital_wallet'=>'📱 Digital Wallet','crypto'=>'₿ Crypto','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $expense['payment_method']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="fc-label">Date *</label>
            <input class="fc" type="date" name="expense_date" value="<?= h($expense['expense_date']) ?>">
          </div>
        </div>
        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Status</label>
            <select class="fc" name="status">
              <?php foreach (['completed'=>'✅ Completed','pending'=>'⏳ Pending','failed'=>'❌ Failed','refunded'=>'↩ Refunded'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $expense['status']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="fc-label">Tags</label>
            <input class="fc" type="text" name="tags" value="<?= h($currentTags) ?>" placeholder="work, personal…">
          </div>
        </div>
        <div class="mb-3">
          <label class="fc-label">Notes</label>
          <textarea class="fc" name="notes"><?= h($expense['notes']??'') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="fc-label">Replace Receipt</label>
          <?php if ($expense['receipt_url']): ?>
          <div style="margin-bottom:8px">
            <span style="font-size:12px;color:var(--text2)">Current: </span>
            <a href="<?= APP_BASE ?>/<?= h($expense['receipt_url']) ?>" target="_blank" style="font-size:12px;color:var(--c-cyan)">View receipt</a>
          </div>
          <?php endif; ?>
          <label class="fc-file" for="receipt-upload"><i class="bi bi-cloud-upload" style="font-size:20px;color:var(--c-cyan);display:block;margin-bottom:4px"></i>Upload new receipt (optional)<div id="upload-name" style="font-size:12px;color:var(--c-cyan);margin-top:4px"></div></label>
          <input type="file" name="receipt" id="receipt-upload" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none" onchange="document.getElementById('upload-name').textContent=this.files[0]?.name||''">
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button type="submit" class="btn-glow" style="width:auto;padding:11px 22px"><i class="bi bi-check-circle"></i> Save Changes</button>
          <a href="<?= APP_BASE ?>/dashboard.php" class="btn-outline"><i class="bi bi-x"></i> Cancel</a>
          <form method="POST" action="<?= APP_BASE ?>/delete_expense.php" style="display:inline" id="del-form-edit">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="redirect" value="/dashboard.php">
            <button type="button" class="btn-danger" onclick="confirmDelete('Delete this expense permanently?','del-form-edit')">
              <i class="bi bi-trash"></i> Delete
            </button>
          </form>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
