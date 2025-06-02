<?php
if (isset($_COOKIE['admin_session'])) {
    session_name("admin_session");
} elseif (isset($_COOKIE['user_session'])) {
    session_name("user_session");
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



define('BASE_URL', (isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . '/project_fkip');

$page = isset($_GET['x']) ? $_GET['x'] : 'dashboard';
$routes = [
    'admin' => [
        'dashboard' => 'admin/dashboard.php',
        'listMahasiswa' => 'admin/listMahasiswa.php',
        'approve' => 'admin/Approve.php',
        'profile' => 'profile.php'
    ],
    'user' => [
        'dashboard' => 'mahasiswa/dashboard.php',
        'daftar_skp' => 'mahasiswa/daftar_skp.php',
        'addSkp' => 'mahasiswa/addSkp.php',
        'profile' => 'profile.php'
    ],
    'public' => [
        'login' => 'pages/login.php',
        'logout' => 'pages/logout.php',
        'register' => 'pages/register.php',
    ]
];


// Cek apakah halaman public
if (isset($routes['public'][$page])) {
    require_once $routes['public'][$page];
    exit;
}

// Cek apakah user sudah login
if (!isset($_SESSION['role'])) {
    header("Location: " . BASE_URL . "/index.php?x=login");
    exit;
}

// Ambil role user
$role = $_SESSION['role'];

// Cek apakah halaman sesuai dengan role
if (isset($routes[$role][$page])) {
    require_once $routes[$role][$page];
} else {
    // Jika tidak cocok, arahkan ke dashboard default
    header("Location: " . BASE_URL . "/index.php?x=dashboard");
}
exit;