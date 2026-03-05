<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title><?= htmlspecialchars($pageTitle ?? 'TurnoFlow') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/system-horario/TurnoFlow/dist/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <?php if (!empty($extraStyles)) foreach ($extraStyles as $s) echo $s . "\n"; ?>
</head>
<body id="kt_body" class="header-fixed header-mobile-fixed subheader-enabled page-loading">

    <?php $user = $_SESSION['user'] ?? null; ?>

    <!-- Header Mobile -->
    <div id="kt_header_mobile" class="header-mobile bg-primary header-mobile-fixed">
        <a href="<?= BASE_URL ?>/dashboard">
            <span class="text-white font-weight-bolder font-size-h4">TurnoFlow</span>
        </a>
        <div class="d-flex align-items-center">
            <button class="btn p-0 burger-icon burger-icon-left ml-4" id="kt_header_mobile_toggle">
                <span></span>
            </button>
        </div>
    </div>

    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-row flex-column-fluid page">
            <div class="d-flex flex-column flex-row-fluid wrapper" id="kt_wrapper">

                <!-- Header -->
                <div id="kt_header" class="header header-fixed bg-primary">
                    <div class="container d-flex align-items-stretch justify-content-between">
                        <div class="d-flex align-items-stretch mr-3">
                            <div class="header-logo">
                                <a href="<?= BASE_URL ?>/dashboard">
                                    <span class="text-white font-weight-bolder font-size-h3">TurnoFlow</span>
                                </a>
                            </div>

                            <div class="header-menu-wrapper header-menu-wrapper-left" id="kt_header_menu_wrapper">
                                <div id="kt_header_menu" class="header-menu header-menu-left header-menu-mobile header-menu-layout-default">
                                    <ul class="menu-nav">
                                        <li class="menu-item <?= ($currentPage ?? '') === 'dashboard' ? 'menu-item-active' : '' ?>">
                                            <a href="<?= BASE_URL ?>/dashboard" class="menu-link">
                                                <span class="menu-text">Dashboard</span>
                                            </a>
                                        </li>
                                        <li class="menu-item <?= ($currentPage ?? '') === 'schedules' ? 'menu-item-active' : '' ?>">
                                            <a href="<?= BASE_URL ?>/schedules" class="menu-link">
                                                <span class="menu-text">Horarios</span>
                                            </a>
                                        </li>
                                        <?php if (($user['rol'] ?? '') === 'coordinador'): ?>
                                        <li class="menu-item <?= ($currentPage ?? '') === 'campaigns' ? 'menu-item-active' : '' ?>">
                                            <a href="<?= BASE_URL ?>/campaigns" class="menu-link">
                                                <span class="menu-text">Campanas</span>
                                            </a>
                                        </li>
                                        <li class="menu-item <?= ($currentPage ?? '') === 'advisors' ? 'menu-item-active' : '' ?>">
                                            <a href="<?= BASE_URL ?>/advisors" class="menu-link">
                                                <span class="menu-text">Asesores</span>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Topbar -->
                        <div class="topbar">
                            <div class="topbar-item">
                                <div class="btn btn-icon btn-clean btn-lg mr-1 dropdown" data-toggle="dropdown">
                                    <span class="svg-icon svg-icon-xl svg-icon-white">
                                        <span class="font-weight-bolder text-white"><?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?></span>
                                    </span>
                                </div>
                                <div class="dropdown-menu p-0 m-0 dropdown-menu-right dropdown-menu-anim-up dropdown-menu-lg">
                                    <div class="d-flex align-items-center p-8 rounded-top">
                                        <div class="symbol symbol-md bg-light-primary mr-3 flex-shrink-0">
                                            <span class="font-weight-bolder font-size-h5 text-primary"><?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?></span>
                                        </div>
                                        <div class="text-dark m-0 flex-grow-1 mr-3 font-size-h5">
                                            <?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?>
                                        </div>
                                        <span class="label label-light-success label-inline font-weight-bold">
                                            <?= ucfirst($user['rol'] ?? 'usuario') ?>
                                        </span>
                                    </div>
                                    <div class="separator separator-solid"></div>
                                    <div class="navi navi-spacer-x-0 pt-5">
                                        <a href="<?= BASE_URL ?>/logout" class="navi-item px-8">
                                            <div class="navi-link">
                                                <div class="navi-text">
                                                    <div class="font-weight-bold">Cerrar Sesion</div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <!-- Subheader -->
                    <div class="subheader py-2 py-lg-6 subheader-solid" id="kt_subheader">
                        <div class="container d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
                            <div class="d-flex align-items-center flex-wrap mr-1">
                                <div class="d-flex align-items-baseline flex-wrap mr-5">
                                    <h5 class="text-dark font-weight-bold my-1 mr-5"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="d-flex flex-column-fluid">
                        <div class="container">
                            <?= $content ?? '' ?>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="footer bg-white py-4 d-flex flex-lg-column" id="kt_footer">
                    <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-dark order-2 order-md-1">
                            <span class="text-muted font-weight-bold mr-2"><?= date('Y') ?></span>
                            <span class="text-dark-75">TurnoFlow - Sistema de Gestion de Horarios</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var KTAppSettings = {
            "breakpoints": {"sm": 576, "md": 768, "lg": 992, "xl": 1200, "xxl": 1200},
            "colors": {
                "theme": {
                    "base": {"white": "#ffffff", "primary": "#6993FF", "secondary": "#E5EAEE", "success": "#1BC5BD", "info": "#8950FC", "warning": "#FFA800", "danger": "#F64E60", "light": "#F3F6F9", "dark": "#212121"},
                    "light": {"white": "#ffffff", "primary": "#E1E9FF", "secondary": "#ECF0F3", "success": "#C9F7F5", "info": "#EEE5FF", "warning": "#FFF4DE", "danger": "#FFE2E5", "light": "#F3F6F9", "dark": "#D6D6E0"},
                    "inverse": {"white": "#ffffff", "primary": "#ffffff", "secondary": "#212121", "success": "#ffffff", "info": "#ffffff", "warning": "#ffffff", "danger": "#ffffff", "light": "#464E5F", "dark": "#ffffff"}
                },
                "gray": {"gray-100": "#F3F6F9", "gray-200": "#ECF0F3", "gray-300": "#E5EAEE", "gray-400": "#D6D6E0", "gray-500": "#B5B5C3", "gray-600": "#80808F", "gray-700": "#464E5F", "gray-800": "#1B283F", "gray-900": "#212121"}
            },
            "font-family": "Poppins"
        };
    </script>
    <script src="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.js"></script>
    <script src="/system-horario/TurnoFlow/dist/assets/js/scripts.bundle.js"></script>
    <?php if (!empty($extraScripts)) foreach ($extraScripts as $s) echo $s . "\n"; ?>
</body>
</html>
