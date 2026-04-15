<?php
$user = $_SESSION['user'] ?? null;
?>
<div id="kt_app_header" class="app-header" data-kt-sticky="true"
     data-kt-sticky-activate="{default: true, lg: true}"
     data-kt-sticky-name="app-header-minimize"
     data-kt-sticky-offset="{default: '200px', lg: '0'}"
     data-kt-sticky-animation="false">

    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between"
         id="kt_app_header_container">

        <!-- Sidebar toggle (mobile) -->
        <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Mostrar menú">
            <div class="btn btn-icon btn-active-color-primary w-35px h-35px"
                 id="kt_app_sidebar_mobile_toggle">
                <i class="ki-outline ki-abstract-14 fs-2 fs-md-1"></i>
            </div>
        </div>

        <!-- Logo -->
        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <a href="<?= BASE_URL ?>/dashboard" class="d-lg-none">
                <span class="text-white fs-4 fw-bold">TurnoFlow</span>
            </a>
        </div>

        <!-- Navbar -->
        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1"
             id="kt_app_header_wrapper">

            <!-- Espaciador -->
            <div class="d-flex align-items-stretch"></div>

            <!-- User menu -->
            <div class="app-navbar flex-shrink-0">
                <!-- Notifications Bell -->
                <div class="app-navbar-item ms-1 ms-md-3">
                    <div class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative"
                         data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                         data-kt-menu-attach="parent"
                         data-kt-menu-placement="bottom-end"
                         id="kt_notification_toggle">
                        <i class="ki-outline ki-notification-on fs-2"></i>
                        <span class="badge badge-circle badge-danger position-absolute top-0 end-0 d-none"
                              id="notification_badge" style="font-size: 0.65rem; min-width: 16px; height: 16px;">0</span>
                    </div>

                    <!-- Notifications dropdown -->
                    <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px"
                         data-kt-menu="true" id="kt_notifications_menu">
                        <div class="d-flex flex-column bgi-no-repeat rounded-top"
                             style="background: linear-gradient(135deg, var(--corp-primary) 0%, #1e40af 100%);">
                            <h3 class="text-white fw-semibold px-9 mt-10 mb-6">
                                Notificaciones <span class="fs-8 opacity-75 ps-2" id="notif_count_label">0 nuevas</span>
                            </h3>
                        </div>
                        <div class="scroll-y mh-325px py-3" id="notifications_list">
                            <div class="d-flex flex-column px-9 py-10 text-center">
                                <span class="text-muted fs-7">Cargando...</span>
                            </div>
                        </div>
                        <div class="py-3 text-center border-top">
                            <a href="<?= BASE_URL ?>/notifications" class="btn btn-color-gray-600 btn-active-color-primary">
                                Ver todas <i class="ki-outline ki-arrow-right fs-5"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
                    <div class="cursor-pointer symbol symbol-35px"
                         data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                         data-kt-menu-attach="parent"
                         data-kt-menu-placement="bottom-end">
                        <div class="symbol-label fs-3 bg-light-primary text-primary fw-bold">
                            <?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?>
                        </div>
                    </div>

                    <!-- User dropdown -->
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                         data-kt-menu="true">
                        <div class="menu-item px-3">
                            <div class="menu-content d-flex align-items-center px-3">
                                <div class="symbol symbol-50px me-5">
                                    <div class="symbol-label fs-2 bg-light-primary text-primary fw-bold">
                                        <?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?>
                                    </div>
                                </div>
                                <div class="d-flex flex-column">
                                    <div class="fw-bold d-flex align-items-center fs-5">
                                        <?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?>
                                    </div>
                                    <span class="badge badge-light-<?= in_array($user['rol'] ?? '', ['coordinador', 'admin'], true) ? 'danger' : 'primary' ?> fw-bold fs-8 px-2 py-1">
                                        <?= ucfirst($user['rol'] ?? 'usuario') ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="separator my-2"></div>

                        <div class="menu-item px-5">
                            <a href="<?= BASE_URL ?>/logout" class="menu-link px-5">
                                <i class="ki-outline ki-exit-right fs-4 me-2"></i>
                                Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
