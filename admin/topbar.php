<?php if (!isset($pageTitle)) { $pageTitle = 'Tableau de bord'; } ?>
<div class="topbar">
    <button class="menu-toggle" type="button" aria-label="Menu" aria-expanded="false" aria-controls="sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
    <div class="topbar-title"><?php echo htmlspecialchars($pageTitle); ?></div>
    <?php include_once __DIR__ . '/../notification_assets.php'; ?>
</div>
<div class="overlay"></div>
