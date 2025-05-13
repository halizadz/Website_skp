<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION['role'])) {
    header("Location: pages/login.php");
    exit();
}

function isActivePage($pageName) {
    return basename($_SERVER['PHP_SELF']) === $pageName;
}

include(__DIR__ . "/config/db.php");
include(__DIR__ . "/assets/php/my_profile.php");

$profileData = [];
if (isset($_SESSION['id'])) {
    $stmt = $con->prepare("SELECT nama, npm, prodi, email, foto_profil, profile_picture_type FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileData = $result->fetch_assoc();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
    <body>
    <div id="app">
        <div class="sidebar" :class="{ 'collapsed': sidebarCollapsed, 'mobile-view': isMobile }">
            <?php if ($_SESSION['role'] === 'admin') { ?>
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
                            <a href="admin/dashboard.php" class="nav-link <?php echo isActivePage('admin/dashboard.php') ? 'active' : ''; ?>">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin/listMahasiswa.php" class="nav-link <?php echo isActivePage('admin/listMahasiswa.php') ? 'active' : ''; ?>">
                                <i class="fas fa-user-graduate"></i>
                                <span>List Mahasiswa</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin/Approve.php" class="nav-link <?php echo isActivePage('admin/Approve.php') ? 'active' : ''; ?>">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Approve</span>
                            </a>
                        </li>
                    </ul>
                    
                    <div class="sidebar-footer p-3">
                        <a href="pages/logout.php" class="nav-link logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
    <?php } elseif ($_SESSION['role'] === 'user') { ?>
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon animate__animated animate__bounceIn">
                        <i class="fas fa-user-graduate"></i> <!-- Changed from admin icon to student icon -->
                    </div>
                    <div class="logo-text animate__animated animate__fadeIn">Student Panel</div> <!-- Changed from Admin to Student -->
                </div>
                <button class="btn btn-link" @click="toggleSidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            
            <ul class="nav flex-column sidebar-menu">
                <li class="nav-item">
                    <a href="mahasiswa/dashboard.php" class="nav-link active"> <!-- Removed PHP check, just set active -->
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mahasiswa/daftar_skp.php" class="nav-link">
                        <i class="fas fa-list"></i> <!-- Changed icon -->
                        <span>Daftar SKP</span> <!-- Changed text -->
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mahasiswa/addSkp.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i> <!-- Changed icon -->
                        <span>Tambah SKP</span> <!-- Changed text -->
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer p-3">
                <a href="pages/logout.php" class="nav-link logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    <?php } else {
        header("Location: pages/login.php");
        exit();
    } ?>
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
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <!-- Admin View -->
                                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?></div>
                                <div class="profile-role">Administrator</div>
                            <?php else: ?>
                                <!-- Regular User View -->
                                <div class="profile-name"><?php echo htmlspecialchars($user['nama'] ?? 'User'); ?></div>
                                <div class="profile-role"><?php echo htmlspecialchars($user['prodi'] ?? 'Mahasiswa'); ?></div>
                            <?php endif; ?>
                        </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu">
                            <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i> Edit Profil
                            </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <div class="container-fluid">
                <div class="dashboard-header animate__animated animate__fadeIn">
                    <h1 class="dashboard-title">My Profile</h1>
                    <p class="dashboard-subtitle">Manage your personal information</p>
                </div>
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="profileForm" action="profile.php">
                            <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="profile-picture-container">
                                    <!-- Container untuk preview gambar -->
                                    <div id="profilePicturePreview" onclick="document.getElementById('foto_profil').click()">
                                        <?php if (!empty($profileData['foto_profil'])): ?>
                                            <img src="data:<?php echo htmlspecialchars($profileData['profile_picture_type']); ?>;base64,<?php echo base64_encode($profileData['foto_profil']); ?>" 
                                                alt="Profile Picture" 
                                                style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <i class="fas fa-user" style="font-size: 4rem; color: #6c757d;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Overlay untuk edit -->
                                    <div class="edit-overlay">
                                        <i class="fas fa-camera fa-2x"></i>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <!-- Tombol untuk upload foto -->
                                    <label for="foto_profil" class="btn btn-outline-primary">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </label>
                                    <input type="file" class="d-none" id="foto_profil" name="foto_profil" 
                                        accept="image/jpeg, image/png, image/gif">
                                </div>
                            </div>
                                <div class="col-md-8">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="nama" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="nama" name="nama" 
                                                   value="<?php echo htmlspecialchars($profileData['nama'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="npm" class="form-label">NPM</label>
                                            <input type="text" class="form-control" id="npm" name="npm" 
                                                   value="<?php echo htmlspecialchars($profileData['npm'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="prodi" class="form-label">Program Study</label>
                                            <input type="text" class="form-control" id="prodi" name="prodi" 
                                                   value="<?php echo htmlspecialchars($profileData['prodi'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($profileData['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <!-- Tambahkan di form profile.php -->
<div class="mb-3">
    <label for="old_password" class="form-label">Password Lama</label>
    <input type="password" class="form-control" id="old_password" name="old_password">
</div>

<div class="mb-3">
    <label for="password" class="form-label">Password Baru</label>
    <input type="password" class="form-control" id="password" name="password">
</div>

<div class="mb-3">
    <label for="password_confirm" class="form-label">Konfirmasi Password Baru</label>
    <input type="password" class="form-control" id="password_confirm" name="password_confirm">
</div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Vue.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
const { createApp, ref, onMounted } = Vue;

createApp({
    setup() {
        const sidebarCollapsed = ref(localStorage.getItem('sidebarCollapsed') === 'true' || false);
        const isMobile = ref(window.innerWidth < 992);

        // Fungsi untuk menampilkan preview gambar (tanpa validasi)
        const showImagePreview = (file) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('profilePicturePreview');
                if (preview) {
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    preview.appendChild(img);
                }
            };
            reader.readAsDataURL(file);
        };

        const handleFileChange = (e) => {
            if (e.target.files && e.target.files[0]) {
                showImagePreview(e.target.files[0]);
            }
        };

        const handleProfileFormSubmit = (e) => {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            return true; // Lanjutkan submit form
        };

        const toggleSidebar = () => {
            sidebarCollapsed.value = !sidebarCollapsed.value;
            localStorage.setItem('sidebarCollapsed', sidebarCollapsed.value);
        };

        onMounted(() => {
            const fotoProfileInput = document.getElementById('foto_profil');
            if (fotoProfileInput) {
                fotoProfileInput.addEventListener('change', handleFileChange);
            }
            
            const profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', handleProfileFormSubmit);
            }
        });

        return {
            sidebarCollapsed,
            isMobile,
            toggleSidebar
        };
    }
}).mount('#app');
</script>

<!-- SweetAlert Notifications from PHP Session -->
<?php if (isset($_SESSION['success_message'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: '<?= addslashes($_SESSION['success_message']) ?>',
        confirmButtonColor: '#6C5DD3',
        confirmButtonText: 'OKE'
    });
});
</script>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const errorMsg = '<?= addslashes($_SESSION['error_message']) ?>';
    const friendlyMsg = errorMsg.includes('max_allowed_packet') 
        ? 'Ukuran gambar terlalu besar. Maksimal 2MB.' 
        : errorMsg;
    
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: friendlyMsg,
        confirmButtonColor: '#6C5DD3',
        confirmButtonText: 'OKE'
    });
});
</script>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
</body>
</html>