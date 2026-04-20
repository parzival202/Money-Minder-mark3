# MoneyMinder – Debts & Reimbursements Module

## Context
The user wants to track money they owe to people (friends, family).
A debt has a total amount and is repaid partially over several months.
Each partial repayment must:
  1. Be recorded as a regular expense (category = "Remboursement") so it appears
     in the history and charts.
  2. Update the remaining balance on the debt record.

This feature touches two files:
  - `db.php`     -> new table + CRUD functions
  - `index.php`  -> new tab UI + POST handlers

---

## Task 1 – Database: new `debts` table + helper functions in `db.php`

### 1a — Add table creation inside `init_db()`

Find the `init_db()` function in `db.php` and add this CREATE TABLE statement
alongside the existing ones:

```sql
CREATE TABLE IF NOT EXISTS debts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL,
    label        TEXT    NOT NULL,
    total_amount REAL    NOT NULL DEFAULT 0,
    amount_paid  REAL    NOT NULL DEFAULT 0,
    note         TEXT    DEFAULT '',
    status       TEXT    NOT NULL DEFAULT 'active',
    created_at   TEXT    DEFAULT (datetime('now'))
)
```

### 1b — Add these four PHP functions at the bottom of `db.php`

```php
// ── Debts ─────────────────────────────────────────────────────────

function fetchDebts($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM debts WHERE user_id = ? ORDER BY status ASC, created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function insertDebt($user_id, $label, $total_amount, $note = '') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO debts (user_id, label, total_amount, amount_paid, note) VALUES (?, ?, ?, 0, ?)");
    $stmt->execute([$user_id, $label, $total_amount, $note]);
    return $pdo->lastInsertId();
}

function addDebtPayment($debt_id, $payment_amount) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE debts SET amount_paid = MIN(amount_paid + ?, total_amount) WHERE id = ?");
    $stmt->execute([$payment_amount, $debt_id]);
    $pdo->prepare("UPDATE debts SET status = 'settled' WHERE id = ? AND amount_paid >= total_amount")
        ->execute([$debt_id]);
}

function deleteDebt($debt_id) {
    global $pdo;
    $pdo->prepare("DELETE FROM debts WHERE id = ?")->execute([$debt_id]);
}
```

---

## Task 2 – POST handlers in `index.php`

Find the `if ($_SERVER['REQUEST_METHOD'] === 'POST')` block.
Add these three handlers before its closing `}`.

### 2a — Create a new debt

```php
if (isset($_POST['add_debt'])) {
    $label        = trim($_POST['debt_label']);
    $total_amount = floatval($_POST['debt_total_amount']);
    $note         = trim($_POST['debt_note'] ?? '');
    if ($label !== '' && $total_amount > 0) {
        insertDebt($user_id, $label, $total_amount, $note);
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?debt_added=1&tab=debts'); exit;
}
```

### 2b — Record a partial repayment

```php
if (isset($_POST['pay_debt'])) {
    $debt_id        = intval($_POST['debt_id']);
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_date   = $_POST['payment_date'] ?: date('Y-m-d');

    if ($debt_id > 0 && $payment_amount > 0) {
        global $pdo;
        $row = $pdo->prepare("SELECT label FROM debts WHERE id = ?");
        $row->execute([$debt_id]);
        $debt  = $row->fetch(PDO::FETCH_ASSOC);
        $label = $debt ? $debt['label'] : 'Remboursement';

        // Auto-create "Remboursement" budget category at 0 FCFA if missing
        $budgets = getBudgets($user_id);
        if (!isset($budgets['Remboursement'])) {
            $budgets['Remboursement'] = 0;
            setBudgets($user_id, $budgets);
        }

        insertExpense($user_id, [
            'date'        => $payment_date,
            'category'    => 'Remboursement',
            'description' => 'Remboursement — ' . $label,
            'amount'      => $payment_amount,
        ]);

        addDebtPayment($debt_id, $payment_amount);
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?debt_paid=1&tab=debts'); exit;
}
```

### 2c — Delete a debt

```php
if (isset($_POST['delete_debt'])) {
    deleteDebt(intval($_POST['delete_debt']));
    header('Location: ' . $_SERVER['PHP_SELF'] . '?debt_deleted=1&tab=debts'); exit;
}
```

---

## Task 3 – Load debts data in `index.php`

Find the "CHARGEMENT DES DONNÉES" section where `$alerts = fetchAlerts(...)` is.
Add this line immediately after it:

```php
$debts = fetchDebts($user_id);
```

---

## Task 4 – Toast notifications in `index.php`

Find the `DOMContentLoaded` JS block that reads URL params for toasts.
Add these three lines inside it:

```javascript
if (p.has('debt_added'))   showToast('Dette ajoutée !');
if (p.has('debt_paid'))    showToast('Remboursement enregistré !');
if (p.has('debt_deleted')) showToast('Dette supprimée.', 'warning');
```

---

## Task 5 – New nav tab item in `index.php`

Find `<ul class="nav nav-tabs">`. Add this `<li>` just before the Alertes `<li>`:

```html
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#debts" type="button">
        Dettes
    </button>
</li>
```

---

## Task 6 – New tab-pane HTML in `index.php`

Add the following complete block between the closing `</div>` of `#savings`
and the opening of `#alerts`.

```html
<!-- TAB : DETTES -->
<div class="tab-pane fade" id="debts" role="tabpanel">
    <div class="row">

        <!-- Left: active debts list -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    Mes Dettes en cours
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDebtModal">
                        <i class="fas fa-plus me-1"></i>Nouvelle dette
                    </button>
                </div>
                <div class="card-body">
                    <?php
                    $active_debts  = array_filter($debts, fn($d) => $d['status'] === 'active');
                    $settled_debts = array_filter($debts, fn($d) => $d['status'] === 'settled');
                    ?>
                    <?php if (empty($active_debts)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-handshake fa-3x mb-3 opacity-25"></i>
                            <p class="fw-semibold mb-1">Aucune dette en cours</p>
                            <small>Cliquez sur "Nouvelle dette" pour en ajouter une.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_debts as $debt):
                            $remaining = $debt['total_amount'] - $debt['amount_paid'];
                            $pct       = $debt['total_amount'] > 0
                                ? round(($debt['amount_paid'] / $debt['total_amount']) * 100, 1) : 0;
                            $bar_col   = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="mb-4 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-bold fs-6"><?php echo htmlspecialchars($debt['label']); ?></span>
                                    <?php if (!empty($debt['note'])): ?>
                                        <small class="text-muted ms-2"><?php echo htmlspecialchars($debt['note']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-success"
                                        data-bs-toggle="modal" data-bs-target="#payDebtModal"
                                        data-debt-id="<?php echo $debt['id']; ?>"
                                        data-debt-label="<?php echo htmlspecialchars($debt['label']); ?>"
                                        data-debt-remaining="<?php echo $remaining; ?>">
                                        <i class="fas fa-money-bill-wave me-1"></i>Payer
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette dette ?')">
                                        <input type="hidden" name="delete_debt" value="<?php echo $debt['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Remboursé : <?php echo formatCurrency($debt['amount_paid']); ?></small>
                                <small class="text-muted">Reste : <strong class="text-danger"><?php echo formatCurrency($remaining); ?></strong></small>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar <?php echo $bar_col; ?>" style="width:<?php echo $pct; ?>%;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">0</small>
                                <small class="text-muted"><?php echo formatCurrency($debt['total_amount']); ?> — <?php echo $pct; ?>% remboursé</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: summary + settled -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header fw-semibold">Résumé</div>
                <div class="card-body">
                    <?php
                    $total_owed      = array_sum(array_column(array_values($active_debts), 'total_amount'));
                    $total_paid_all  = array_sum(array_column(array_values($active_debts), 'amount_paid'));
                    $total_remaining = $total_owed - $total_paid_all;
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Dettes actives</span>
                        <strong><?php echo count($active_debts); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total dû</span>
                        <strong class="text-danger"><?php echo formatCurrency($total_remaining); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Déjà remboursé</span>
                        <strong class="text-success"><?php echo formatCurrency($total_paid_all); ?></strong>
                    </div>
                    <?php if ($total_owed > 0): ?>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?php echo round(($total_paid_all / $total_owed) * 100); ?>%;"></div>
                    </div>
                    <small class="text-muted"><?php echo round(($total_paid_all / max($total_owed,1)) * 100); ?>% remboursé au total</small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($settled_debts)): ?>
            <div class="card mt-3">
                <div class="card-header fw-semibold text-success">
                    <i class="fas fa-check-circle me-2"></i>Dettes soldées
                </div>
                <div class="card-body p-2">
                    <?php foreach ($settled_debts as $debt): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 px-1 border-bottom">
                        <div>
                            <span class="fw-semibold"><?php echo htmlspecialchars($debt['label']); ?></span>
                            <small class="text-muted ms-2"><?php echo formatCurrency($debt['total_amount']); ?></small>
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            <span class="badge bg-success">Soldée ✅</span>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="delete_debt" value="<?php echo $debt['id']; ?>">
                                <button class="btn btn-sm btn-outline-secondary border-0"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div><!-- /#debts -->
```

---

## Task 7 – Two new modals in `index.php`

Add both modals in the MODALS section, after the `deleteAllExpensesModal`.

### Modal 1 — Add a new debt

```html
<!-- Ajouter une dette -->
<div class="modal fade" id="addDebtModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-handshake me-2"></i>Nouvelle dette</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Créancier <small class="text-muted">(qui tu rembourses)</small></label>
            <input type="text" class="form-control" name="debt_label" placeholder="Ex: Maman, Ami Kofi..." required>
        </div>
        <div class="mb-3">
            <label class="form-label">Montant total à rembourser (FCFA)</label>
            <input type="number" class="form-control" name="debt_total_amount" min="0" step="100" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Note <small class="text-muted">(optionnel)</small></label>
            <input type="text" class="form-control" name="debt_note" placeholder="Ex: Prêt pour téléphone...">
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="add_debt" class="btn btn-primary">Ajouter</button>
    </div>
  </form></div>
</div>
```

### Modal 2 — Record a partial repayment

```html
<!-- Payer une dette -->
<div class="modal fade" id="payDebtModal" tabindex="-1">
  <div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Enregistrer un remboursement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" name="debt_id" id="payDebtId">
        <div class="mb-3">
            <label class="form-label">Créancier</label>
            <input type="text" class="form-control" id="payDebtLabel" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Montant restant</label>
            <input type="text" class="form-control" id="payDebtRemaining" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Montant à rembourser (FCFA)</label>
            <input type="number" class="form-control" name="payment_amount" min="1" step="100" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="pay_debt" class="btn btn-success">Enregistrer</button>
    </div>
  </form></div>
</div>
```

---

## Task 8 – JS: pre-fill payDebtModal in `index.php`

Inside the `DOMContentLoaded` JS block, after the `editModal` listener, add:

```javascript
const payDebtModal = document.getElementById('payDebtModal');
if (payDebtModal) {
    payDebtModal.addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        document.getElementById('payDebtId').value        = b.dataset.debtId;
        document.getElementById('payDebtLabel').value     = b.dataset.debtLabel;
        document.getElementById('payDebtRemaining').value =
            parseFloat(b.dataset.debtRemaining).toLocaleString('fr-FR') + ' FCFA';
    });
}
```

---

## Summary

| Task | File      | What it does                                    |
|------|-----------|-------------------------------------------------|
| 1    | db.php    | Create debts table + 4 CRUD functions           |
| 2    | index.php | POST handlers: add / pay / delete debt          |
| 3    | index.php | Load $debts array                               |
| 4    | index.php | Toast notifications for debt actions            |
| 5    | index.php | New nav tab item "Dettes"                       |
| 6    | index.php | Full tab-pane HTML (list + summary + settled)   |
| 7    | index.php | Two modals (add debt + pay debt)                |
| 8    | index.php | JS to pre-fill the pay modal                    |

**Apply in order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8**

After applying, test:
1. Create a debt "Maman — 50 000 FCFA"
2. Record a payment of 15 000 FCFA -> verify it appears in Historique as "Remboursement"
3. Verify remaining balance shows 35 000 FCFA
4. Pay remaining 35 000 FCFA -> verify debt moves to "Soldée"
5. Confirm "Remboursement" category was auto-created in budgets at 0 FCFA
