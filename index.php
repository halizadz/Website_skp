<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Cek session dengan cara yang lebih aman
if (session_status() !== PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/project_fkip');

// Redirect ke login jika belum login
if (!isset($_SESSION['level']) && basename($_SERVER['SCRIPT_NAME']) === 'index.php') {
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit();
}

// Daftar halaman dengan require_once untuk menghindari duplikasi
$available_pages = [
    'admin' => [
        'dashboard' => 'admin/dashboard.php',
        'listMahasiswa' => 'admin/listMahasiswa.php',
        'approve' => 'admin/Approve.php',
        'statistics' => 'assets/statistics.php',
        'profile' => 'profile.php'
    ],
    'mahasiswa' => [
        'dashboard' => 'mahasiswa/dashboard.php',
        'skp' => 'tmp/daftar_skp.php',
        'addSkp' => 'tmp/addSkp.php',
        'profile' => 'profile.php'
    ],
    'common' => [
        'login' => 'pages/login.php',
        'logout' => 'pages/logout.php',
        'register' => 'pages/register.php',
        'home' => 'index.php'
    ]
];


// Handle halaman umum
if (isset($available_pages['common'][$page])) {
    require_once $available_pages['common'][$page];
    exit();
}

// Verifikasi login
if (!isset($_SESSION['level'])) {
    header('Location: ?x=login');
    exit();
}

// Verifikasi akses role
$role = $_SESSION['level'];
if (!isset($available_pages[$role][$page])) {
    header('Location: ?x=dashboard');
    exit();
}

// Gunakan require_once untuk menghindari duplikasi memori
require_once $available_pages[$role][$page];
exit();