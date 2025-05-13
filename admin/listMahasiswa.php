<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (isset($_GET['check_update'])) {
    header('Content-Type: application/json');
    
    $last_check = $_SESSION['last_check'] ?? date('Y-m-d H:i:s');
    $user_id = $_SESSION['id'];
    
    // Cek update khusus untuk user ini saja (tidak semua data)
    $query = "SELECT COUNT(*) FROM student_activities WHERE user_id = ? AND updated_at > ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("is", $user_id, $last_check);
    $stmt->execute();
    
    $updated = $stmt->get_result()->fetch_row()[0] > 0;
    $_SESSION['last_check'] = date('Y-m-d H:i:s');
    
    echo json_encode(['updated' => $updated]);
    exit();
}
// Redirect jika bukan admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/login.php"); // Perhatikan path yang benar
    exit();
}

// Koneksi database
require_once __DIR__ . "/../config/db.php";

// Inisialisasi variabel
$search = trim($_GET['search'] ?? '');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$perPage = 10;
$offset = ($page - 1) * $perPage;

$profileData = [];
if (isset($_SESSION['id'])) {
    $stmt = $con->prepare("SELECT nama, npm, prodi, email, foto_profil, profile_picture_type FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileData = $result->fetch_assoc();
}

function displayProfilePicture($profileData) {
    if (!empty($profileData['foto_profil'])) {
        $mimeType = $profileData['profile_picture_type'] ?? 'image/jpeg';
        return 'data:' . $mimeType . ';base64,' . base64_encode($profileData['foto_profil']);
    }
    
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
    );
}

function isActivePage($pageName) {
    return basename($_SERVER['PHP_SELF']) === $pageName;
}

// PROSES CRUD - Dipisahkan menjadi fungsi-fungsi
function updateStudent($con, $data) {
    $stmt = $con->prepare("UPDATE users SET npm=?, nama=?, email=?, prodi=? WHERE id=?");
    $stmt->bind_param("ssssi", $data['npm'], $data['nama'], $data['email'], $data['prodi'], $data['id']);
    return $stmt->execute();
}

function deleteStudent($con, $id) {
    $stmt = $con->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Fungsi helper untuk hashing yang konsisten
function hashPassword($password) {
    return hash('sha256', trim($password)); // trim() untuk menghindari whitespace
}
 
    function resetPassword($con, $id) {
        $defaultPassword = hashPassword('Un1v3rs1t4X!2024');
        $stmt = $con->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $defaultPassword, $id);
        if ($stmt->execute()) {
            session_regenerate_id(true); // Tambahkan ini
            return true;
        }
        return false;
    }


// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $data = [
        'id' => (int)$_POST['id'],
        'npm' => htmlspecialchars(trim($_POST['npm'])),
        'nama' => htmlspecialchars(trim($_POST['nama'])),
        'email' => filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL),
        'prodi' => htmlspecialchars(trim($_POST['prodi']))
    ];
    
    if (updateStudent($con, $data)) {
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'Data berhasil diperbarui!'];
    } else {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Gagal memperbarui data'];
    }
    header("Location: listMahasiswa.php");
    exit();
}

// Handle Actions
if (isset($_GET['action'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$id) {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'ID tidak valid'];
        header("Location: listMahasiswa.php");
        exit();
    }

    switch ($_GET['action']) {
        case 'delete':
            if (deleteStudent($con, $id)) {
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Data dihapus!'];
            } else {
                $_SESSION['notification'] = ['type' => 'error', 'message' => 'Gagal menghapus'];
            }
            break;
            
        case 'reset_password':
            if (resetPassword($con, $id)) {
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Password direset!'];
            } else {
                $_SESSION['notification'] = ['type' => 'error', 'message' => 'Gagal reset password'];
            }
            break;
    }
    
    header("Location: listMahasiswa.php");
    exit();
}

// Query Builder untuk pencarian
function buildStudentQuery($search) {
    $query = "SELECT id, npm, nama, email, prodi, role FROM users WHERE role = 'user'";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (npm LIKE ? OR nama LIKE ? OR email LIKE ? OR prodi LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_fill(0, 4, $searchTerm);
    }
    
    return ['query' => $query, 'params' => $params];
}

// Hitung total data
$countData = buildStudentQuery($search);
$stmt = $con->prepare(str_replace('SELECT id, npm', 'SELECT COUNT(*) as total', $countData['query']));

if (!empty($countData['params'])) {
    $stmt->bind_param(str_repeat('s', count($countData['params'])), ...$countData['params']);
}

$stmt->execute();
$totalStudents = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalStudents / $perPage);

// Ambil data dengan paginasi
$queryData = buildStudentQuery($search);
$queryData['query'] .= " LIMIT ? OFFSET ?";
$queryData['params'][] = $perPage;
$queryData['params'][] = $offset;

$stmt = $con->prepare($queryData['query']);
$types = str_repeat('s', count($queryData['params']) - 2) . 'ii'; // Semua string kecuali 2 terakhir (int)
$stmt->bind_param($types, ...$queryData['params']);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Notifikasi
$notification = $_SESSION['notification'] ?? null;
unset($_SESSION['notification']);

// Data untuk edit
$editStudent = null;
if (isset($_GET['edit'])) {
    $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $con->prepare("SELECT id, npm, nama, email, prodi FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editStudent = $stmt->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>List Mahasiswa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .edit-form-container {
            background: #ffffff;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
        }

        .edit-form-container .card-header {
    border-radius: 10px 10px 0 0 !important;
}

.form-control-lg, .form-select-lg {
    padding: 0.75rem 1rem;
    font-size: 1.05rem;
}

.btn-lg {
    padding: 0.5rem 1.5rem;
    font-size: 1.05rem;
    border-radius: 0.5rem;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}

.table td {
    vertical-align: middle;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-danger:hover {
    background-color: #bb2d3b;
    border-color: #b02a37;
}

.btn-info {
    background-color: #0dcaf0;
    border-color: #0dcaf0;
}

.btn-info:hover {
    background-color: #0da6c7;
    border-color: #0d9cbc;
}
        .badge-admin { background-color: #6f42c1; }
        .badge-user { background-color: #20c997; }
    </style>
</head>
<body>
<div id="app">
    <div class="sidebar" :class="{ 'collapsed': sidebarCollapsed, 'mobile-view': isMobile }">
                <div class="sidebar-header">
                    <div class="logo-container">
                        <div class="logo-icon animate__animated animate__bounceIn">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="logo-text animate__animated animate__fadeIn">Admin Panel</div>
                    </div>
                    <button class="btn btn-link" @click="toggleSidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
                </div>
                
                <ul class="nav flex-column sidebar-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link <?php echo isActivePage('dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="listMahasiswa.php" class="nav-link <?php echo isActivePage('listMahasiswa.php') ? 'active' : ''; ?>">
                            <i class="fas fa-user-graduate"></i>
                            <span>List Mahasiswa</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Approve.php" class="nav-link <?php echo isActivePage('Approve.php') ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Approve</span>
                        </a>
                    </li>
                </ul>
                
                <div class="sidebar-footer p-3">
                    <a href="../pages/logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>


            <!-- Main Content -->
            <div class="main-content" :class="{ 'expanded': sidebarCollapsed }">
                <!-- Top Navbar -->
                <nav class="top-navbar navbar navbar-expand-lg">
                <div class="container-fluid">
                    <div class="d-flex align-items-center ms-auto">
                        <div class="profile-container">
                            <!-- Dropdown untuk menu profil -->
                            <div class="profile-container dropdown">
                            <div class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="profile-avatar">
                                <?php 
                                // Ambil data profil sekali saja di awal file
                                if (!empty($profileData['foto_profil'])): ?>
                                    <img src="<?php echo displayProfilePicture($profileData); ?>" 
                                        alt="Profile Picture" 
                                        class="profile-image">
                                <?php else: ?>
                                    <div class="avatar-fallback">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?></div>
                                <div class="profile-role">Administrator</div>
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu">
                            <li>
                            <a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-user-edit me-2"></i> Edit Profil
                            </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

        <div class="page-wrapper">
            <div class="content">
                <!-- Notifikasi SweetAlert -->
                <?php if ($notification): ?>
                    <script>
                        Swal.fire({
                            icon: '<?= $notification['type'] ?>',
                            title: '<?= $notification['type'] === 'success' ? "Sukses" : "Error" ?>',
                            text: '<?= $notification['message'] ?>',
                            timer: 3000
                        });
                    </script>
                <?php endif; ?>

                <!-- Form Edit (Muncul saat mode edit) -->
                
<?php if ($editStudent): ?>
    <div class="edit-form-container animate__animated animate__fadeIn">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Data Mahasiswa</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="listMahasiswa.php">
                    <input type="hidden" name="id" value="<?= $editStudent['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">NPM</label>
                            <input type="text" class="form-control form-control-lg" name="npm" 
                                   value="<?= htmlspecialchars($editStudent['npm']) ?>" required>
                            <div class="form-text">Nomor Pokok Mahasiswa</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Lengkap</label>
                            <input type="text" class="form-control form-control-lg" name="nama" 
                                   value="<?= htmlspecialchars($editStudent['nama']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control form-control-lg" name="email" 
                                   value="<?= htmlspecialchars($editStudent['email']) ?>" required>
                            <div class="form-text">Email aktif mahasiswa</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Program Studi</label>
                            <select class="form-select form-select-lg" name="prodi" required>
                                <option value="Teknik Informatika" <?= $editStudent['prodi'] === 'Teknik Informatika' ? 'selected' : '' ?>>Teknik Informatika</option>
                                <option value="Sistem Informasi" <?= $editStudent['prodi'] === 'Sistem Informasi' ? 'selected' : '' ?>>Sistem Informasi</option>
                                <option value="Teknik Komputer" <?= $editStudent['prodi'] === 'Teknik Komputer' ? 'selected' : '' ?>>Teknik Komputer</option>
                            </select>
                        </div>
                        <div class="col-12 mt-4">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="listMahasiswa.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i> Batal
                                </a>
                                <button type="submit" name="edit_student" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

                <div class="page-header">
                    <h1 class="dashboard-title">List Mahasiswa</h1>
                    <p class="dashboard-subtitle">Manage students' data </p>
                </div>

                <div class="card">
                    <div class="card-body">
                        <!-- Search Form -->
                        <form method="get" class="mb-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Cari mahasiswa..." 
                                    value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="listMahasiswa.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Tabel Data -->
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>NPM</th>
                                                <th>Nama</th>
                                                <th>Email</th>
                                                <th>Prodi</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $index => $student): ?> 
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($student['npm']) ?></td>
                                                    <td><?= htmlspecialchars($student['nama']) ?></td>
                                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                                    <td><?= htmlspecialchars($student['prodi']) ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?edit=<?= $student['id'] ?>" class="btn btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button class="btn btn-info reset-btn" data-id="<?= $student['id'] ?>">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                            <button class="btn btn-danger delete-btn" data-id="<?= $student['id'] ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>


                        <!-- Pagination -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">
                                            &laquo; Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">
                                            Next &raquo;
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/script.js"></script>

    <?php if ($notification): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?= $notification['type'] === 'success' ? 'success' : 'error' ?>',
                title: '<?= $notification['type'] === 'success' ? "Sukses" : "Error" ?>',
                text: '<?= addslashes($notification['message']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
        });
    </script>
<?php endif; ?>
<script>
// Fungsi untuk mengecek pembaruan data
function checkUpdates() {
    fetch('?check_update=1')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.updated) {
                // Notifikasi dengan opsi manual refresh
                Swal.fire({
                    title: 'Data Terbaru Tersedia',
                    html: 'Versi lebih baru dari data tersedia.<br>Muat ulang sekarang?',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Muat Ulang',
                    cancelButtonText: 'Nanti',
                    timer: 10000, // Auto-close setelah 10 detik
                    timerProgressBar: true,
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        location.reload();
                    }
                });
            }
        })
        .catch(error => {
            console.error('Gagal memeriksa pembaruan:', error);
            setTimeout(checkUpdates, 60000); // Coba lagi dalam 60 detik jika error
        });
}

// Fungsi untuk menangani aksi berisiko (delete/reset)
function handleAction(action, id, message, defaultText) {
    Swal.fire({
        title: message.title,
        text: message.text,
        icon: message.icon,
        showCancelButton: true,
        confirmButtonText: message.confirmText,
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `listMahasiswa.php?action=${action}&id=${id}`;
        }
    });
}

// Inisialisasi setelah DOM siap
document.addEventListener('DOMContentLoaded', function() {
    // Mulai pengecekan pembaruan
    checkUpdates();
    setInterval(checkUpdates, 30000); // Cek setiap 30 detik

    // Handler tombol reset password
    document.querySelectorAll('.reset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            handleAction('reset_password', id, {
                title: 'Reset Password?',
                text: "Password akan direset ke default: " + defaultPassword,
                icon: 'question',
                confirmText: 'Ya, Reset'
            });
        });
    });

    // Handler tombol delete
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            handleAction('delete', id, {
                title: 'Hapus Data?',
                text: "Data mahasiswa akan dihapus permanen!",
                icon: 'warning',
                confirmText: 'Ya, Hapus'
            });
        });
    });
});

// Variabel global jika diperlukan
const defaultPassword = 'Un1v3rs1t4X!2024';
</script>
</body>
</html>