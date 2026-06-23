<?php if ($error): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success d-flex align-items-center gap-2">
    <i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>
