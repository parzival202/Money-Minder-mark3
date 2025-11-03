<?php
// archives.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_bot.php';

// Initialisation base de données et utilisateur par défaut
init_db();
$user_id = ensure_default_user();

// Récupération des archives uniquement via la base de données persistante
$__archives_by_month = [];
$db_archives = fetchArchives($user_id);
foreach ($db_archives as $arc) {
    $data = $arc['data'] ?? [];
    $__archives_by_month[$arc['id']] = [
        'label' => $arc['month_year'],
        'categories' => $data['budgets'] ?? [],
        'total' => $arc['total_expenses'] ?? array_sum($data['budgets'] ?? []),
        'days' => 30 // Optionally compute days if needed
    ];
}
uasort($__archives_by_month, function($a,$b){ return strcmp($b['label'],$a['label']); });

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Archives - Money Minder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root{
            --primary:#6D28D9; --secondary:#F472B6; --success:#60A5FA; --danger:#e74c3c;
            --warning:#f39c12; --info:#1abc9c; --light:#EEF2FF; --dark:#6B46C1;
        }
        body{ background:#EEF2FF; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar{ background:var(--primary); }
        .card{ border:none; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,.12); margin-bottom:24px; }
        .chart-container { position: relative; height: 380px; width: 100%; }
    </style>
</head>
<body>

<header class="bg-light border-bottom shadow-sm mb-4">
    <div class="container d-flex justify-content-between align-items-center py-2">
        <div class="d-flex align-items-center">
            <img src="assets/logo2.png" alt="Logo" height="80" class="me-1">
            <div>
                <h5 class="mb-0 fw-bold" style="color: #6537F3;">Money Minder</h5>
                <small class="text-muted">Archives des périodes précédentes</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button id="archive-current-btn" class="btn btn-success btn-sm"><i class="fas fa-archive me-1"></i>Archiver le mois actuel</button>
            <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Retour au tableau de bord</a>
        </div>
    </div>
</header>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">Archives</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="list-group" id="archive-months-list">
                        <?php if (!empty($__archives_by_month)): ?>
                            <?php foreach ($__archives_by_month as $__k => $__arc): ?>
                                <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="showArchive('<?php echo $__k; ?>')">
                                    <span><?php echo htmlspecialchars($__arc['label']); ?></span>
                                    <span class="badge bg-secondary"><?php echo formatCurrency($__arc['total']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">Aucune archive disponible.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-8">
                    <div id="archive-details">
                        <?php if (!empty($__archives_by_month)): ?>
                            <?php foreach ($__archives_by_month as $__k => $__arc):
                                $__labels = array_keys($__arc['categories']);
                                $__values = array_values($__arc['categories']);
                                $__savings = max(0, (isset($_SESSION['monthly_budget']) ? floatval($_SESSION['monthly_budget']) : 0) - $__arc['total']);
                                $__avg = $__arc['days'] > 0 ? ($__arc['total'] / $__arc['days']) : 0;
                            ?>
                                <div id="archive_<?php echo $__k; ?>" class="archive-detail card mb-3" style="display:none;">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($__arc['label']); ?></h5>
                                        <div class="row g-3 align-items-center">
                                            <div class="col-6">
                                                <div class="chart-container">
                                                    <canvas id="arc_chart_<?php echo $__k; ?>"></canvas>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <ul class="list-group">
                                                    <li class="list-group-item d-flex justify-content-between"><span>Total dépensé</span><strong><?php echo formatCurrency($__arc['total']); ?></strong></li>
                                                    <li class="list-group-item d-flex justify-content-between"><span>Total économisé</span><strong><?php echo formatCurrency($__savings); ?></strong></li>
                                                    <li class="list-group-item d-flex justify-content-between"><span>Dépenses moy. / jour</span><strong><?php echo formatCurrency(round($__avg)); ?></strong></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                    (function(){
                                        var ctx = document.getElementById('arc_chart_<?php echo $__k; ?>');
                                        if(!ctx) return;
                                        new Chart(ctx, {
                                            type: 'pie',
                                            data: {
                                                labels: <?php echo json_encode($__labels); ?>,
                                                datasets: [{
                                                    data: <?php echo json_encode($__values); ?>,
                                                    backgroundColor: [
                                                        'var(--primary)', 'var(--secondary)', '#FFCE56', '#4BC0C0',
                                                        '#9966FF', '#FF9F40', '#8AC926', '#1982C4'
                                                    ]
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        position: 'bottom'
                                                    }
                                                }
                                            }
                                        });
                                    })();
                                    </script>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showArchive(id){
    document.querySelectorAll('#archive-details .archive-detail').forEach(el => el.style.display='none');
    var el = document.getElementById('archive_'+id);
    if(el) el.style.display='block';
}

document.getElementById('archive-current-btn').addEventListener('click', function() {
    if (confirm('Êtes-vous sûr de vouloir archiver le mois actuel ? Cette action est irréversible.')) {
        fetch('api/archive-current-month.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload(); // Reload to show new archive
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors de l\'archivage.');
        });
    }
});
</script>

</body>
</html>
