<?php
require_once 'db.php';
init_db();
$user_id = ensure_default_user();

$categories = ['Alimentation', 'Transport', 'Loisirs/Sortie', 'Mode', 'Aide proche', 'Abonnement mensuel'];
$descriptions = ['Achat', 'Paiement', 'Dépense', 'Facture', 'Achat en ligne'];

for($i=0; $i<50; $i++) {
    $category = $categories[array_rand($categories)];
    $description = $descriptions[array_rand($descriptions)] . ' ' . ($i+1);
    $amount = rand(1000, 20000); // Random amount between 1000 and 20000 FCFA
    $date = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));

    insertExpense($user_id, [
        'date' => $date,
        'category' => $category,
        'description' => $description,
        'amount' => $amount
    ]);
}
echo 'Inserted 50 varied expenses\n';
?>
