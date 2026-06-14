<?php
// categories.php — Manage expense categories
require_once __DIR__ . '/config/auth.php';
$user       = requireAuth();
$userId     = currentUserId();
$pageTitle  = 'Categories';
$activePage = 'categories';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postStr('action');

    if ($action === 'add') {
        $name  = postStr('name');
        $emoji = postStr('emoji') ?: '💰';
        $color = postStr('color') ?: '#00e5ff';
        $budget = postFloat('budget', 200.0);
        if (!$name) { flashSet('error','Category name is required.'); }
        else {
            $exists = dbQueryOne('SELECT id FROM categories WHERE user_id=? AND LOWER(name)=LOWER(?)', [$userId,$name]);
            if ($exists) { flashSet('error','A category with that name already exists.'); }
            else {
                $maxOrder = dbQueryOne('SELECT MAX(sort_order) AS mo FROM categories WHERE user_id=?', [$userId])['mo'] ?? 0;
                $catId = dbInsert('INSERT INTO categories (user_id,name,emoji,color,sort_order) VALUES (?,?,?,?,?) RETURNING id', [$userId,$name,$emoji,$color,(int)$maxOrder+1]);
                // Add a budget entry for this category
                if ($budget > 0) {
                    $now = new DateTime();
                    dbExec('INSERT INTO budgets (user_id,category_id,amount,month,year) VALUES (?,?,?,?,?) ON CONFLICT (user_id,category_id,month,year) DO UPDATE SET amount=EXCLUDED.amount',
                        [$userId,$catId,$budget,(int)$now->format('n'),(int)$now->format('Y')]);
                }
                flashSet('success', "Category \"$name\" added!");
            }
        }
    } elseif ($action === 'edit') {
        $catId = postStr('cat_id');
        $name  = postStr('name');
        $emoji = postStr('emoji') ?: '💰';
        $color = postStr('color') ?: '#00e5ff';
        if (!$catId || !isValidUuid($catId)) { flashSet('error','Invalid category.'); }
        elseif (!$name) { flashSet('error','Name is required.'); }
        else {
            $cat = dbQueryOne('SELECT id FROM categories WHERE id=? AND user_id=?', [$catId,$userId]);
            if (!$cat) { flashSet('error','Category not found.'); }
            else { dbExec('UPDATE categories SET name=?,emoji=?,color=?,updated_at=? WHERE id=? AND user_id=?', [$name,$emoji,$color,'NOW()',$catId,$userId]); flashSet('success','Category updated!'); }
        }
    } elseif ($action === 'delete') {
        $catId = postStr('cat_id');
        if (!$catId || !isValidUuid($catId)) { flashSet('error','Invalid category.'); }
        else {
            $cat = dbQueryOne('SELECT id,is_system FROM categories WHERE id=? AND user_id=?', [$catId,$userId]);
            if (!$cat) { flashSet('error','Category not found.'); }
            elseif ($cat['is_system']) { flashSet('error','System categories cannot be deleted.'); }
            else {
                $inUse = dbQueryOne('SELECT COUNT(*) AS n FROM expenses WHERE category_id=? AND user_id=?', [$catId,$userId]);
                if ((int)$inUse['n'] > 0) { flashSet('error',"Cannot delete — {$inUse['n']} expense(s) use this category. Reassign them first."); }
                else { dbExec('DELETE FROM categories WHERE id=? AND user_id=?', [$catId,$userId]); flashSet('success','Category deleted.'); }
            }
        }
    }
    header('Location: ' . APP_BASE . '/categories.php'); exit;
}

$now = new DateTime();
$month = (int)$now->format('n'); $year = (int)$now->format('Y');
$categories = dbQuery(
    "SELECT c.id, c.name, c.emoji, c.color, c.is_system, c.sort_order,
            COALESCE(b.amount,0) AS budget_amt,
            COALESCE(SUM(e.amount) FILTER (WHERE e.status='completed' AND EXTRACT(MONTH FROM e.expense_date)=? AND EXTRACT(YEAR FROM e.expense_date)=?),0) AS spent,
            COUNT(e.id) AS txn_count
     FROM categories c
     LEFT JOIN budgets b ON b.category_id=c.id AND b.user_id=? AND b.month=? AND b.year=?
     LEFT JOIN expenses e ON e.category_id=c.id AND e.user_id=?
     WHERE c.user_id=?
     GROUP BY c.id, c.name, c.emoji, c.color, c.is_system, c.sort_order, b.amount
     ORDER BY c.sort_order, c.name",
    [$month,$year,$userId,$month,$year,$userId,$userId]
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hd">
  <div class="page-title"><i class="bi bi-tags" style="color:var(--c-pink)"></i> Categories</div>
  <button class="btn-glow" style="width:auto;padding:9px 18px" onclick="openModal('add-cat-modal')">
    <i class="bi bi-plus"></i> Add Category
  </button>
</div>

<div class="cat-grid">
  <?php foreach ($categories as $cat):
    $pct = $cat['budget_amt'] > 0 ? min(100, round(($cat['spent'] / $cat['budget_amt']) * 100)) : 0;
    $barColor = $pct >= 90 ? '#f87171' : ($pct >= 75 ? '#f59e0b' : $cat['color']);
  ?>
  <div class="cat-card" style="--cc:<?= h($cat['color']) ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <span class="cat-em"><?= h($cat['emoji']) ?></span>
      <div style="display:flex;gap:5px">
        <?php if (!$cat['is_system']): ?>
        <button class="btn-icon edit" title="Edit"
          onclick="openEditCat('<?= h($cat['id']) ?>','<?= h(addslashes($cat['name'])) ?>','<?= h($cat['emoji']) ?>','<?= h($cat['color']) ?>')">
          <i class="bi bi-pencil"></i>
        </button>
        <form method="POST" action="<?= APP_BASE ?>/categories.php" style="display:inline" id="delcat-<?= h($cat['id']) ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="cat_id" value="<?= h($cat['id']) ?>">
          <button type="button" class="btn-icon del" title="Delete"
            onclick="confirmDelete('Delete &quot;<?= h(addslashes($cat['name'])) ?>&quot;?','delcat-<?= h($cat['id']) ?>')">
            <i class="bi bi-trash"></i>
          </button>
        </form>
        <?php else: ?>
        <span class="tag" style="background:rgba(255,255,255,.06);color:var(--text3)">System</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="cat-nm"><?= h($cat['name']) ?></div>
    <div class="cat-am"><?= $cat['txn_count'] ?> transactions · Budget: ₱<?= number_format((float)$cat['budget_amt'],0) ?></div>
    <div class="cat-sp" style="color:<?= h($cat['color']) ?>">₱<?= number_format((float)$cat['spent'],2) ?></div>
    <div style="margin-top:9px">
      <div class="bbar">
        <div class="bbar-fill" style="width:<?= $pct ?>%;background:<?= h($barColor) ?>;opacity:.85"></div>
      </div>
      <div style="font-size:10px;color:var(--text3);margin-top:3px"><?= $pct ?>% of budget used this month</div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ADD CATEGORY MODAL -->
<div class="modal-overlay" id="add-cat-modal" style="display:none">
  <div class="modal-box">
    <div class="modal-title">Add New Category</div>
    <div class="modal-sub">Create a custom expense category</div>
    <form method="POST" action="<?= APP_BASE ?>/categories.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="mb-3">
        <label class="fc-label">Category Name *</label>
        <input class="fc" type="text" name="name" required placeholder="e.g. Pet Care">
      </div>
      <div class="g2 mb-3">
        <div>
          <label class="fc-label">Emoji</label>
          <input class="fc" type="text" name="emoji" placeholder="🐾" maxlength="4" value="💰">
        </div>
        <div>
          <label class="fc-label">Color</label>
          <input class="fc" type="color" name="color" value="#00e5ff" style="height:42px;padding:4px 8px;cursor:pointer">
        </div>
      </div>
      <div class="mb-3">
        <label class="fc-label">Monthly Budget ($)</label>
        <input class="fc" type="number" name="budget" value="200" min="0" step="1">
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn-glow" style="width:auto;flex:1;padding:11px"><i class="bi bi-check-circle"></i> Save</button>
        <button type="button" class="btn-outline" onclick="closeModal('add-cat-modal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<div class="modal-overlay" id="edit-cat-modal" style="display:none">
  <div class="modal-box">
    <div class="modal-title">Edit Category</div>
    <div class="modal-sub">Update category details</div>
    <form method="POST" action="<?= APP_BASE ?>/categories.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="cat_id" id="ec-id">
      <div class="mb-3">
        <label class="fc-label">Category Name *</label>
        <input class="fc" type="text" name="name" id="ec-name" required>
      </div>
      <div class="g2 mb-3">
        <div>
          <label class="fc-label">Emoji</label>
          <input class="fc" type="text" name="emoji" id="ec-emoji" maxlength="4">
        </div>
        <div>
          <label class="fc-label">Color</label>
          <input class="fc" type="color" name="color" id="ec-color" style="height:42px;padding:4px 8px;cursor:pointer">
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn-glow" style="width:auto;flex:1;padding:11px"><i class="bi bi-check-circle"></i> Save</button>
        <button type="button" class="btn-outline" onclick="closeModal('edit-cat-modal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraJs = "
function openEditCat(id, name, emoji, color) {
  document.getElementById('ec-id').value = id;
  document.getElementById('ec-name').value = name;
  document.getElementById('ec-emoji').value = emoji;
  document.getElementById('ec-color').value = color;
  openModal('edit-cat-modal');
}";
require_once __DIR__ . '/includes/footer.php';
