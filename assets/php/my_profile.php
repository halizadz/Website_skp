<?php
require_once(__DIR__ . '/../../config/db.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi autentikasi dan role
if (!isset($_SESSION['role'])) {
    $_SESSION['error_message'] = 'Anda harus login terlebih dahulu';
    header("Location: index.php?x=login");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_SESSION['id'])) {
            throw new Exception('User not authenticated');
        }

        $userId = $_SESSION['id'];
            $nama = trim($_POST['nama'] ?? '');
            $npm = trim($_POST['npm'] ?? '');
            $prodi = trim($_POST['prodi'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($nama) || empty($npm) || empty($prodi) || empty($email)) {
                throw new Exception('Semua field wajib diisi.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            $stmt = $con->prepare("UPDATE users SET nama = ?, npm = ?, prodi = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nama, $npm, $prodi, $email, $userId);

            if (!$stmt->execute()) {
                throw new Exception('Gagal memperbarui data pengguna.');
            }

            // Perbarui session dengan data terbaru
            $_SESSION['nama'] = $nama;
            $_SESSION['prodi'] = $prodi;
            $_SESSION['email'] = $email;

        // Handle upload gambar
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_profil'];
            $maxSize = 2 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                throw new Exception('Ukuran gambar terlalu besar. Maksimal 2MB.');
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Format file tidak didukung. Hanya JPG, PNG, atau GIF.');
            }

            $imageData = file_get_contents($file['tmp_name']);
            $stmt = $con->prepare("UPDATE users SET foto_profil = ?, profile_picture_type = ? WHERE id = ?");
            $stmt->bind_param("ssi", $imageData, $file['type'], $userId);

            if (!$stmt->execute()) {
                if (strpos($con->error, 'max_allowed_packet') !== false) {
                    throw new Exception('Ukuran gambar terlalu besar untuk sistem. Gunakan gambar yang lebih kecil.');
                } else {
                    throw new Exception('Gagal mengupdate foto profil');
                }
            }

            $_SESSION['profile_picture_type'] = $file['type'];
            $_SESSION['success_message'] = 'Foto profil berhasil diperbarui.';
        }

        if (!empty($_POST['password']) || !empty($_POST['old_password'])) {
        // Cek apakah semua field password terisi dengan benar
        if (empty($_POST['old_password'])) {
            throw new Exception('Password lama wajib diisi.');
        }
        
        if (empty($_POST['password'])) {
            throw new Exception('Password baru wajib diisi.');
        }
        
        if (strlen($_POST['password']) < 8) {
            throw new Exception('Password baru minimal 8 karakter.');
        }

        if (!isset($_POST['password_confirm']) || $_POST['password'] !== $_POST['password_confirm']) {
            throw new Exception('Konfirmasi password tidak cocok.');
        }

        // Ambil password dari database untuk verifikasi
        $stmt = $con->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('User tidak ditemukan.');
        }
        
        $user = $result->fetch_assoc();
        $storedPassword = $user['password']; 
        $oldPasswordHashed = hash('sha256', $_POST['old_password']);
        
        // Verifikasi password lama
        if ($storedPassword !== $oldPasswordHashed) {
            throw new Exception('Password lama tidak sesuai.');
        }

        // Hash password baru
        $hashedNewPassword = hash('sha256', $_POST['password']);
        
        // Update password di database
        $passwordStmt = $con->prepare("UPDATE users SET password = ? WHERE id = ?");
        $passwordStmt->bind_param("si", $hashedNewPassword, $userId);
        
        if (!$passwordStmt->execute()) {
            throw new Exception('Gagal memperbarui password: ' . $con->error);
        }
        
       $_SESSION['success_message'] = 'Password berhasil diperbarui.';
        } else {
            $_SESSION['success_message'] = 'Data profil berhasil diperbarui.';
        }
        header("Location: index.php?x=profile");
        exit();

        
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: index.php?x=profile");
        exit();
    }
}

// Fungsi untuk menampilkan gambar profil
function displayProfilePicture($profileData) {
    if (!empty($profileData['foto_profil'])) {
        $mimeType = $profileData['profile_picture_type'] ?? 'image/jpeg';
        return 'data:' . $mimeType . ';base64,' . base64_encode($profileData['foto_profil']);
    }

    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
    );
}
?>
