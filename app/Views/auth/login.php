<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Login - TurnoFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" />
    <link href="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/system-horario/TurnoFlow/dist/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .login-aside {
            background: linear-gradient(147.04deg, #1a1a2e 0.74%, #16213e 99.61%);
        }
        .login-aside::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4);
        }
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .feature-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        .form-control-custom {
            background-color: #f5f8fa;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control-custom:focus {
            background-color: #fff;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        .input-group-custom {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
        }
        .input-with-icon {
            padding-left: 3rem !important;
        }
    </style>
</head>
<body id="kt_body" class="header-fixed subheader-enabled page-loading">
    <div class="d-flex flex-column flex-root">
        <div class="login login-1 login-signin-on d-flex flex-column flex-lg-row flex-column-fluid bg-white" id="kt_login">

            <!-- Aside -->
            <div class="login-aside d-flex flex-column flex-row-auto position-relative" style="width: 45%;">
                <div class="d-flex flex-column-auto flex-column pt-lg-40 pt-15 px-10 position-relative z-index-1">
                    <!-- Logo -->
                    <div class="text-center mb-15">
                        <div class="brand-logo mx-auto mb-5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="white"/>
                                <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67V7z" fill="white"/>
                            </svg>
                        </div>
                        <h1 class="font-weight-bolder text-white font-size-h1 mb-2">TurnoFlow</h1>
                        <p class="text-white-50 font-size-h6 font-weight-bold">Sistema de Gestión de Horarios</p>
                    </div>

                    <!-- Features -->
                    <div class="px-5">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="white"/>
                                    <path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="white"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-white font-weight-bolder mb-0">Gestión de Horarios</h5>
                                <span class="text-white-50">Planificacion automatica de turnos</span>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M6.28548 15.0861C7.34369 13.1814 9.35142 12 11.5304 12H12.4696C14.6486 12 16.6563 13.1814 17.7145 15.0861L19.3493 18.0287C20.0899 19.3618 19.1259 21 17.601 21H6.39903C4.87406 21 3.91012 19.3618 4.65071 18.0287L6.28548 15.0861Z" fill="white"/>
                                    <rect opacity="0.3" x="8" y="3" width="8" height="8" rx="4" fill="white"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-white font-weight-bolder mb-0">Control de Asesores</h5>
                                <span class="text-white-50">Seguimiento de horas y asistencia</span>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M14 3V21H10V3C10 2.4 10.4 2 11 2H13C13.6 2 14 2.4 14 3ZM7 14H5C4.4 14 4 14.4 4 15V21H8V15C8 14.4 7.6 14 7 14Z" fill="white"/>
                                    <path d="M21 20H20V8C20 7.4 19.6 7 19 7H17C16.4 7 16 7.4 16 8V20H3C2.4 20 2 20.4 2 21C2 21.6 2.4 22 3 22H21C21.6 22 22 21.6 22 21C22 20.4 21.6 20 21 20Z" fill="white"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-white font-weight-bolder mb-0">Reportes Detallados</h5>
                                <span class="text-white-50">Metricas y analisis en tiempo real</span>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path opacity="0.3" d="M20.5543 4.37824L12.1798 2.02473C12.0626 1.99176 11.9376 1.99176 11.8203 2.02473L3.44572 4.37824C3.18118 4.45258 3 4.6807 3 4.93945V13.569C3 14.6914 3.48509 15.8404 4.4417 16.984C5.17231 17.8575 6.18314 18.7345 7.446 19.5909C9.56752 21.0295 11.6566 21.912 11.7445 21.9488C11.8258 21.9829 11.9129 22 12.0001 22C12.0872 22 12.1744 21.983 12.2557 21.9488C12.3435 21.912 14.4326 21.0295 16.5541 19.5909C17.8169 18.7345 18.8277 17.8575 19.5584 16.984C20.515 15.8404 21 14.6914 21 13.569V4.93945C21 4.6807 20.8189 4.45258 20.5543 4.37824Z" fill="white"/>
                                    <path d="M14.854 11.321C14.7568 11.2282 14.6388 11.1818 14.4998 11.1818H14.3333V10.2272C14.3333 9.61741 14.1041 9.09378 13.6458 8.65628C13.1875 8.21876 12.639 8 12 8C11.361 8 10.8124 8.21876 10.3541 8.65626C9.89574 9.09378 9.66663 9.6174 9.66663 10.2272V11.1818H9.49999C9.36115 11.1818 9.24306 11.2282 9.14583 11.321C9.0486 11.4138 9 11.5265 9 11.6591V14.5765C9 14.7091 9.0486 14.8216 9.14583 14.9145C9.24306 15.0073 9.36115 15.0536 9.49999 15.0536H14.5C14.6389 15.0536 14.7569 15.0073 14.8542 14.9145C14.9513 14.8216 15 14.7091 15 14.5765V11.6591C15 11.5265 14.9513 11.4138 14.854 11.321ZM13.166 11.1818H10.833V10.2272C10.833 9.95354 10.9288 9.72159 11.1203 9.53125C11.3118 9.34091 11.5409 9.24569 11.8077 9.24569H12.1922C12.459 9.24569 12.6882 9.34091 12.8796 9.53125C13.0712 9.72159 13.166 9.95354 13.166 10.2272V11.1818Z" fill="white"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-white font-weight-bolder mb-0">Acceso Seguro</h5>
                                <span class="text-white-50">Roles y permisos personalizados</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer del aside -->
                <div class="d-flex flex-column-auto flex-column justify-content-end py-10 px-10 position-relative z-index-1">
                    <div class="d-flex align-items-center">
                        <span class="text-white-50 font-size-sm">Powered by</span>
                        <span class="text-white font-weight-bolder font-size-sm ml-2">TurnoFlow v1.0</span>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="login-content flex-row-fluid d-flex flex-column justify-content-center position-relative overflow-hidden p-7" style="width: 55%;">
                <div class="d-flex flex-column-fluid flex-center mt-30 mt-lg-0">
                    <div class="login-form login-signin" style="width: 100%; max-width: 450px;">
                        <form class="form" method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                            <!-- Header -->
                            <div class="text-center mb-15">
                                <h2 class="font-weight-bolder text-dark-75 mb-3" style="font-size: 2rem;">
                                    Bienvenido
                                </h2>
                                <p class="text-muted font-weight-bold font-size-h6">
                                    Ingresa tus credenciales para acceder
                                </p>
                            </div>

                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-custom alert-light-danger fade show mb-10" role="alert">
                                <div class="alert-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="currentColor"/>
                                        <rect x="11" y="14" width="7" height="2" rx="1" transform="rotate(-90 11 14)" fill="currentColor"/>
                                        <rect x="11" y="17" width="2" height="2" rx="1" transform="rotate(-90 11 17)" fill="currentColor"/>
                                    </svg>
                                </div>
                                <div class="alert-text font-weight-bold">
                                    <?php foreach ($errors as $error): ?>
                                        <?= htmlspecialchars($error) ?><br>
                                    <?php endforeach; ?>
                                </div>
                                <div class="alert-close">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true"><i class="ki ki-close"></i></span>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Email -->
                            <div class="form-group mb-8">
                                <label class="font-size-h6 font-weight-bolder text-dark-75 mb-3">
                                    Correo Electronico
                                </label>
                                <div class="input-group-custom">
                                    <span class="input-icon text-muted">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M21 19H3C2.4 19 2 18.6 2 18V6C2 5.4 2.4 5 3 5H21C21.6 5 22 5.4 22 6V18C22 18.6 21.6 19 21 19Z" fill="currentColor"/>
                                            <path d="M21 5H2.99999C2.69999 5 2.49999 5.10005 2.29999 5.30005L11.2 13.3C11.7 13.7 12.4 13.7 12.8 13.3L21.7 5.30005C21.5 5.10005 21.3 5 21 5Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <input class="form-control form-control-custom input-with-icon"
                                           type="email"
                                           name="email"
                                           value="<?= htmlspecialchars($email ?? '') ?>"
                                           placeholder="ejemplo@turnoflow.com"
                                           autocomplete="off"
                                           required />
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="form-group mb-10">
                                <label class="font-size-h6 font-weight-bolder text-dark-75 mb-3">
                                    Contrasena
                                </label>
                                <div class="input-group-custom">
                                    <span class="input-icon text-muted">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M20.5543 4.37824L12.1798 2.02473C12.0626 1.99176 11.9376 1.99176 11.8203 2.02473L3.44572 4.37824C3.18118 4.45258 3 4.6807 3 4.93945V13.569C3 14.6914 3.48509 15.8404 4.4417 16.984C5.17231 17.8575 6.18314 18.7345 7.446 19.5909C9.56752 21.0295 11.6566 21.912 11.7445 21.9488C11.8258 21.9829 11.9129 22 12.0001 22C12.0872 22 12.1744 21.983 12.2557 21.9488C12.3435 21.912 14.4326 21.0295 16.5541 19.5909C17.8169 18.7345 18.8277 17.8575 19.5584 16.984C20.515 15.8404 21 14.6914 21 13.569V4.93945C21 4.6807 20.8189 4.45258 20.5543 4.37824Z" fill="currentColor"/>
                                            <path d="M14.854 11.321C14.7568 11.2282 14.6388 11.1818 14.4998 11.1818H14.3333V10.2272C14.3333 9.61741 14.1041 9.09378 13.6458 8.65628C13.1875 8.21876 12.639 8 12 8C11.361 8 10.8124 8.21876 10.3541 8.65626C9.89574 9.09378 9.66663 9.6174 9.66663 10.2272V11.1818H9.49999C9.36115 11.1818 9.24306 11.2282 9.14583 11.321C9.0486 11.4138 9 11.5265 9 11.6591V14.5765C9 14.7091 9.0486 14.8216 9.14583 14.9145C9.24306 15.0073 9.36115 15.0536 9.49999 15.0536H14.5C14.6389 15.0536 14.7569 15.0073 14.8542 14.9145C14.9513 14.8216 15 14.7091 15 14.5765V11.6591C15 11.5265 14.9513 11.4138 14.854 11.321ZM13.166 11.1818H10.833V10.2272C10.833 9.95354 10.9288 9.72159 11.1203 9.53125C11.3118 9.34091 11.5409 9.24569 11.8077 9.24569H12.1922C12.459 9.24569 12.6882 9.34091 12.8796 9.53125C13.0712 9.72159 13.166 9.95354 13.166 10.2272V11.1818Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <input class="form-control form-control-custom input-with-icon"
                                           type="password"
                                           name="password"
                                           placeholder="Ingresa tu contrasena"
                                           autocomplete="off"
                                           required />
                                </div>
                            </div>

                            <!-- Submit -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-login btn-primary w-100 py-4">
                                    <span class="font-weight-bolder font-size-h6">Iniciar Sesion</span>
                                </button>
                            </div>
                        </form>

                        <!-- Usuarios de prueba (solo para desarrollo) -->
                        <div class="mt-15 pt-10 border-top">
                            <p class="text-center text-muted font-size-sm mb-5">Usuarios de prueba</p>
                            <div class="row">
                                <div class="col-6">
                                    <div class="bg-light-primary rounded p-4 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="label label-primary label-inline mr-2">Admin</span>
                                        </div>
                                        <small class="text-muted d-block mt-2">zajo.deox@gmail.com</small>
                                        <small class="text-muted">Zick913!</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light-success rounded p-4 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="label label-success label-inline mr-2">Coord</span>
                                        </div>
                                        <small class="text-muted d-block mt-2">carlos@turnoflow.com</small>
                                        <small class="text-muted">coord123</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light-warning rounded p-4">
                                        <div class="d-flex align-items-center">
                                            <span class="label label-warning label-inline mr-2">Super</span>
                                        </div>
                                        <small class="text-muted d-block mt-2">maria@turnoflow.com</small>
                                        <small class="text-muted">supervisor123</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light-info rounded p-4">
                                        <div class="d-flex align-items-center">
                                            <span class="label label-info label-inline mr-2">Asesor</span>
                                        </div>
                                        <small class="text-muted d-block mt-2">diana@turnoflow.com</small>
                                        <small class="text-muted">asesor123</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="d-flex justify-content-center align-items-center py-7">
                    <span class="text-muted font-weight-bold font-size-sm">
                        &copy; <?= date('Y') ?> TurnoFlow. Todos los derechos reservados.
                    </span>
                </div>
            </div>
        </div>
    </div>

    <script>
        var KTAppSettings = {
            "breakpoints": {"sm": 576, "md": 768, "lg": 992, "xl": 1200, "xxl": 1200},
            "colors": {
                "theme": {
                    "base": {"white": "#ffffff", "primary": "#667eea", "secondary": "#E5EAEE", "success": "#1BC5BD", "info": "#8950FC", "warning": "#FFA800", "danger": "#F64E60", "light": "#F3F6F9", "dark": "#212121"},
                    "light": {"white": "#ffffff", "primary": "#E1E9FF", "secondary": "#ECF0F3", "success": "#C9F7F5", "info": "#EEE5FF", "warning": "#FFF4DE", "danger": "#FFE2E5", "light": "#F3F6F9", "dark": "#D6D6E0"},
                    "inverse": {"white": "#ffffff", "primary": "#ffffff", "secondary": "#212121", "success": "#ffffff", "info": "#ffffff", "warning": "#ffffff", "danger": "#ffffff", "light": "#464E5F", "dark": "#ffffff"}
                },
                "gray": {"gray-100": "#F3F6F9", "gray-200": "#ECF0F3", "gray-300": "#E5EAEE", "gray-400": "#D6D6E0", "gray-500": "#B5B5C3", "gray-600": "#80808F", "gray-700": "#464E5F", "gray-800": "#1B283F", "gray-900": "#212121"}
            },
            "font-family": "Inter"
        };
    </script>
    <script src="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.js"></script>
    <script src="/system-horario/TurnoFlow/dist/assets/js/scripts.bundle.js"></script>
</body>
</html>
