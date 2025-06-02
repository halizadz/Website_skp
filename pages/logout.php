<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kosongkan semua data session
$_SESSION = [];
session_destroy();

// Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ✅ Redirect ke halaman login melalui router
header("Location: index.php?x=login");
exit();
?>
