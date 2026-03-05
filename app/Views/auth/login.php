<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Login - TurnoFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="/system-horario/TurnoFlow/dist/assets/css/pages/login/login-1.css" rel="stylesheet" type="text/css" />
    <link href="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/system-horario/TurnoFlow/dist/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
</head>
<body id="kt_body" class="header-fixed subheader-enabled page-loading">
    <div class="d-flex flex-column flex-root">
        <div class="login login-1 login-signin-on d-flex flex-column flex-lg-row flex-column-fluid bg-white" id="kt_login">

            <!-- Aside -->
            <div class="login-aside d-flex flex-column flex-row-auto" style="background-color: #1e1e2d;">
                <div class="d-flex flex-column-auto flex-column pt-lg-40 pt-15">
                    <a href="#" class="text-center mb-10">
                        <span class="text-white font-size-h1 font-weight-bolder">TurnoFlow</span>
                    </a>
                    <h3 class="font-weight-bolder text-center font-size-h4 font-size-h1-lg text-white">
                        Sistema de Gestion<br/>de Horarios
                    </h3>
                </div>
                <div class="aside-img d-flex flex-row-fluid bgi-no-repeat bgi-position-y-bottom bgi-position-x-center"
                     style="background-image: url(/system-horario/TurnoFlow/dist/assets/media/svg/illustrations/login-visual-1.svg)"></div>
            </div>

            <!-- Content -->
            <div class="login-content flex-row-fluid d-flex flex-column justify-content-center position-relative overflow-hidden p-7 mx-auto">
                <div class="d-flex flex-column-fluid flex-center">
                    <div class="login-form login-signin">
                        <form class="form" method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                            <div class="pb-13 pt-lg-0 pt-5">
                                <h3 class="font-weight-bolder text-dark font-size-h4 font-size-h1-lg">Bienvenido a TurnoFlow</h3>
                                <span class="text-muted font-weight-bold font-size-h4">Ingresa tus credenciales</span>
                            </div>

                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger mb-10">
                                <?php foreach ($errors as $error): ?>
                                    <span><?= htmlspecialchars($error) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label class="font-size-h6 font-weight-bolder text-dark">Email</label>
                                <input class="form-control form-control-solid h-auto py-6 px-6 rounded-lg"
                                       type="email"
                                       name="email"
                                       value="<?= htmlspecialchars($email ?? '') ?>"
                                       autocomplete="off"
                                       required />
                            </div>

                            <div class="form-group">
                                <label class="font-size-h6 font-weight-bolder text-dark pt-5">Contrasena</label>
                                <input class="form-control form-control-solid h-auto py-6 px-6 rounded-lg"
                                       type="password"
                                       name="password"
                                       autocomplete="off"
                                       required />
                            </div>

                            <div class="pb-lg-0 pb-5">
                                <button type="submit" class="btn btn-primary font-weight-bolder font-size-h6 px-8 py-4 my-3">
                                    Ingresar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-lg-start justify-content-center align-items-end py-7 py-lg-0">
                    <div class="text-dark-50 font-size-lg font-weight-bolder">
                        <span><?= date('Y') ?> - TurnoFlow</span>
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
</body>
</html>
