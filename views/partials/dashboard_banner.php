<?php if ($banner_type): ?>
<div class="alert alert-<?php echo $banner_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
    <i class="fas <?php echo $banner_icon; ?> me-2"></i>
    <div><?php echo $banner_message; ?></div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
