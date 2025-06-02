<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('user_session'); // HARUS sebelum session_start()
    session_start();
}
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'user'){
    header("Location: index.php?x=login");
    exit();
}

function isActivePage($pageName) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage == $pageName) ? true : false;
}

require_once __DIR__ . '/../config/db.php';  

// Ambil data user
$user = [];
$stmt = $con->prepare("SELECT id, nama, npm, prodi FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

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

// Ambil kategori dan subkategori aktivitas
$categories = $con->query("
    SELECT DISTINCT c.category_id, c.category_name 
    FROM activity_categories c
    JOIN activity_subcategories sc ON c.category_id = sc.category_id
    ORDER BY c.category_name
")->fetch_all(MYSQLI_ASSOC);

// Untuk subkategori
$subcategories = $con->query("
    SELECT DISTINCT sc.subcategory_id, sc.subcategory_name, sc.category_id, sc.points
    FROM activity_subcategories sc
    GROUP BY sc.subcategory_name
    ORDER BY sc.subcategory_name
")->fetch_all(MYSQLI_ASSOC);

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subcategory_id = $_POST['subcategory_id'] ?? '';
    $description = $_POST['description'] ?? '';
    $proof_file = $_FILES['proof_file'] ?? null;

    // Validasi
    $errors = [];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (empty($subcategory_id)) {
        $errors['subcategory_id'] = "Pilih aktivitas wajib diisi";
    }
    
    if (empty($description)) {
        $errors['description'] = "Deskripsi wajib diisi";
    } elseif (strlen($description) > 500) {
        $errors['description'] = "Deskripsi maksimal 500 karakter";
    }
    
    if (!$proof_file || $proof_file['error'] !== UPLOAD_ERR_OK) {
        $errors['proof_file'] = "Bukti dokumen wajib diupload";
    } elseif ($proof_file['size'] > $max_size) {
        $errors['proof_file'] = "Ukuran file maksimal 2MB";
    } else {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($proof_file['type'], $allowed_types)) {
            $errors['proof_file'] = "Hanya file PDF, JPG, dan PNG yang diperbolehkan";
        }
    }
    
    // Jika tidak ada error, proses data
    if (empty($errors)) {
        try {
            // Baca file sebagai binary data
            $proof_content = file_get_contents($proof_file['tmp_name']);
            $proof_type = $proof_file['type'];
            $original_filename = $proof_file['name'];
            
            // Insert ke database
            $stmt = $con->prepare("
                INSERT INTO student_activities 
                (user_id, subcategory_id, description, proof_file_path, file_type, original_filename, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param(
                "isssss", 
                $_SESSION['id'], 
                $subcategory_id, 
                $description, 
                $proof_content, 
                $proof_type,
                $original_filename
            );
            
            if ($stmt->execute()) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Aktivitas SKP berhasil ditambahkan dan menunggu persetujuan'
                ];
                header("Location: index.php?x=dashboard");
                exit();
            } else {
                throw new Exception("Gagal menyimpan data ke database: " . $con->error);
            }
        } catch (Exception $e) {
            $errors['general'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Aktivitas SKP - Sistem SKP</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-dashboard.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    
    
    <!-- Custom CSS -->
    <style>
        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        .file-upload:hover {
            border-color: #0d6efd;
            background-color: #e9ecef;
        }
        .file-upload-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 15px;
            display: none;
        }
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875em;
        }
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
<div id="app">
    <!-- Sidebar -->
    <div class="sidebar" :class="{ 'collapsed': sidebarCollapsed, 'mobile-view': isMobile }">
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
                <a href="index.php?x=dashboard" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php?x=daftar_skp" class="nav-link">
                    <i class="fas fa-list"></i>
                    <span>Daftar SKP</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php?x=addSkp" class="nav-link active">
                    <i class="fas fa-plus-circle"></i>
                    <span>Tambah SKP</span>
                </a>
            </li>
        </ul>
        
        
        <div class="sidebar-footer p-3">
            <a href="index.php?x=logout" class="nav-link logout-btn">
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
                    <div class="profile-container dropdown">
                        <div class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="profile-avatar">
                                <?php if (!empty($profileData['foto_profil'])): ?>
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
                                <div class="profile-name"><?php echo htmlspecialchars($user['nama'] ?? 'user'); ?></div>
                                <div class="profile-role"><?php echo htmlspecialchars($user['prodi'] ?? 'user'); ?></div> <!-- Changed to display prodi -->
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="index.php?x=profile">
                                    <i class="fas fa-user-edit me-2"></i> Edit Profil
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3></i>Tambah Aktivitas SKP</h3>

                </a>
            </div>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    // Custom message untuk ukuran file
                    if (strpos($errors['general'], 'max_allowed_packet') !== false) {
                        echo "Ukuran file maksimal 2MB";
                    } else {
                        echo $errors['general']; 
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Form Tambah Aktivitas</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="skpForm" novalidate>
                        <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="categorySelect" class="form-label">Kategori</label>
                            <select class="form-select" id="categorySelect" required>
                                <option value="">Pilih Kategori</option>
                                <?php 
                                $uniqueCategories = [];
                                foreach ($categories as $category): 
                                    if (!in_array($category['category_name'], $uniqueCategories)):
                                        $uniqueCategories[] = $category['category_name'];
                                ?>
                                    <option value="<?= $category['category_id'] ?>">
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                        </div>
                        
                            <div class="col-md-6 mb-3">
                                <label for="subcategorySelect" class="form-label">Aktivitas</label>
                                <select class="form-select " name="subcategory_id" id="subcategorySelect" required disabled>
                                    <option value="">Pilih Aktivitas</option>
                                    <?php foreach ($subcategories as $subcat): ?>
                                        <option value="<?= $subcat['subcategory_id'] ?>">
                                            <?= htmlspecialchars($subcat['subcategory_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php echo $errors['subcategory_id'] ?? ''; ?></div>
                                <small class="text-muted">Poin akan otomatis terisi sesuai pilihan aktivitas</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                            <label for="pointsDisplay" class="form-label">Poin</label>
                            <input type="text" class="form-control" id="pointsDisplay" readonly>
                        </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="Menunggu Persetujuan" readonly>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="description" class="form-label">Deskripsi Kegiatan</label>
                                <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                    id="description" name="description" rows="3" required
                                    placeholder="Jelaskan secara singkat kegiatan yang dilakukan"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="invalid-feedback"><?php echo $errors['description'] ?? ''; ?></div>
                                <small class="text-muted">Maksimal 500 karakter</small>
                            </div>
                            
                            <div class="col-12 mb-4">
                                <label class="form-label">Bukti Dokumen</label>
                                <div class="file-upload <?php echo isset($errors['proof_file']) ? 'is-invalid' : ''; ?>" 
                                    id="fileUploadArea">
                                    <input type="file" id="proof_file" name="proof_file" 
                                        accept=".pdf,.jpg,.jpeg,.png" required style="display: none;">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted"></i>
                                    <h5 class="my-3">Seret file ke sini atau klik untuk memilih</h5>
                                    <p class="text-muted">Format: PDF, JPG, atau PNG (Maks. 2MB)</p>
                                    <img id="filePreview" class="file-upload-preview" alt="Preview dokumen">
                                    <div id="fileProgress" class="progress mt-3" style="display:none; height: 5px;">
                                        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="invalid-feedback"><?php echo $errors['proof_file'] ?? ''; ?></div>
                                <div id="fileName" class="mt-2 fw-bold"></div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100 py-3">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Aktivitas
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Panduan Pengisian</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Pilih kategori dan aktivitas yang sesuai dengan kegiatan yang telah dilakukan</li>
                        <li>Isi deskripsi kegiatan dengan jelas dan lengkap</li>
                        <li>Unggah bukti dokumen yang valid (sertifikat, foto, atau dokumen pendukung)</li>
                        <li>Pastikan file yang diupload jelas terbaca dan tidak melebihi 2MB</li>
                        <li>Aktivitas akan diverifikasi oleh admin sebelum poin ditambahkan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/utils.js"></script>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>

<script>
$(document).ready(function() {
    // Data initialization
    const subcategories = <?php echo json_encode($subcategories); ?>;
    const $categorySelect = $('#categorySelect');
    const $subcategorySelect = $('#subcategorySelect');
    const $pointsDisplay = $('#pointsDisplay');
    const $fileUploadArea = $('#fileUploadArea');
    const $proofFile = $('#proof_file');
    const $fileName = $('#fileName');
    const $filePreview = $('#filePreview');
    const $uploadInstructions = $('.upload-instructions');
    const $skpForm = $('#skpForm');

    // 1. PERBAIKAN UNTUK DUPLIKAT SUBKATEGORI
    // Organize subcategories by category_id dengan menghilangkan duplikat
    const categoryToSubcategories = {};
    const usedSubcategoryIds = new Set(); // Untuk melacak ID yang sudah diproses
    
    subcategories.forEach(sub => {
        if (!usedSubcategoryIds.has(sub.subcategory_id)) {
            if (!categoryToSubcategories[sub.category_id]) {
                categoryToSubcategories[sub.category_id] = [];
            }
            categoryToSubcategories[sub.category_id].push(sub);
            usedSubcategoryIds.add(sub.subcategory_id);
        }
    });

    $fileUploadArea.css({
        'border': '2px dashed #dee2e6',
        'border-radius': '5px',
        'padding': '20px',
        'text-align': 'center',
        'cursor': 'pointer',
        'transition': 'all 0.3s',
        'background-color': '#f8f9fa',
        'position': 'relative',
        'overflow': 'hidden'
    });

    // 2. PERBAIKAN FILE UPLOAD HANDLER
    // Trigger file input ketika area upload diklik
    $fileUploadArea.on('click', function(e) {
        if (e.target === this || $(e.target).hasClass('upload-instructions')) {
            $proofFile.trigger('click');
        }
    });

    // Category change handler
    $categorySelect.on('change', function() {
        const categoryId = $(this).val();
        $subcategorySelect.empty().append('<option value="">Pilih Aktivitas</option>');
        
        if (categoryId && categoryToSubcategories[categoryId]) {
            $subcategorySelect.prop('disabled', false);
            
            // Menambahkan opsi subkategori tanpa duplikat
            categoryToSubcategories[categoryId].forEach(sub => {
                $subcategorySelect.append(
                    `<option value="${sub.subcategory_id}" data-points="${sub.points || 0}">
                        ${sub.subcategory_name}
                    </option>`
                );
            });
        } else {
            $subcategorySelect.prop('disabled', true);
        }
        
        // Reset points display
        $pointsDisplay.val('');
    });

    // Subcategory change handler
    $subcategorySelect.on('change', function() {
        const points = $(this).find('option:selected').data('points') || 0;
        $pointsDisplay.val(points);
    });

    // File input change handler
    $proofFile.on('change', function(e) {
       if (this.files && this.files[0]) {
            handleFileSelection(this.files[0]);
        }
    });

    // 3. DRAG AND DROP IMPROVEMENT
    $fileUploadArea
        .on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('highlight');
        })
        .on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('highlight');
        })
        .on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('highlight');
            
            if (e.originalEvent.dataTransfer.files.length) {
                const file = e.originalEvent.dataTransfer.files[0];
                $proofFile[0].files = e.originalEvent.dataTransfer.files;
                handleFileSelection(file);
            }
        });

    // 4. ENHANCED FILE SELECTION HANDLER
    function handleFileSelection(file) {
        if (!file) return;

        // Validasi file
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!allowedTypes.includes(file.type)) {
            showFileError('Hanya file PDF, JPG, dan PNG yang diperbolehkan');
            return;
        }
        
        if (file.size > maxSize) {
            showFileError('Ukuran file maksimal 2MB');
            return;
        }

        // Tampilkan nama file
        $fileName.text(file.name);
        
        // Tampilkan preview untuk gambar
        if (file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $filePreview.attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        } else {
            $filePreview.hide();
            // Tambahkan icon PDF jika file PDF
            $fileName.prepend('<i class="fas fa-file-pdf me-2"></i>');
        }
        
        // Clear validation
        $fileUploadArea.removeClass('is-invalid');
        $fileUploadArea.next('.invalid-feedback').text('');
    }

    function showFileError(message) {
        $fileUploadArea.addClass('is-invalid');
        $fileUploadArea.next('.invalid-feedback').text(message);
        $proofFile.val(''); // Reset input file
        $fileName.text('');
        $filePreview.hide();
    }

    // 5. FORM VALIDATION IMPROVEMENT
    $skpForm.on('submit', function(e) {
        let isValid = true;
        
        // Reset validation
        $(this).removeClass('was-validated');
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        // Validate category
        if (!$categorySelect.val()) {
            isValid = false;
            $categorySelect.addClass('is-invalid')
                .next('.invalid-feedback').text('Pilih kategori wajib diisi');
        }

        // Validate subcategory
        if (!$subcategorySelect.val()) {
            isValid = false;
            $subcategorySelect.addClass('is-invalid')
                .next('.invalid-feedback').text('Pilih aktivitas wajib diisi');
        }

        // Validate file
        if (!$proofFile.val()) {
            isValid = false;
            $fileUploadArea.addClass('is-invalid')
                .next('.invalid-feedback').text('Bukti dokumen wajib diupload');
        }

        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('was-validated');
        }
    });

    // Initialize if category has value
    if ($categorySelect.val()) {
        $categorySelect.trigger('change');
    }
});
</script>
</body>
</html>