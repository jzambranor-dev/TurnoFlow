<?php
$user = $_SESSION['user'] ?? null;
$isCoordinador = ($user['rol'] ?? '') === 'coordinador';
$currentPage = $currentPage ?? '';
?>
<div id="kt_app_sidebar" class="app-sidebar flex-column"
     data-kt-drawer="true"
     data-kt-drawer-name="app-sidebar"
     data-kt-drawer-activate="{default: true, lg: false}"
     data-kt-drawer-overlay="true"
     data-kt-drawer-width="225px"
     data-kt-drawer-direction="start"
     data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <!-- Logo -->
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="<?= BASE_URL ?>/dashboard">
            <span class="text-white fs-2 fw-bold">TurnoFlow</span>
        </a>

        <!-- Toggle -->
        <div id="kt_app_sidebar_toggle"
             class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
             data-kt-toggle="true"
             data-kt-toggle-state="active"
             data-kt-toggle-target="body"
             data-kt-toggle-name="app-sidebar-minimize">
            <i class="ki-outline ki-black-left-line fs-3 rotate-180"></i>
        </div>
    </div>

    <!-- Menu -->
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
            <div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3"
                 data-kt-scroll="true"
                 data-kt-scroll-activate="true"
                 data-kt-scroll-height="auto"
                 data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
                 data-kt-scroll-wrappers="#kt_app_sidebar_menu"
                 data-kt-scroll-offset="5px"
                 data-kt-scroll-save-state="true">

                <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6"
                     id="kt_app_sidebar_menu"
                     data-kt-menu="true"
                     data-kt-menu-expand="false">

                    <!-- Dashboard -->
                    <div class="menu-item">
                        <a class="menu-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
                           href="<?= BASE_URL ?>/dashboard">
                            <span class="menu-icon">
                                <i class="ki-outline ki-element-11 fs-2"></i>
                            </span>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </div>

                    <!-- Separador -->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Operaciones</span>
                        </div>
                    </div>

                    <!-- Horarios -->
                    <div data-kt-menu-trigger="click" class="menu-item menu-accordion <?= in_array($currentPage, ['schedules', 'schedules-import']) ? 'here show' : '' ?>">
                        <span class="menu-link">
                            <span class="menu-icon">
                                <i class="ki-outline ki-calendar fs-2"></i>
                            </span>
                            <span class="menu-title">Horarios</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <div class="menu-sub menu-sub-accordion">
                            <div class="menu-item">
                                <a class="menu-link <?= $currentPage === 'schedules' ? 'active' : '' ?>"
                                   href="<?= BASE_URL ?>/schedules">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Ver Horarios</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link <?= $currentPage === 'schedules-import' ? 'active' : '' ?>"
                                   href="<?= BASE_URL ?>/schedules/import">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Importar Dimensionamiento</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if ($isCoordinador): ?>
                    <!-- Separador - Solo Coordinador -->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Administración</span>
                        </div>
                    </div>

                    <!-- Campañas -->
                    <div class="menu-item">
                        <a class="menu-link <?= $currentPage === 'campaigns' ? 'active' : '' ?>"
                           href="<?= BASE_URL ?>/campaigns">
                            <span class="menu-icon">
                                <i class="ki-outline ki-abstract-26 fs-2"></i>
                            </span>
                            <span class="menu-title">Campañas</span>
                        </a>
                    </div>

                    <!-- Asesores -->
                    <div class="menu-item">
                        <a class="menu-link <?= $currentPage === 'advisors' ? 'active' : '' ?>"
                           href="<?= BASE_URL ?>/advisors">
                            <span class="menu-icon">
                                <i class="ki-outline ki-people fs-2"></i>
                            </span>
                            <span class="menu-title">Asesores</span>
                        </a>
                    </div>

                    <!-- Configuración -->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Configuración</span>
                        </div>
                    </div>

                    <div class="menu-item">
                        <a class="menu-link <?= $currentPage === 'config-hours' ? 'active' : '' ?>"
                           href="<?= BASE_URL ?>/config/hours">
                            <span class="menu-icon">
                                <i class="ki-outline ki-time fs-2"></i>
                            </span>
                            <span class="menu-title">Horas Mensuales</span>
                        </a>
                    </div>

                    <?php else: ?>
                    <!-- Items bloqueados para supervisor -->
                    <div class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-7">Administración</span>
                        </div>
                    </div>

                    <div class="menu-item">
                        <span class="menu-link cursor-not-allowed" title="Solo Coordinador">
                            <span class="menu-icon">
                                <i class="ki-outline ki-lock fs-2 text-muted"></i>
                            </span>
                            <span class="menu-title text-muted">Campañas</span>
                        </span>
                    </div>

                    <div class="menu-item">
                        <span class="menu-link cursor-not-allowed" title="Solo Coordinador">
                            <span class="menu-icon">
                                <i class="ki-outline ki-lock fs-2 text-muted"></i>
                            </span>
                            <span class="menu-title text-muted">Asesores</span>
                        </span>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="app-sidebar-footer flex-column-auto pt-2 pb-6 px-6" id="kt_app_sidebar_footer">
        <a href="<?= BASE_URL ?>/logout"
           class="btn btn-flex flex-center btn-custom btn-primary overflow-hidden text-nowrap px-0 h-40px w-100">
            <span class="btn-label">Cerrar Sesión</span>
            <i class="ki-outline ki-exit-right btn-icon fs-2 m-0"></i>
        </a>
    </div>
</div>
