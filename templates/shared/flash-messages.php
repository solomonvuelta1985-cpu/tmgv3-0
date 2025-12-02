<?php
/**
 * Reusable Flash Message Component
 *
 * Usage:
 * include ROOT_PATH . '/templates/shared/flash-messages.php';
 *
 * Displays flash messages from session if they exist
 */

if (isset($_SESSION['flash_message'])):
    $flashType = $_SESSION['flash_type'] ?? 'info';
    $flashMessage = $_SESSION['flash_message'];

    // Clear the flash message immediately
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
        <?php if ($flashType === 'success'): ?>
            <i class="fas fa-check-circle"></i>
        <?php elseif ($flashType === 'danger'): ?>
            <i class="fas fa-exclamation-circle"></i>
        <?php elseif ($flashType === 'warning'): ?>
            <i class="fas fa-exclamation-triangle"></i>
        <?php else: ?>
            <i class="fas fa-info-circle"></i>
        <?php endif; ?>

        <?= $flashMessage ?>

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
