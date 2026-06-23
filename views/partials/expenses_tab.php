<div class="tab-pane fade" id="expenses" role="tabpanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Liste des Dépenses</span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#filtersCollapse"><i class="fas fa-filter me-1"></i>Filtres</button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAllExpensesModal"><i class="fas fa-trash me-1"></i>Tout supprimer</button>
            </div>
        </div>
        <div class="card-body">
            <div class="collapse mb-3" id="filtersCollapse">
                <div class="card card-body bg-light">
                    <div class="row g-2">
                        <div class="col-md-3 col-6">
                            <label class="form-label small">Catégorie</label>
                            <select class="form-select form-select-sm" id="filterCategory">
                                <option value="">Toutes</option>
                                <?php foreach (array_keys($budgets) as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-6"><label class="form-label small">Date début</label><input type="date" class="form-control form-control-sm" id="filterDateStart"></div>
                        <div class="col-md-3 col-6"><label class="form-label small">Date fin</label><input type="date" class="form-control form-control-sm" id="filterDateEnd"></div>
                        <div class="col-md-3 col-6"><label class="form-label small">Montant min</label><input type="number" class="form-control form-control-sm" id="filterAmountMin" placeholder="0"></div>
                        <div class="col-md-3 col-6"><label class="form-label small">Montant max</label><input type="number" class="form-control form-control-sm" id="filterAmountMax" placeholder="Max"></div>
                        <div class="col-12 mt-1">
                            <button class="btn btn-primary btn-sm me-2" id="applyFilters">Appliquer</button>
                            <button class="btn btn-outline-secondary btn-sm" id="resetFilters">Réinitialiser</button>
                        </div>
                    </div>
                </div>
            </div>
            <small class="text-muted d-block mb-2" id="expensesCount"><?php echo count($expenses); ?> dépense(s)</small>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="expensesTable">
                    <thead class="table-light">
                        <tr>
                            <th><button class="btn btn-link p-0 text-decoration-none sort-btn" data-sort="date-desc">Date <i class="fas fa-sort ms-1"></i></button></th>
                            <th><button class="btn btn-link p-0 text-decoration-none sort-btn" data-sort="category">Catégorie <i class="fas fa-sort ms-1"></i></button></th>
                            <th>Description</th>
                            <th class="text-end"><button class="btn btn-link p-0 text-decoration-none sort-btn" data-sort="amount-desc">Montant <i class="fas fa-sort ms-1"></i></button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <?php
                        $sorted_exp = $expenses;
                        usort($sorted_exp, fn($a,$b) => strtotime($b['created_at'] ?? $b['date']) - strtotime($a['created_at'] ?? $a['date']));
                        foreach ($sorted_exp as $exp):
                        ?>
                        <tr>
                            <td data-sort="<?php echo strtotime($exp['created_at'] ?? $exp['date']); ?>">
                                <?php echo isset($exp['created_at']) ? date('d/m/Y H:i', strtotime($exp['created_at'])) : htmlspecialchars($exp['date']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($exp['category']); ?></td>
                            <td><?php echo htmlspecialchars($exp['description']); ?></td>
                            <td class="text-end text-danger fw-semibold" data-sort="<?php echo $exp['amount']; ?>">-<?php echo formatCurrency($exp['amount']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary me-1"
                                    data-bs-toggle="modal" data-bs-target="#editExpenseModal"
                                    data-id="<?php echo $exp['id']; ?>"
                                    data-description="<?php echo htmlspecialchars($exp['description']); ?>"
                                    data-amount="<?php echo $exp['amount']; ?>"
                                    data-category="<?php echo htmlspecialchars($exp['category']); ?>"
                                    data-date="<?php echo $exp['date']; ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="delete_expense" value="<?php echo $exp['id']; ?>">
                                    <input type="hidden" name="current_tab" value="expenses">
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="noExpensesMessage" class="alert alert-info d-none">
                <i class="fas fa-info-circle me-2"></i>Aucune dépense ne correspond aux filtres.
            </div>
        </div>
    </div>
</div>
