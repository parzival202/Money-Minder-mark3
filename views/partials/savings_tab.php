<div class="tab-pane fade" id="savings" role="tabpanel">
    <div class="row">
        <div class="col-md-6">

            <div class="card mt-3">
                <div class="card-header fw-semibold d-flex align-items-center gap-2">
                    <i class="fas fa-heartbeat text-danger"></i>
                    Santé financière du mois
                </div>
                <div class="card-body">
                    <?php
                    $allocated_total = array_sum($budgets);
                    $safety_margin   = max($monthly_budget - $allocated_total, 0);

                    $real_savings_rate = $monthly_budget > 0
                        ? ($current_savings / $monthly_budget) * 100
                        : 0;
                    if ($real_savings_rate >= 20) {
                        $savings_label = 'Excellent';
                        $savings_color = '#16a34a';
                        $savings_icon  = 'fa-circle-check';
                    } elseif ($real_savings_rate >= 10) {
                        $savings_label = 'Correct';
                        $savings_color = '#d97706';
                        $savings_icon  = 'fa-circle-minus';
                    } else {
                        $savings_label = 'Insuffisant';
                        $savings_color = '#dc2626';
                        $savings_icon  = 'fa-circle-xmark';
                    }

                    $needs_cats  = ['Alimentation', 'Transport', 'Abonnement mensuel'];
                    $wants_cats  = ['Loisirs/Sortie', 'Mode', 'Aide proche'];
                    $needs_spent = 0;
                    $wants_spent = 0;
                    foreach ($expenses as $e) {
                        if (in_array($e['category'], $needs_cats, true)) $needs_spent += floatval($e['amount']);
                        if (in_array($e['category'], $wants_cats, true)) $wants_spent += floatval($e['amount']);
                    }
                    $needs_pct = $monthly_budget > 0 ? round(($needs_spent / $monthly_budget) * 100, 1) : 0;
                    $wants_pct = $monthly_budget > 0 ? round(($wants_spent / $monthly_budget) * 100, 1) : 0;
                    $ratio_ok  = ($needs_pct <= 52 && $wants_pct <= 32);
                    $ratio_color = $ratio_ok ? '#16a34a' : '#d97706';
                    $ratio_icon  = $ratio_ok ? 'fa-circle-check' : 'fa-circle-minus';
                    $ratio_label = $ratio_ok ? 'Équilibré' : 'À surveiller';

                    $projected_end = $total_expenses + ($daily_average * $days_remaining);
                    if ($projected_end <= $monthly_budget * 0.80) {
                        $proj_color = '#16a34a'; $proj_icon = 'fa-circle-check'; $proj_label = 'Bonne trajectoire';
                    } elseif ($projected_end <= $monthly_budget) {
                        $proj_color = '#d97706'; $proj_icon = 'fa-circle-minus'; $proj_label = 'Limite acceptable';
                    } else {
                        $proj_color = '#dc2626'; $proj_icon = 'fa-circle-xmark'; $proj_label = 'Dépassement probable';
                    }
                    $proj_pct = $monthly_budget > 0 ? min(($projected_end / $monthly_budget) * 100, 100) : 0;
                    ?>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-piggy-bank" style="color:<?php echo $savings_color; ?>"></i>
                                <span class="fw-semibold" style="font-size:.9rem;">Taux d'épargne</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold" style="color:<?php echo $savings_color; ?>;">
                                    <?php echo number_format($real_savings_rate, 1); ?>%
                                </span>
                                <i class="fas <?php echo $savings_icon; ?>" style="color:<?php echo $savings_color; ?>"></i>
                                <span class="badge rounded-pill" style="background:<?php echo $savings_color; ?>;font-size:.72rem;">
                                    <?php echo $savings_label; ?>
                                </span>
                            </div>
                        </div>
                        <div class="progress" style="height:8px;border-radius:6px;background:#e5e7eb;">
                            <div class="progress-bar" style="width:<?php echo min($real_savings_rate, 100); ?>%;background:<?php echo $savings_color; ?>;border-radius:6px;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted" style="font-size:.72rem;">Objectif recommandé : 20%</small>
                            <small class="text-muted" style="font-size:.72rem;"><?php echo formatCurrency($current_savings); ?> épargnés</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-scale-balanced" style="color:<?php echo $ratio_color; ?>"></i>
                                <span class="fw-semibold" style="font-size:.9rem;">Ratio 50/30</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas <?php echo $ratio_icon; ?>" style="color:<?php echo $ratio_color; ?>"></i>
                                <span class="badge rounded-pill" style="background:<?php echo $ratio_color; ?>;font-size:.72rem;">
                                    <?php echo $ratio_label; ?>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex gap-1" style="height:8px;border-radius:6px;overflow:hidden;">
                            <div style="width:<?php echo min($needs_pct, 60); ?>%;background:#2563EB;border-radius:6px 0 0 6px;" title="Besoins : <?php echo $needs_pct; ?>%"></div>
                            <div style="width:<?php echo min($wants_pct, 40); ?>%;background:#7C3AED;" title="Envies : <?php echo $wants_pct; ?>%"></div>
                            <div style="flex:1;background:#e5e7eb;border-radius:0 6px 6px 0;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small style="color:#2563EB;font-size:.72rem;"><i class="fas fa-circle me-1" style="font-size:.55rem;"></i>Besoins <?php echo $needs_pct; ?>% (max 50%)</small>
                            <small style="color:#7C3AED;font-size:.72rem;"><i class="fas fa-circle me-1" style="font-size:.55rem;"></i>Envies <?php echo $wants_pct; ?>% (max 30%)</small>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-chart-line" style="color:<?php echo $proj_color; ?>"></i>
                                <span class="fw-semibold" style="font-size:.9rem;">Projection fin de cycle</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas <?php echo $proj_icon; ?>" style="color:<?php echo $proj_color; ?>"></i>
                                <span class="badge rounded-pill" style="background:<?php echo $proj_color; ?>;font-size:.72rem;">
                                    <?php echo $proj_label; ?>
                                </span>
                            </div>
                        </div>
                        <div class="progress" style="height:8px;border-radius:6px;background:#e5e7eb;">
                            <div class="progress-bar" style="width:<?php echo $proj_pct; ?>%;background:<?php echo $proj_color; ?>;border-radius:6px;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted" style="font-size:.72rem;">Projection : <?php echo formatCurrency(round($projected_end)); ?></small>
                            <small class="text-muted" style="font-size:.72rem;">Budget : <?php echo formatCurrency($monthly_budget); ?></small>
                        </div>
                    </div>

                    <?php if ($safety_margin > 0): ?>
                    <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted d-flex align-items-center gap-1">
                            <i class="fas fa-shield-alt text-success"></i>
                            Marge de sécurité non allouée
                        </small>
                        <span class="fw-bold text-success"><?php echo formatCurrency($safety_margin); ?></span>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
        <div class="col-md-6">
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
                    🎯 Mes Objectifs d'Épargne
                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#savingGoalsModal"><i class="fas fa-cog me-1"></i>Modifier</button>
                </div>
                <div class="card-body">
                    <?php if (!empty($saving_goals)): foreach ($saving_goals as $key => $goal):
                        $pct_goal = $goal['target'] > 0 ? min(($goal['current'] / $goal['target']) * 100, 100) : 0;
                        $rem_goal = $goal['target'] - $goal['current'];
                        $dl       = new DateTime($goal['deadline']);
                        $iv       = (new DateTime())->diff($dl);
                        $mo_rem   = max(($iv->y * 12) + $iv->m, 1);
                        $wk_rem   = max(ceil($iv->days / 7), 1);
                        $mo_save  = $rem_goal > 0 ? ceil($rem_goal / $mo_rem) : 0;
                        $wk_save  = $rem_goal > 0 ? ceil($rem_goal / $wk_rem) : 0;
                        $pc       = $pct_goal >= 100 ? 'bg-success' : ($pct_goal >= 75 ? 'bg-warning' : ($pct_goal >= 50 ? 'bg-info' : 'bg-primary'));
                    ?>
                    <div class="goal-card mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between mb-2">
                            <h6 class="mb-0"><?php echo htmlspecialchars($goal['name']); ?></h6>
                            <span class="badge <?php echo $pct_goal >= 100 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo round($pct_goal); ?>%</span>
                        </div>
                        <div class="progress mb-2" style="height:10px;">
                            <div class="progress-bar <?php echo $pc; ?>" style="width:<?php echo $pct_goal; ?>%;"></div>
                        </div>
                        <div class="row text-center mb-2">
                            <div class="col-6"><small class="text-muted d-block">Épargné</small><strong><?php echo formatCurrency($goal['current']); ?></strong></div>
                            <div class="col-6"><small class="text-muted d-block">Objectif</small><strong><?php echo formatCurrency($goal['target']); ?></strong></div>
                        </div>
                        <?php if ($pct_goal < 100): ?>
                        <div class="p-2 bg-light rounded small">
                            <div class="fw-semibold mb-1"><i class="fas fa-lightbulb me-1 text-warning"></i>Pour atteindre ton objectif :</div>
                            <?php if ($mo_rem >= 2): ?><div><?php echo formatCurrency($mo_save); ?> / mois (<?php echo $mo_rem; ?> mois)</div><?php endif; ?>
                            <div><?php echo formatCurrency($wk_save); ?> / semaine (<?php echo $wk_rem; ?> semaines)</div>
                            <div class="text-muted mt-1"><i class="fas fa-hourglass-end me-1"></i>Échéance : <?php echo date('d/m/Y', strtotime($goal['deadline'])); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="p-2 bg-success bg-opacity-10 rounded small text-success"><i class="fas fa-check-circle me-1"></i>Objectif atteint ! 🎉</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-bullseye fa-2x mb-2 opacity-50"></i>
                        <p>Aucun objectif défini</p>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#savingGoalsModal"><i class="fas fa-plus me-1"></i>Créer un objectif</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
