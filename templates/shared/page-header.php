<?php
/**
 * Reusable Page Header Component
 *
 * Usage:
 * include ROOT_PATH . '/templates/shared/page-header.php';
 *
 * Variables required:
 * - $pageTitle: Title of the page
 * - $pageIcon (optional): Font Awesome icon class
 * - $pageDescription (optional): Subtitle/description text
 */

$pageIcon = $pageIcon ?? 'fas fa-file-alt';
$pageDescription = $pageDescription ?? '';
?>

<div class="page-header">
    <h2>
        <i class="<?= htmlspecialchars($pageIcon) ?>"></i>
        <?= htmlspecialchars($pageTitle) ?>
    </h2>
    <?php if ($pageDescription): ?>
        <p class="text-muted mb-0 mt-2"><?= htmlspecialchars($pageDescription) ?></p>
    <?php endif; ?>
</div>
