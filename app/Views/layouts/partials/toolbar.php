<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$breadcrumbs = $breadcrumbs ?? [];
?>
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                <?= htmlspecialchars($pageTitle) ?>
            </h1>

            <?php if (!empty($breadcrumbs)): ?>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="<?= BASE_URL ?>/dashboard" class="text-muted text-hover-primary">
                        <i class="ki-outline ki-home fs-6 text-muted me-1"></i>
                    </a>
                </li>
                <?php foreach ($breadcrumbs as $crumb): ?>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-500 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <?php if (!empty($crumb['url'])): ?>
                        <a href="<?= htmlspecialchars($crumb['url']) ?>" class="text-muted text-hover-primary">
                            <?= htmlspecialchars($crumb['label']) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($crumb['label']) ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <?php if (!empty($toolbarActions)): ?>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <?= $toolbarActions ?>
        </div>
        <?php endif; ?>
    </div>
</div>
