<?php

function replaceLine(string $path, array $replacements): void {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    foreach ($replacements as $lineNumber => $value) {
        $lines[$lineNumber - 1] = $value;
    }
    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

replaceLine(__DIR__ . '/../index.php', [
    229 => "if (isset(\$budgets['Ã‰pargne'])) { \$budgets['Épargne'] = (\$budgets['Épargne'] ?? 0) + (float)\$budgets['Ã‰pargne']; unset(\$budgets['Ã‰pargne']); setBudgets(\$user_id, \$budgets); }",
    230 => "if (!isset(\$budgets['Épargne'])) { \$budgets['Épargne'] = 50000; setBudgets(\$user_id, \$budgets); }",
    317 => "    if (\$cat === 'Épargne') { \$chartColors[] = '#DC3545'; }",
    324 => "\$current_savings     = \$budgets['Épargne'] ?? 0;",
]);

replaceLine(__DIR__ . '/../setup.php', [
    22 => "\$sourceSavings = (float)(\$sourceBudgets['Épargne'] ?? 0);",
    45 => "        foreach ((\$template['category_ratios'] + ['Épargne' => 0]) as \$category => \$_ratio) {",
    48 => "        \$finalBudgets['Épargne'] = \$savingAmount;",
    158 => "                            <?php echo \$category === 'Épargne' ? 'readonly' : ''; ?>>",
]);

replaceLine(__DIR__ . '/../db.php', [
    308 => "    if (isset(\$budgetsAssoc['Ã‰pargne'])) { \$budgetsAssoc['Épargne'] = (\$budgetsAssoc['Épargne'] ?? 0) + (float)\$budgetsAssoc['Ã‰pargne']; unset(\$budgetsAssoc['Ã‰pargne']); }",
]);
