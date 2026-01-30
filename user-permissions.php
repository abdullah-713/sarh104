<?php
/**
 * صفحة إدارة صلاحيات المستخدمين
 * User Permissions Management Page
 * 
 * يتحكم بها السوبر أدمن فقط
 */

require_once __DIR__ . '/config/app.php';
require_login();
require_permission('manage_permissions');

// التحقق من صلاحية السوبر أدمن
if (!is_super_admin()) {
    redirect('dashboard.php', 'ليس لديك صلاحية الوصول لهذه الصفحة', 'danger');
    exit;
}

// جلب قائمة المستخدمين
$users = Database::fetchAll("
    SELECT u.id, u.emp_code, u.full_name, u.username, u.email, u.avatar,
           u.is_super_admin, u.permissions, u.visible_modules,
           r.name as role_name, r.permissions as role_permissions
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.is_active = 1
    ORDER BY u.full_name
");

// جلب الصلاحيات المتاحة
$availablePermissions = Database::fetchAll("
    SELECT * FROM available_permissions ORDER BY category, display_order
") ?: [];

// جلب الوحدات المتاحة
$availableModules = Database::fetchAll("
    SELECT * FROM available_modules WHERE is_active = 1 ORDER BY display_order
") ?: [];

// تجميع الصلاحيات حسب الفئة
$permissionsByCategory = [];
foreach ($availablePermissions as $perm) {
    $cat = $perm['category'] ?? 'عام';
    $permissionsByCategory[$cat][] = $perm;
}

// إذا لم توجد جداول الصلاحيات، نستخدم قائمة افتراضية
if (empty($availablePermissions)) {
    $permissionsByCategory = [
        'الموظفين' => [
            ['permission_key' => 'employees.view', 'name_ar' => 'عرض الموظفين'],
            ['permission_key' => 'employees.add', 'name_ar' => 'إضافة موظف'],
            ['permission_key' => 'employees.edit', 'name_ar' => 'تعديل موظف'],
            ['permission_key' => 'employees.delete', 'name_ar' => 'حذف موظف'],
        ],
        'الفروع' => [
            ['permission_key' => 'branches.view', 'name_ar' => 'عرض الفروع'],
            ['permission_key' => 'branches.add', 'name_ar' => 'إضافة فرع'],
            ['permission_key' => 'branches.edit', 'name_ar' => 'تعديل فرع'],
            ['permission_key' => 'branches.delete', 'name_ar' => 'حذف فرع'],
        ],
        'الحضور' => [
            ['permission_key' => 'attendance.view', 'name_ar' => 'عرض الحضور'],
            ['permission_key' => 'attendance.edit', 'name_ar' => 'تعديل سجلات الحضور'],
            ['permission_key' => 'attendance.manual_checkin', 'name_ar' => 'تسجيل حضور يدوي'],
        ],
        'التقارير' => [
            ['permission_key' => 'reports.view', 'name_ar' => 'عرض التقارير'],
            ['permission_key' => 'reports.export', 'name_ar' => 'تصدير التقارير'],
        ],
        'الإعدادات' => [
            ['permission_key' => 'settings.view', 'name_ar' => 'عرض الإعدادات'],
            ['permission_key' => 'settings.edit', 'name_ar' => 'تعديل الإعدادات'],
            ['permission_key' => 'manage_permissions', 'name_ar' => 'إدارة الصلاحيات'],
        ],
    ];
}

if (empty($availableModules)) {
    $availableModules = [
        ['module_key' => 'dashboard', 'name_ar' => 'لوحة التحكم', 'icon' => 'bi-speedometer2'],
        ['module_key' => 'employees', 'name_ar' => 'الموظفين', 'icon' => 'bi-people'],
        ['module_key' => 'branches', 'name_ar' => 'الفروع', 'icon' => 'bi-building'],
        ['module_key' => 'attendance', 'name_ar' => 'الحضور والانصراف', 'icon' => 'bi-clock'],
        ['module_key' => 'leaves', 'name_ar' => 'الإجازات', 'icon' => 'bi-calendar-check'],
        ['module_key' => 'reports', 'name_ar' => 'التقارير', 'icon' => 'bi-file-earmark-bar-graph'],
        ['module_key' => 'settings', 'name_ar' => 'الإعدادات', 'icon' => 'bi-gear'],
        ['module_key' => 'notifications', 'name_ar' => 'الإشعارات', 'icon' => 'bi-bell'],
    ];
}

$page_title = 'إدارة صلاحيات المستخدمين';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- العنوان -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>إدارة صلاحيات المستخدمين</h4>
                            <p class="text-muted mb-0">تحكم بصلاحيات كل مستخدم والوحدات المرئية له</p>
                        </div>
                        <span class="badge bg-danger fs-6">
                            <i class="bi bi-crown me-1"></i>
                            صلاحيات السوبر أدمن
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- قائمة المستخدمين -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>المستخدمين</h5>
                        <input type="text" class="form-control form-control-sm" id="userSearch" 
                               placeholder="بحث عن مستخدم..." style="max-width: 250px;">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th>المستخدم</th>
                                    <th>الدور</th>
                                    <th class="text-center">سوبر أدمن</th>
                                    <th class="text-center">الصلاحيات الفردية</th>
                                    <th class="text-center">الوحدات المرئية</th>
                                    <th width="120" class="text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): 
                                    $userPerms = json_decode($user['permissions'] ?? '[]', true) ?: [];
                                    $userModules = json_decode($user['visible_modules'] ?? '[]', true) ?: [];
                                    $isSuperAdmin = (bool) $user['is_super_admin'];
                                ?>
                                <tr data-user-id="<?= $user['id'] ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($user['avatar']): ?>
                                                <img src="<?= e($user['avatar']) ?>" class="rounded-circle me-2" 
                                                     width="40" height="40" alt="">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center 
                                                            justify-content-center me-2" style="width: 40px; height: 40px;">
                                                    <?= mb_substr($user['full_name'], 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?= e($user['full_name']) ?></div>
                                                <small class="text-muted"><?= e($user['emp_code']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= e($user['role_name'] ?? '-') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isSuperAdmin): ?>
                                            <span class="badge bg-danger"><i class="bi bi-crown"></i> نعم</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">لا</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= count($userPerms) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (empty($userModules)): ?>
                                            <span class="badge bg-success">الكل</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= count($userModules) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-permissions" 
                                                data-user-id="<?= $user['id'] ?>"
                                                data-user-name="<?= e($user['full_name']) ?>"
                                                data-is-super="<?= $isSuperAdmin ? '1' : '0' ?>"
                                                data-permissions='<?= e(json_encode($userPerms)) ?>'
                                                data-modules='<?= e(json_encode($userModules)) ?>'>
                                            <i class="bi bi-shield-lock"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تعديل الصلاحيات -->
<div class="modal fade" id="permissionsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock me-2"></i>
                    تعديل صلاحيات: <span id="modalUserName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="permissionsForm">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <!-- خيار السوبر أدمن -->
                    <div class="card border-danger mb-4">
                        <div class="card-header bg-danger text-white">
                            <i class="bi bi-crown me-2"></i>صلاحيات السوبر أدمن
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isSuperAdmin" name="is_super_admin">
                                <label class="form-check-label" for="isSuperAdmin">
                                    <strong>تفعيل صلاحيات السوبر أدمن المطلقة</strong>
                                    <br>
                                    <small class="text-muted">السوبر أدمن يمتلك جميع الصلاحيات تلقائياً ويمكنه التحكم بصلاحيات الآخرين</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- الوحدات المرئية -->
                    <div class="card mb-4" id="modulesSection">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-grid me-2"></i>الوحدات المرئية في القائمة الجانبية
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-success" id="selectAllModules">
                                    <i class="bi bi-check-all"></i> تحديد الكل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllModules">
                                    <i class="bi bi-x"></i> إلغاء الكل
                                </button>
                                <small class="text-muted ms-2">(اتركها فارغة لإظهار جميع الوحدات)</small>
                            </div>
                            <div class="row g-3" id="modulesCheckboxes">
                                <?php foreach ($availableModules as $module): ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input module-checkbox" type="checkbox" 
                                               name="visible_modules[]" 
                                               value="<?= e($module['module_key']) ?>"
                                               id="module_<?= e($module['module_key']) ?>">
                                        <label class="form-check-label" for="module_<?= e($module['module_key']) ?>">
                                            <i class="<?= e($module['icon'] ?? 'bi-circle') ?> me-1"></i>
                                            <?= e($module['name_ar']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- الصلاحيات الفردية -->
                    <div class="card" id="permissionsSection">
                        <div class="card-header bg-warning text-dark">
                            <i class="bi bi-key me-2"></i>الصلاحيات الفردية الإضافية
                            <small class="text-muted">(تُضاف إلى صلاحيات الدور)</small>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-success" id="selectAllPerms">
                                    <i class="bi bi-check-all"></i> تحديد الكل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllPerms">
                                    <i class="bi bi-x"></i> إلغاء الكل
                                </button>
                            </div>
                            
                            <div class="accordion" id="permissionsAccordion">
                                <?php $catIndex = 0; foreach ($permissionsByCategory as $category => $perms): $catIndex++; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?= $catIndex > 1 ? 'collapsed' : '' ?>" 
                                                type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#cat<?= $catIndex ?>">
                                            <?= e($category) ?>
                                            <span class="badge bg-primary ms-2 cat-count" data-cat="<?= $catIndex ?>">0</span>
                                        </button>
                                    </h2>
                                    <div id="cat<?= $catIndex ?>" 
                                         class="accordion-collapse collapse <?= $catIndex === 1 ? 'show' : '' ?>">
                                        <div class="accordion-body">
                                            <div class="row g-2">
                                                <?php foreach ($perms as $perm): ?>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input perm-checkbox" type="checkbox" 
                                                               name="permissions[]" 
                                                               value="<?= e($perm['permission_key']) ?>"
                                                               data-cat="<?= $catIndex ?>"
                                                               id="perm_<?= e($perm['permission_key']) ?>">
                                                        <label class="form-check-label" 
                                                               for="perm_<?= e($perm['permission_key']) ?>">
                                                            <?= e($perm['name_ar']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="savePermissions">
                    <i class="bi bi-check-lg me-1"></i>حفظ التغييرات
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('permissionsModal'));
    
    // البحث في الجدول
    document.getElementById('userSearch').addEventListener('input', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('#usersTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    });
    
    // فتح المودال للتعديل
    document.querySelectorAll('.btn-edit-permissions').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const isSuperAdmin = this.dataset.isSuper === '1';
            let permissions = [];
            let modules = [];
            
            try {
                permissions = JSON.parse(this.dataset.permissions || '[]');
                modules = JSON.parse(this.dataset.modules || '[]');
            } catch (e) {}
            
            document.getElementById('editUserId').value = userId;
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('isSuperAdmin').checked = isSuperAdmin;
            
            // إعادة تعيين checkboxes
            document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = false);
            
            // تحديد الصلاحيات الموجودة
            permissions.forEach(perm => {
                const cb = document.getElementById('perm_' + perm);
                if (cb) cb.checked = true;
            });
            
            // تحديد الوحدات الموجودة
            modules.forEach(mod => {
                const cb = document.getElementById('module_' + mod);
                if (cb) cb.checked = true;
            });
            
            updateCategoryCounts();
            toggleSuperAdminSections();
            modal.show();
        });
    });
    
    // إخفاء/إظهار أقسام الصلاحيات عند تفعيل السوبر أدمن
    document.getElementById('isSuperAdmin').addEventListener('change', toggleSuperAdminSections);
    
    function toggleSuperAdminSections() {
        const isSuper = document.getElementById('isSuperAdmin').checked;
        const sections = document.querySelectorAll('#modulesSection, #permissionsSection');
        sections.forEach(section => {
            section.style.opacity = isSuper ? '0.5' : '1';
            section.querySelectorAll('input').forEach(input => {
                input.disabled = isSuper;
            });
        });
    }
    
    // تحديد الكل / إلغاء الكل للوحدات
    document.getElementById('selectAllModules').addEventListener('click', () => {
        document.querySelectorAll('.module-checkbox:not(:disabled)').forEach(cb => cb.checked = true);
    });
    document.getElementById('deselectAllModules').addEventListener('click', () => {
        document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = false);
    });
    
    // تحديد الكل / إلغاء الكل للصلاحيات
    document.getElementById('selectAllPerms').addEventListener('click', () => {
        document.querySelectorAll('.perm-checkbox:not(:disabled)').forEach(cb => cb.checked = true);
        updateCategoryCounts();
    });
    document.getElementById('deselectAllPerms').addEventListener('click', () => {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
        updateCategoryCounts();
    });
    
    // تحديث عداد كل فئة
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.addEventListener('change', updateCategoryCounts);
    });
    
    function updateCategoryCounts() {
        const counts = {};
        document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
            const cat = cb.dataset.cat;
            counts[cat] = (counts[cat] || 0) + 1;
        });
        document.querySelectorAll('.cat-count').forEach(badge => {
            badge.textContent = counts[badge.dataset.cat] || 0;
        });
    }
    
    // حفظ التغييرات
    document.getElementById('savePermissions').addEventListener('click', async function() {
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جاري الحفظ...';
        
        const formData = {
            user_id: document.getElementById('editUserId').value,
            is_super_admin: document.getElementById('isSuperAdmin').checked ? 1 : 0,
            permissions: [],
            visible_modules: []
        };
        
        document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
            formData.permissions.push(cb.value);
        });
        
        document.querySelectorAll('.module-checkbox:checked').forEach(cb => {
            formData.visible_modules.push(cb.value);
        });
        
        try {
            const response = await fetch('api/admin/update_user_permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'تم الحفظ',
                    text: 'تم تحديث صلاحيات المستخدم بنجاح',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(result.message || 'فشل في الحفظ');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: error.message
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
