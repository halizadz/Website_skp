<?php
// config/auth_utils.php

function checkUserSession($requiredRole) {
    // Implementasi asli Anda
}

function checkUserSessionWithLoading($requiredRole) {
    // Tampilkan loading screen
    if (!headers_sent()) {
        include 'loading.php';
        flush();
        ob_flush();
    }
    
    // Panggil fungsi check session biasa
    checkUserSession($requiredRole);
    
    // Hapus loading screen dengan JavaScript
    echo '<script>document.querySelector(".loading-overlay").remove();</script>';
}

function safeSessionStart() {
    $sessionLock = session_name() . '.lock';
    $lock = fopen(sys_get_temp_dir() . '/' . $sessionLock, 'w+');
    
    if (!flock($lock, LOCK_EX)) {
        // Jika tidak bisa mendapatkan lock, tunggu sebentar
        usleep(100000); // 100ms
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot obtain session lock');
        }
    }
    
    session_start();
    
    // Simpan referensi ke lock agar tidak dilepas sampai script selesai
    $_SESSION['_lock'] = $lock;
}

function safeSessionWriteClose() {
    session_write_close();
    if (isset($_SESSION['_lock'])) {
        flock($_SESSION['_lock'], LOCK_UN);
        fclose($_SESSION['_lock']);
        unset($_SESSION['_lock']);
    }
}

