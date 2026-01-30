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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>
                    إضافة موظف جديد
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addEmployeeForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_employee">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required placeholder="أدخل الاسم الكامل">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم الموظف <span class="text-danger">*</span></label>
                            <input type="text" name="emp_code" class="form-control" required placeholder="EMP001" style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required placeholder="اسم الدخول">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="example@domain.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="6 أحرف على الأقل">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الدور <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <option value="">اختر الدور...</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الفرع <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">اختر الفرع...</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= e($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- صورة الموظف (اختياري) -->
                        <div class="col-12">
                            <label class="form-label">صورة الموظف <small class="text-muted">(اختياري)</small></label>
                            <div class="d-flex flex-column align-items-center p-3 bg-light rounded">
                                <div id="photoPreview" class="mb-3" style="display: none;">
                                    <img id="previewImg" src="" alt="Preview" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary" id="capturePhotoBtn">
                                        <i class="bi bi-camera me-1"></i> التقاط صورة
                                    </button>
                                    <label class="btn btn-outline-success mb-0">
                                        <i class="bi bi-upload me-1"></i> رفع صورة
                                        <input type="file" id="uploadPhotoInput" accept="image/*" style="display: none;">
                                    </label>
                                    <button type="button" class="btn btn-outline-danger" id="clearPhotoBtn" style="display: none;">
                                        <i class="bi bi-x-circle me-1"></i> إزالة
                                    </button>
                                </div>
                                <input type="hidden" name="photo_data" id="photoData">
                                <small class="text-muted mt-2">يمكنك التقاط صورة أو رفع صورة من جهازك</small>
                            </div>
                            <video id="videoElement" autoplay playsinline style="display: none; max-width: 100%; border-radius: 8px; margin-top: 10px;"></video>
                            <canvas id="canvasElement" style="display: none;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="bi bi-plus-lg me-1"></i> إضافة الموظف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const API_URL = '<?= url("api/admin/command_action.php") ?>';
const CSRF_TOKEN = '<?= csrf_token() ?>';

let stream = null;
let video = document.getElementById('videoElement');
let canvas = document.getElementById('canvasElement');
let photoDataInput = document.getElementById('photoData');
let previewImg = document.getElementById('previewImg');
let photoPreview = document.getElementById('photoPreview');
let captureBtn = document.getElementById('capturePhotoBtn');
let clearBtn = document.getElementById('clearPhotoBtn');
let uploadInput = document.getElementById('uploadPhotoInput');

// Start camera
async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } 
        });
        video.srcObject = stream;
        video.style.display = 'block';
        captureBtn.innerHTML = '<i class="bi bi-camera-fill me-1"></i> التقاط الآن';
        captureBtn.onclick = capturePhoto;
    } catch (err) {
        console.error('Camera error:', err);
        Swal.fire('خطأ', 'لا يمكن الوصول إلى الكاميرا. تأكد من إعطاء الإذن.', 'error');
    }
}

captureBtn.onclick = startCamera;

// Capture photo
function capturePhoto() {
    if (!video || !stream) return;
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    const photoData = canvas.toDataURL('image/jpeg', 0.8);
    setPhoto(photoData);
    stopCamera();
}

// Set photo from any source
function setPhoto(dataUrl) {
    photoDataInput.value = dataUrl;
    previewImg.src = dataUrl;
    photoPreview.style.display = 'block';
    clearBtn.style.display = 'inline-block';
}

// Stop camera
function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    video.style.display = 'none';
    captureBtn.innerHTML = '<i class="bi bi-camera me-1"></i> التقاط صورة';
    captureBtn.onclick = startCamera;
}

// Upload photo
uploadInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        Swal.fire('خطأ', 'يجب اختيار ملف صورة', 'error');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire('خطأ', 'حجم الصورة يجب أن يكون أقل من 5 ميجابايت', 'error');
        return;
    }
    const reader = new FileReader();
    reader.onload = (e) => setPhoto(e.target.result);
    reader.readAsDataURL(file);
});

// Clear photo
clearBtn.onclick = function() {
    photoDataInput.value = '';
    previewImg.src = '';
    photoPreview.style.display = 'none';
    clearBtn.style.display = 'none';
    uploadInput.value = '';
    stopCamera();
};

// Form submission with JSON API
document.getElementById('addEmployeeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('submitBtn');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الإضافة...';
    
    try {
        const formData = new FormData(form);
        const data = {
            action: 'create_employee',
            full_name: formData.get('full_name'),
            emp_code: formData.get('emp_code'),
            username: formData.get('username'),
            email: formData.get('email'),
            password: formData.get('password'),
            role_id: parseInt(formData.get('role_id')),
            branch_id: parseInt(formData.get('branch_id')),
            photo_data: formData.get('photo_data') || '',
            is_active: 1
        };
        
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: 'تمت إضافة الموظف بنجاح',
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('خطأ', result.message || 'حدث خطأ غير متوقع', 'error');
        }
    } catch (err) {
        console.error('Error:', err);
        Swal.fire('خطأ', 'حدث خطأ في الاتصال بالخادم', 'error');
    }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> إضافة الموظف';
});

// Cleanup on modal close
document.getElementById('addModal').addEventListener('hidden.bs.modal', function() {
    stopCamera();
    document.getElementById('addEmployeeForm').reset();
    photoPreview.style.display = 'none';
    clearBtn.style.display = 'none';
    photoDataInput.value = '';
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
