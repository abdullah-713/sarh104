<?php
/**
 * إدارة الموظفين - Employees Management
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();
require_permission('manage_employees');

$pageTitle = 'إدارة الموظفين';
$currentPage = 'employees';

// جلب الموظفين
try {
    $employees = Database::fetchAll("
        SELECT u.*, r.name as role_name, r.color as role_color, b.name as branch_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN branches b ON u.branch_id = b.id
        ORDER BY u.full_name
    ");
    
    $roles = Database::fetchAll("SELECT * FROM roles WHERE is_active = 1 ORDER BY role_level");
    $branches = Database::fetchAll("SELECT * FROM branches WHERE is_active = 1 ORDER BY name");
} catch (Exception $e) {
    $employees = [];
    $roles = [];
    $branches = [];
}

include INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-people-fill text-primary me-2"></i>
            إدارة الموظفين
        </h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> إضافة موظف
        </button>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الموظف</th>
                            <th>رقم الموظف</th>
                            <th>الدور</th>
                            <th>الفرع</th>
                            <th>الحالة</th>
                            <th>النقاط</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                لا يوجد موظفين
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-2" style="width:40px;height:40px;background:<?= e($emp['role_color'] ?? '#6c757d') ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-person text-white"></i>
                                    </div>
                                    <div>
                                        <strong><?= e($emp['full_name']) ?></strong>
                                        <?php if ($emp['is_online']): ?>
                                        <span class="badge bg-success ms-1" style="font-size:0.6rem;">متصل</span>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= e($emp['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= e($emp['emp_code']) ?></code></td>
                            <td>
                                <span class="badge" style="background:<?= e($emp['role_color'] ?? '#6c757d') ?>">
                                    <?= e($emp['role_name'] ?? 'غير محدد') ?>
                                </span>
                            </td>
                            <td><?= e($emp['branch_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($emp['is_active']): ?>
                                <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                <span class="badge bg-danger">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-warning fw-bold">
                                    <i class="bi bi-star-fill me-1"></i>
                                    <?= number_format($emp['current_points']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('admin/universal_manager.php?table=users&search=' . $emp['id']) ?>" class="btn btn-outline-primary" title="تعديل">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة موظف -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>
                    إضافة موظف جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url('api/admin/command_action.php') ?>" method="POST" id="addForm">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_employee">
                    
                    <div class="mb-3">
                        <label class="form-label">الاسم الكامل *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">رقم الموظف *</label>
                            <input type="text" name="emp_code" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">اسم المستخدم *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">صورة الموظف *</label>
                        <div class="d-flex flex-column align-items-center">
                            <div id="photoPreview" class="mb-3" style="display: none;">
                                <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                            </div>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary" id="capturePhotoBtn">
                                    <i class="bi bi-camera me-1"></i> التقاط صورة
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearPhotoBtn" style="display: none;">
                                    <i class="bi bi-x-circle me-1"></i> إزالة
                                </button>
                            </div>
                            <input type="hidden" name="photo_data" id="photoData">
                            <small class="text-muted mt-2">يجب التقاط صورة للموظف أمام الكاميرا</small>
                        </div>
                        <video id="videoElement" autoplay style="display: none; max-width: 100%; border-radius: 8px;"></video>
                        <canvas id="canvasElement" style="display: none;"></canvas>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">الدور *</label>
                            <select name="role_id" class="form-select" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">الفرع *</label>
                            <select name="branch_id" class="form-select" required>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= e($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> إضافة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let stream = null;
let video = document.getElementById('videoElement');
let canvas = document.getElementById('canvasElement');
let photoDataInput = document.getElementById('photoData');
let previewImg = document.getElementById('previewImg');
let photoPreview = document.getElementById('photoPreview');
let captureBtn = document.getElementById('capturePhotoBtn');
let clearBtn = document.getElementById('clearPhotoBtn');

// Capture photo button - initial handler
let startCamera = async function() {
    try {
        // Request camera access
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 }
            } 
        });
        
        video.srcObject = stream;
        video.style.display = 'block';
        
        // Change button to capture mode
        captureBtn.innerHTML = '<i class="bi bi-camera-fill me-1"></i> التقاط';
        captureBtn.removeEventListener('click', startCamera);
        captureBtn.addEventListener('click', capturePhoto);
        
    } catch (err) {
        console.error('Error accessing camera:', err);
        Swal.fire('خطأ', 'لا يمكن الوصول إلى الكاميرا. يرجى التحقق من الصلاحيات.', 'error');
    }
};

captureBtn.addEventListener('click', startCamera);

// Capture photo from video
function capturePhoto() {
    if (!video || !stream) return;
    
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    
    // Convert to base64
    const photoData = canvas.toDataURL('image/jpeg', 0.8);
    photoDataInput.value = photoData;
    
    // Show preview
    previewImg.src = photoData;
    photoPreview.style.display = 'block';
    
    // Stop video stream
    stream.getTracks().forEach(track => track.stop());
    stream = null;
    video.style.display = 'none';
    
    // Reset button to start camera again
    captureBtn.innerHTML = '<i class="bi bi-camera me-1"></i> التقاط صورة';
    captureBtn.removeEventListener('click', capturePhoto);
    captureBtn.addEventListener('click', startCamera);
    
    // Show clear button
    clearBtn.style.display = 'inline-block';
}

// Clear photo
clearBtn.addEventListener('click', function() {
    photoDataInput.value = '';
    previewImg.src = '';
    photoPreview.style.display = 'none';
    clearBtn.style.display = 'none';
    
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.style.display = 'none';
    }
});

// Form submission
document.getElementById('addForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('[type="submit"]');
    
    // Validate photo
    if (!photoDataInput.value) {
        Swal.fire('تنبيه', 'يجب التقاط صورة للموظف', 'warning');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الإضافة...';
    
    try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            Swal.fire('تم!', 'تمت إضافة الموظف بنجاح', 'success').then(() => location.reload());
        } else {
            Swal.fire('خطأ', result.message || 'حدث خطأ', 'error');
        }
    } catch (err) {
        Swal.fire('خطأ', 'حدث خطأ في الاتصال', 'error');
    }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> إضافة';
});

// Cleanup on modal close
document.getElementById('addModal').addEventListener('hidden.bs.modal', function() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    video.style.display = 'none';
    photoPreview.style.display = 'none';
    photoDataInput.value = '';
    clearBtn.style.display = 'none';
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
