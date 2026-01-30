<?php
/**
 * صفحة تغيير كلمة المرور - Security
 * تحديث كلمة المرور للمستخدم
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'تغيير كلمة المرور';
$currentPage = 'change-password';

$userId = current_user_id();
$error = '';
$success = '';
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'خطأ في التحقق الأمني. يرجى إعادة المحاولة.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword)) {
            $errors['current_password'] = 'كلمة المرور الحالية مطلوبة';
        }
        
        if (empty($newPassword)) {
            $errors['new_password'] = 'كلمة المرور الجديدة مطلوبة';
        } elseif (mb_strlen($newPassword) < 6) {
            $errors['new_password'] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        }
        
        if (empty($confirmPassword)) {
            $errors['confirm_password'] = 'تأكيد كلمة المرور مطلوب';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'كلمة المرور غير متطابقة';
        }
        
        if ($currentPassword === $newPassword) {
            $errors['new_password'] = 'كلمة المرور الجديدة يجب أن تختلف عن الحالية';
        }
        
        // If no validation errors, proceed
        if (empty($errors)) {
            try {
                // Verify user ID is valid
                if (!$userId || $userId <= 0) {
                    $error = 'خطأ: معرف المستخدم غير صالح. يرجى تسجيل الدخول مرة أخرى.';
                    error_log("Password change error: Invalid user ID: {$userId}");
                } else {
                    // Get current password hash from DB
                    $user = Database::fetchOne("SELECT password_hash FROM users WHERE id = :id", ['id' => $userId]);
                    
                    if (!$user) {
                        $error = 'حدث خطأ. يرجى تسجيل الدخول مرة أخرى.';
                        error_log("Password change error: User not found for ID: {$userId}");
                    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                        $errors['current_password'] = 'كلمة المرور الحالية غير صحيحة';
                    } else {
                        // Hash new password
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        if (!$hashedPassword) {
                            $error = 'حدث خطأ أثناء تشفير كلمة المرور. حاول مرة أخرى.';
                            error_log("Password change error: Failed to hash password for user ID: {$userId}");
                        } else {
                            // Update in database using direct SQL to ensure it works
                            $sql = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
                            $stmt = Database::query($sql, [
                                'password_hash' => $hashedPassword,
                                'id' => $userId
                            ]);
                            $affectedRows = $stmt->rowCount();
                            
                            // Verify update was successful
                            if ($affectedRows > 0) {
                                // Log activity
                                log_activity('password_changed', 'user', 'تم تغيير كلمة المرور بنجاح', $userId);
                                
                                // Success
                                $success = 'تم تغيير كلمة المرور بنجاح!';
                                
                                // Optional: Invalidate other sessions
                                // Database::query("DELETE FROM user_sessions WHERE user_id = :id AND session_token != :token", ['id' => $userId, 'token' => session_id()]);
                            } else {
                                $error = 'لم يتم تحديث كلمة المرور. يرجى المحاولة مرة أخرى.';
                                error_log("Password update failed: No rows affected for user ID {$userId}. SQL: {$sql}");
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ أثناء تحديث كلمة المرور. حاول مرة أخرى.';
                error_log("Password change error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            } catch (PDOException $e) {
                $error = 'حدث خطأ في قاعدة البيانات. حاول مرة أخرى.';
                error_log("Password change PDO error: " . $e->getMessage() . " | Code: " . $e->getCode());
            }
        }
    }
}

include INCLUDES_PATH . '/header.php';
?>

<style>
.password-page {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    padding: 2rem 0;
}
.password-card {
    max-width: 480px;
    margin: 0 auto;
    width: 100%;
}
.password-card .card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
}
.password-header {
    background: linear-gradient(135deg, var(--sarh-primary) 0%, var(--sarh-primary-light) 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}
.password-header .icon-circle {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 2rem;
}
.password-body {
    padding: 2rem;
}
.form-floating {
    margin-bottom: 1rem;
}
.form-floating > .form-control {
    height: 58px;
    padding: 1rem 1rem 0.5rem 2.5rem;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}
.form-floating > .form-control:focus {
    border-color: var(--sarh-primary);
    box-shadow: 0 0 0 4px rgba(26,35,126,0.1);
}
.form-floating > label {
    padding: 1rem;
}
.form-floating .input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #adb5bd;
    font-size: 1.1rem;
    z-index: 5;
}
.form-floating > .form-control.is-invalid {
    border-color: #dc3545;
}
.password-toggle {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #adb5bd;
    cursor: pointer;
    z-index: 5;
    padding: 0.5rem;
}
.password-toggle:hover {
    color: var(--sarh-primary);
}
.strength-meter {
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
}
.strength-meter .bar {
    height: 100%;
    width: 0;
    transition: all 0.3s;
    border-radius: 2px;
}
.strength-meter .bar.weak { width: 25%; background: #dc3545; }
.strength-meter .bar.fair { width: 50%; background: #ffc107; }
.strength-meter .bar.good { width: 75%; background: #17a2b8; }
.strength-meter .bar.strong { width: 100%; background: #28a745; }
.strength-text {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}
.btn-submit {
    height: 54px;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
}
.security-tips {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1.5rem;
}
.security-tips h6 {
    font-size: 0.85rem;
    color: var(--sarh-gray);
    margin-bottom: 0.75rem;
}
.security-tips ul {
    margin: 0;
    padding-right: 1.25rem;
    font-size: 0.8rem;
    color: var(--sarh-gray);
}
.security-tips li {
    margin-bottom: 0.25rem;
}
</style>

<div class="password-page">
    <div class="container">
        <div class="password-card">
            <div class="card">
                <div class="password-header">
                    <div class="icon-circle">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h4 class="fw-bold mb-1">تغيير كلمة المرور</h4>
                    <p class="mb-0 opacity-75">حافظ على أمان حسابك</p>
                </div>
                
                <div class="password-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= e($error) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div>
                            <?= e($success) ?>
                            <a href="<?= url('settings.php') ?>" class="alert-link d-block mt-1">
                                <i class="bi bi-arrow-right me-1"></i>
                                العودة للإعدادات
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    
                    <form method="POST" action="" id="passwordForm" novalidate>
                        <?= csrf_field() ?>
                        
                        <!-- Current Password -->
                        <div class="form-floating position-relative">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" 
                                   id="current_password" 
                                   name="current_password" 
                                   placeholder="كلمة المرور الحالية"
                                   required>
                            <label for="current_password">كلمة المرور الحالية</label>
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if (isset($errors['current_password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['current_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- New Password -->
                        <div class="form-floating position-relative">
                            <i class="bi bi-key input-icon"></i>
                            <input type="password" 
                                   class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" 
                                   id="new_password" 
                                   name="new_password" 
                                   placeholder="كلمة المرور الجديدة"
                                   minlength="6"
                                   required
                                   oninput="checkStrength(this.value)">
                            <label for="new_password">كلمة المرور الجديدة</label>
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if (isset($errors['new_password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['new_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Password Strength Meter -->
                        <div class="strength-meter">
                            <div class="bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text text-muted" id="strengthText"></div>
                        
                        <!-- Confirm Password -->
                        <div class="form-floating position-relative mt-3">
                            <i class="bi bi-key-fill input-icon"></i>
                            <input type="password" 
                                   class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="تأكيد كلمة المرور الجديدة"
                                   minlength="6"
                                   required>
                            <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-submit">
                                <i class="bi bi-check-lg me-2"></i>
                                تغيير كلمة المرور
                            </button>
                            <a href="<?= url('settings.php') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-right me-1"></i>
                                إلغاء
                            </a>
                        </div>
                    </form>
                    
                    <!-- Security Tips -->
                    <div class="security-tips">
                        <h6><i class="bi bi-lightbulb me-1"></i> نصائح أمنية</h6>
                        <ul>
                            <li>استخدم 6 أحرف على الأقل</li>
                            <li>امزج بين الحروف والأرقام والرموز</li>
                            <li>تجنب استخدام معلومات شخصية</li>
                            <li>لا تشارك كلمة المرور مع أحد</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function checkStrength(password) {
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
    
    bar.className = 'bar';
    
    if (password.length === 0) {
        bar.style.width = '0';
        text.textContent = '';
    } else if (strength <= 1) {
        bar.classList.add('weak');
        text.textContent = 'ضعيفة';
        text.className = 'strength-text text-danger';
    } else if (strength <= 2) {
        bar.classList.add('fair');
        text.textContent = 'متوسطة';
        text.className = 'strength-text text-warning';
    } else if (strength <= 3) {
        bar.classList.add('good');
        text.textContent = 'جيدة';
        text.className = 'strength-text text-info';
    } else {
        bar.classList.add('strong');
        text.textContent = 'قوية';
        text.className = 'strength-text text-success';
    }
}

// Validate confirm password
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    if (this.value && this.value !== newPass) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
