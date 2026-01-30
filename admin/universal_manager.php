<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * مدير قاعدة البيانات الشامل (God Mode)
 * Universal Database Manager
 * =====================================================
 * ⚠️ للمسؤولين فقط - مستوى 10
 * ⚠️ Super Admin Only - Level 10
 * =====================================================
 */

// تحميل الإعدادات
require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// حماية الصفحة - يجب تسجيل الدخول
check_login();

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من صلاحية God Mode (مستوى 10 فقط)
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SESSION['role_level'] < 10) {
    log_activity('unauthorized_access', 'security', 'محاولة وصول غير مصرح لمدير قاعدة البيانات', current_user_id(), 'user');
    flash('danger', 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
    redirect(url('index.php'));
}

// ═══════════════════════════════════════════════════════════════════════════════
// المتغيرات
// ═══════════════════════════════════════════════════════════════════════════════
$selectedTable = $_GET['table'] ?? '';
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$searchQuery = $_GET['search'] ?? '';
$perPage = 50;
$offset = ($currentPage - 1) * $perPage;

$tables = [];
$columns = [];
$rows = [];
$totalRows = 0;
$primaryKey = 'id';
$jsonColumns = [];

// ═══════════════════════════════════════════════════════════════════════════════
// جلب قائمة الجداول
// ═══════════════════════════════════════════════════════════════════════════════
try {
    $tablesResult = Database::fetchAll("SHOW TABLES");
    foreach ($tablesResult as $row) {
        $tables[] = array_values($row)[0];
    }
} catch (PDOException $e) {
    $error = "خطأ في جلب الجداول: " . $e->getMessage();
}

// ═══════════════════════════════════════════════════════════════════════════════
// إذا تم اختيار جدول
// ═══════════════════════════════════════════════════════════════════════════════
if ($selectedTable && in_array($selectedTable, $tables)) {
    try {
        // جلب هيكل الجدول
        $columnsResult = Database::fetchAll("DESCRIBE `{$selectedTable}`");
        
        foreach ($columnsResult as $col) {
            $columns[] = [
                'name' => $col['Field'],
                'type' => $col['Type'],
                'null' => $col['Null'] === 'YES',
                'key' => $col['Key'],
                'default' => $col['Default'],
                'extra' => $col['Extra']
            ];
            
            // تحديد المفتاح الأساسي
            if ($col['Key'] === 'PRI') {
                $primaryKey = $col['Field'];
            }
            
            // تحديد أعمدة JSON
            if (stripos($col['Type'], 'json') !== false || 
                stripos($col['Type'], 'text') !== false ||
                stripos($col['Type'], 'longtext') !== false) {
                $jsonColumns[] = $col['Field'];
            }
        }
        
        // بناء استعلام البحث
        $whereClause = '';
        $params = [];
        
        if (!empty($searchQuery)) {
            $searchConditions = [];
            foreach ($columns as $col) {
                // البحث في الأعمدة النصية فقط
                if (preg_match('/(char|text|varchar)/i', $col['type'])) {
                    $searchConditions[] = "`{$col['name']}` LIKE :search";
                }
            }
            if (!empty($searchConditions)) {
                $whereClause = "WHERE " . implode(' OR ', $searchConditions);
                $params['search'] = "%{$searchQuery}%";
            }
        }
        
        // جلب إجمالي السجلات
        $countSql = "SELECT COUNT(*) FROM `{$selectedTable}` {$whereClause}";
        $totalRows = (int) Database::fetchValue($countSql, $params);
        
        // جلب البيانات مع الترقيم
        $dataSql = "SELECT * FROM `{$selectedTable}` {$whereClause} ORDER BY `{$primaryKey}` DESC LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::fetchAll($dataSql, $params);
        
    } catch (PDOException $e) {
        $error = "خطأ في جلب البيانات: " . $e->getMessage();
    }
}

// حساب الصفحات
$totalPages = ceil($totalRows / $perPage);

// ═══════════════════════════════════════════════════════════════════════════════
// إعدادات الصفحة
// ═══════════════════════════════════════════════════════════════════════════════
$pageTitle = 'مدير قاعدة البيانات';
$hideBottomNav = true;

// أنماط إضافية
$additionalStyles = <<<CSS
/* تصميم كثيف لشبكة البيانات */
.data-grid-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.data-grid {
    font-size: 0.8rem;
    margin: 0;
}

.data-grid thead th {
    background: linear-gradient(135deg, #ff6f00 0%, #283593 100%);
    color: #fff;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 10px 8px;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
    border: none;
}

.data-grid thead th .col-type {
    display: block;
    font-size: 0.65rem;
    font-weight: 400;
    opacity: 0.7;
    margin-top: 2px;
}

.data-grid tbody td {
    padding: 6px 8px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.data-grid tbody tr:hover {
    background: #f8f9ff;
}

.data-grid tbody tr:hover td {
    border-color: #e8e8ff;
}

/* خلية قابلة للتحرير */
.editable-cell {
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
    border-radius: 4px;
    padding: 4px 6px !important;
}

.editable-cell:hover {
    background: #fff3e0;
    box-shadow: inset 0 0 0 2px #ff9800;
}

.editable-cell.editing {
    background: #fff;
    box-shadow: inset 0 0 0 2px #ff6f00;
    overflow: visible;
    white-space: normal;
    z-index: 100;
}

.editable-cell input,
.editable-cell textarea {
    width: 100%;
    min-width: 150px;
    border: none;
    background: transparent;
    font-size: inherit;
    font-family: inherit;
    padding: 0;
    margin: 0;
    outline: none;
}

.editable-cell textarea {
    min-height: 60px;
    resize: vertical;
}

/* خلية JSON */
.json-cell {
    background: #e3f2fd;
    color: #1565c0;
    font-family: 'Courier New', monospace;
    font-size: 0.7rem;
}

.json-cell:hover {
    background: #bbdefb;
}

/* خلية المفتاح الأساسي */
.pk-cell {
    background: #fff8e1;
    font-weight: 700;
    color: #f57c00;
}

/* خلية فارغة */
.null-cell {
    color: #9e9e9e;
    font-style: italic;
}

/* أزرار الإجراءات */
.row-actions {
    white-space: nowrap;
}

.row-actions .btn {
    padding: 4px 8px;
    font-size: 0.7rem;
}

/* شريط الأدوات */
.toolbar {
    background: #f8f9fa;
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
}

/* محدد الجدول */
.table-selector {
    max-width: 300px;
}

.table-selector .form-select {
    font-weight: 600;
    border: 2px solid #ff6f00;
    border-radius: 8px;
}

/* مربع البحث */
.search-box {
    max-width: 300px;
}

.search-box .form-control {
    border-radius: 8px;
    padding-right: 40px;
}

.search-box .search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9e9e9e;
}

/* الترقيم */
.pagination-info {
    font-size: 0.85rem;
    color: #666;
}

/* نافذة JSON */
.json-modal .modal-body {
    padding: 0;
}

.json-editor {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    line-height: 1.5;
    border: none;
    border-radius: 0;
    min-height: 300px;
    background: #1e1e1e;
    color: #d4d4d4;
}

.json-editor:focus {
    box-shadow: none;
    background: #1e1e1e;
    color: #d4d4d4;
}

/* شارات الأنواع */
.type-badge {
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 500;
}

.type-int { background: #e8f5e9; color: #2e7d32; }
.type-varchar { background: #e3f2fd; color: #1565c0; }
.type-text { background: #fff3e0; color: #ff6f00; }
.type-json { background: #fce4ec; color: #c2185b; }
.type-datetime { background: #f3e5f5; color: #7b1fa2; }
.type-decimal { background: #e0f7fa; color: #00838f; }
.type-enum { background: #fff8e1; color: #ff8f00; }
.type-tinyint { background: #efebe9; color: #5d4037; }

/* التجاوب */
@media (max-width: 768px) {
    .toolbar {
        flex-direction: column;
        gap: 12px;
    }
    
    .table-selector,
    .search-box {
        max-width: 100%;
    }
}
CSS;

// تحميل رأس الصفحة
include INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-3">
    
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- العنوان -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-database-gear text-primary me-2"></i>
                مدير قاعدة البيانات الشامل
            </h4>
            <p class="text-muted mb-0 small">
                <i class="bi bi-shield-lock text-danger me-1"></i>
                وضع God Mode - جميع الجداول قابلة للتحرير
            </p>
        </div>
        <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right me-1"></i>
            العودة للوحة التحكم
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- شريط الأدوات -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="data-grid-container mb-4">
        <div class="toolbar d-flex flex-wrap justify-content-between align-items-center gap-3">
            <!-- محدد الجدول -->
            <div class="table-selector">
                <label class="form-label small fw-bold mb-1">
                    <i class="bi bi-table me-1"></i>
                    اختر الجدول
                </label>
                <select class="form-select" id="tableSelector" onchange="selectTable(this.value)">
                    <option value="">-- اختر جدول --</option>
                    <?php foreach ($tables as $table): ?>
                    <option value="<?= e($table) ?>" <?= $selectedTable === $table ? 'selected' : '' ?>>
                        <?= e($table) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($selectedTable): ?>
            <!-- مربع البحث -->
            <div class="search-box position-relative flex-grow-1">
                <label class="form-label small fw-bold mb-1">
                    <i class="bi bi-search me-1"></i>
                    بحث
                </label>
                <input type="text" 
                       class="form-control" 
                       id="searchInput"
                       placeholder="ابحث في البيانات..."
                       value="<?= e($searchQuery) ?>"
                       onkeypress="if(event.key==='Enter') performSearch()">
                <i class="bi bi-search search-icon"></i>
            </div>
            
            <!-- معلومات -->
            <div class="text-end">
                <div class="small text-muted">
                    <i class="bi bi-layers me-1"></i>
                    <?= number_format($totalRows) ?> سجل
                </div>
                <div class="small text-muted">
                    <i class="bi bi-key me-1"></i>
                    PK: <code><?= e($primaryKey) ?></code>
                </div>
            </div>
            
            <!-- أزرار -->
            <div>
                <button class="btn btn-success btn-sm me-1" onclick="openAddModal()">
                    <i class="bi bi-plus-lg me-1"></i>
                    إضافة سجل
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    تحديث
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($selectedTable && !empty($columns)): ?>
        <!-- ═══════════════════════════════════════════════════════════════════════ -->
        <!-- جدول البيانات -->
        <!-- ═══════════════════════════════════════════════════════════════════════ -->
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
            <table class="table data-grid" id="dataGrid">
                <thead>
                    <tr>
                        <th style="width: 80px;">#</th>
                        <?php foreach ($columns as $col): ?>
                        <th>
                            <?= e($col['name']) ?>
                            <?php if ($col['key'] === 'PRI'): ?>
                            <i class="bi bi-key-fill text-warning ms-1"></i>
                            <?php endif; ?>
                            <span class="col-type"><?= e($col['type']) ?></span>
                        </th>
                        <?php endforeach; ?>
                        <th style="width: 100px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= count($columns) + 2 ?>" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>
                            لا توجد بيانات
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $index => $row): ?>
                    <tr data-pk="<?= e($row[$primaryKey]) ?>">
                        <td class="text-muted small"><?= $offset + $index + 1 ?></td>
                        <?php foreach ($columns as $col): ?>
                        <?php 
                            $colName = $col['name'];
                            $value = $row[$colName];
                            $isPK = $col['key'] === 'PRI';
                            $isJson = in_array($colName, $jsonColumns);
                            $isNull = $value === null;
                            
                            // تحديد نوع العمود للتنسيق
                            $typeClass = '';
                            if (preg_match('/^(big)?int/i', $col['type'])) $typeClass = 'type-int';
                            elseif (preg_match('/varchar/i', $col['type'])) $typeClass = 'type-varchar';
                            elseif (preg_match('/text/i', $col['type'])) $typeClass = 'type-text';
                            elseif (preg_match('/json/i', $col['type'])) $typeClass = 'type-json';
                            elseif (preg_match('/datetime|timestamp/i', $col['type'])) $typeClass = 'type-datetime';
                            elseif (preg_match('/decimal/i', $col['type'])) $typeClass = 'type-decimal';
                            elseif (preg_match('/enum/i', $col['type'])) $typeClass = 'type-enum';
                            elseif (preg_match('/tinyint/i', $col['type'])) $typeClass = 'type-tinyint';
                            
                            $cellClass = 'editable-cell';
                            if ($isPK) $cellClass .= ' pk-cell';
                            if ($isJson) $cellClass .= ' json-cell';
                            if ($isNull) $cellClass .= ' null-cell';
                        ?>
                        <td class="<?= $cellClass ?>"
                            data-column="<?= e($colName) ?>"
                            data-type="<?= $isJson ? 'json' : 'text' ?>"
                            data-pk="<?= e($row[$primaryKey]) ?>"
                            data-original="<?= e($value ?? '') ?>"
                            <?php if (!$isPK): ?>
                            onclick="editCell(this)"
                            <?php endif; ?>
                            title="<?= e(mb_substr($value ?? 'NULL', 0, 500)) ?>">
                            <?php if ($isNull): ?>
                                <em>NULL</em>
                            <?php elseif ($isJson && !empty($value)): ?>
                                <span class="badge type-badge type-json">JSON</span>
                                <?= e(mb_substr($value, 0, 30)) ?>...
                            <?php else: ?>
                                <?= e(mb_substr($value, 0, 50)) ?><?= mb_strlen($value) > 50 ? '...' : '' ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="row-actions">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewRow(<?= e($row[$primaryKey]) ?>)" title="عرض">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteRow(<?= e($row[$primaryKey]) ?>)" title="حذف">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ═══════════════════════════════════════════════════════════════════════ -->
        <!-- الترقيم -->
        <!-- ═══════════════════════════════════════════════════════════════════════ -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="pagination-info">
                عرض <?= $offset + 1 ?> - <?= min($offset + $perPage, $totalRows) ?> من <?= number_format($totalRows) ?> سجل
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?table=<?= e($selectedTable) ?>&page=1&search=<?= e($searchQuery) ?>">
                            <i class="bi bi-chevron-double-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?table=<?= e($selectedTable) ?>&page=<?= $currentPage - 1 ?>&search=<?= e($searchQuery) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?table=<?= e($selectedTable) ?>&page=<?= $i ?>&search=<?= e($searchQuery) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?table=<?= e($selectedTable) ?>&page=<?= $currentPage + 1 ?>&search=<?= e($searchQuery) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?table=<?= e($selectedTable) ?>&page=<?= $totalPages ?>&search=<?= e($searchQuery) ?>">
                            <i class="bi bi-chevron-double-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php elseif (!$selectedTable): ?>
        <!-- رسالة الترحيب -->
        <div class="text-center py-5">
            <i class="bi bi-database display-1 text-primary opacity-25"></i>
            <h5 class="mt-3 text-muted">اختر جدولاً للبدء</h5>
            <p class="text-muted small">يمكنك عرض وتحرير أي جدول في قاعدة البيانات</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- نافذة تحرير JSON -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="jsonModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="bi bi-code-square me-2"></i>
                    تحرير JSON
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body json-modal p-0">
                <div class="p-2 bg-light border-bottom small">
                    <span class="text-muted">العمود:</span>
                    <code id="jsonColumnName"></code>
                </div>
                <textarea class="form-control json-editor" id="jsonEditor" rows="15" dir="ltr"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-warning" onclick="formatJson()">
                    <i class="bi bi-braces me-1"></i>
                    تنسيق
                </button>
                <button type="button" class="btn btn-primary" onclick="saveJson()">
                    <i class="bi bi-check-lg me-1"></i>
                    حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- نافذة عرض السجل -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>
                    عرض السجل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- سيتم ملؤها بالـ JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- نافذة إضافة سجل -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>
                    إضافة سجل جديد
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="addModalBody">
                <form id="addForm">
                    <!-- سيتم ملؤها بالـ JavaScript -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" onclick="saveNewRecord()">
                    <i class="bi bi-check-lg me-1"></i>
                    حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════════
// متغيرات عامة
// ═══════════════════════════════════════════════════════════════════════════════
const currentTable = '<?= e($selectedTable) ?>';
const primaryKey = '<?= e($primaryKey) ?>';
const columns = <?= json_encode($columns, JSON_UNESCAPED_UNICODE) ?>;
const jsonColumns = <?= json_encode($jsonColumns, JSON_UNESCAPED_UNICODE) ?>;
let currentEditCell = null;
let jsonModalInstance = null;
let currentJsonCell = null;

// ═══════════════════════════════════════════════════════════════════════════════
// اختيار جدول
// ═══════════════════════════════════════════════════════════════════════════════
function selectTable(tableName) {
    if (tableName) {
        window.location.href = '?table=' + encodeURIComponent(tableName);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// البحث
// ═══════════════════════════════════════════════════════════════════════════════
function performSearch() {
    const query = document.getElementById('searchInput').value;
    window.location.href = '?table=' + encodeURIComponent(currentTable) + '&search=' + encodeURIComponent(query);
}

// ═══════════════════════════════════════════════════════════════════════════════
// تحديث البيانات
// ═══════════════════════════════════════════════════════════════════════════════
function refreshData() {
    window.location.reload();
}

// ═══════════════════════════════════════════════════════════════════════════════
// تحرير خلية
// ═══════════════════════════════════════════════════════════════════════════════
function editCell(cell) {
    // إذا كانت خلية JSON، افتح النافذة
    if (cell.dataset.type === 'json') {
        openJsonEditor(cell);
        return;
    }
    
    // إغلاق أي خلية مفتوحة
    if (currentEditCell && currentEditCell !== cell) {
        cancelEdit(currentEditCell);
    }
    
    // إذا كانت الخلية في وضع التحرير بالفعل
    if (cell.classList.contains('editing')) {
        return;
    }
    
    currentEditCell = cell;
    const originalValue = cell.dataset.original;
    const column = cell.dataset.column;
    
    cell.classList.add('editing');
    
    // إنشاء حقل الإدخال
    const input = document.createElement('input');
    input.type = 'text';
    input.value = originalValue === 'NULL' ? '' : originalValue;
    input.dataset.original = originalValue;
    
    // حفظ عند الضغط على Enter، إلغاء عند Escape
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            saveCell(cell, input.value);
        } else if (e.key === 'Escape') {
            cancelEdit(cell);
        }
    });
    
    // حفظ عند فقدان التركيز
    input.addEventListener('blur', function() {
        setTimeout(() => {
            if (cell.classList.contains('editing')) {
                saveCell(cell, input.value);
            }
        }, 100);
    });
    
    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    input.select();
}

// ═══════════════════════════════════════════════════════════════════════════════
// حفظ الخلية
// ═══════════════════════════════════════════════════════════════════════════════
async function saveCell(cell, newValue) {
    const originalValue = cell.dataset.original;
    const column = cell.dataset.column;
    const pk = cell.dataset.pk;
    
    // إذا لم تتغير القيمة
    if (newValue === originalValue || (newValue === '' && originalValue === 'NULL')) {
        cancelEdit(cell);
        return;
    }
    
    cell.classList.remove('editing');
    cell.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    try {
        const response = await fetch('<?= url('api/universal_action.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({
                action: 'update',
                table: currentTable,
                pk_column: primaryKey,
                pk_value: pk,
                column: column,
                value: newValue === '' ? null : newValue
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cell.dataset.original = newValue;
            cell.textContent = newValue || 'NULL';
            if (!newValue) cell.classList.add('null-cell');
            else cell.classList.remove('null-cell');
            
            showSuccess('تم الحفظ بنجاح');
        } else {
            throw new Error(data.message || 'فشل الحفظ');
        }
    } catch (error) {
        showError(error.message);
        cell.textContent = originalValue === 'NULL' ? 'NULL' : originalValue;
    }
    
    currentEditCell = null;
}

// ═══════════════════════════════════════════════════════════════════════════════
// إلغاء التحرير
// ═══════════════════════════════════════════════════════════════════════════════
function cancelEdit(cell) {
    cell.classList.remove('editing');
    const originalValue = cell.dataset.original;
    cell.textContent = originalValue === '' ? 'NULL' : originalValue;
    currentEditCell = null;
}

// ═══════════════════════════════════════════════════════════════════════════════
// تحرير JSON
// ═══════════════════════════════════════════════════════════════════════════════
function openJsonEditor(cell) {
    currentJsonCell = cell;
    const column = cell.dataset.column;
    const value = cell.dataset.original;
    
    document.getElementById('jsonColumnName').textContent = column;
    document.getElementById('jsonEditor').value = value || '{}';
    
    // محاولة تنسيق JSON
    try {
        const parsed = JSON.parse(value || '{}');
        document.getElementById('jsonEditor').value = JSON.stringify(parsed, null, 2);
    } catch (e) {
        // إذا لم يكن JSON صالحاً، اتركه كما هو
    }
    
    if (!jsonModalInstance) {
        jsonModalInstance = new bootstrap.Modal(document.getElementById('jsonModal'));
    }
    jsonModalInstance.show();
}

// ═══════════════════════════════════════════════════════════════════════════════
// تنسيق JSON
// ═══════════════════════════════════════════════════════════════════════════════
function formatJson() {
    const editor = document.getElementById('jsonEditor');
    try {
        const parsed = JSON.parse(editor.value);
        editor.value = JSON.stringify(parsed, null, 2);
    } catch (e) {
        showError('JSON غير صالح: ' + e.message);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// حفظ JSON
// ═══════════════════════════════════════════════════════════════════════════════
async function saveJson() {
    const editor = document.getElementById('jsonEditor');
    const newValue = editor.value;
    
    // التحقق من صحة JSON
    try {
        JSON.parse(newValue);
    } catch (e) {
        showError('JSON غير صالح: ' + e.message);
        return;
    }
    
    const cell = currentJsonCell;
    const column = cell.dataset.column;
    const pk = cell.dataset.pk;
    
    try {
        const response = await fetch('<?= url('api/universal_action.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({
                action: 'update',
                table: currentTable,
                pk_column: primaryKey,
                pk_value: pk,
                column: column,
                value: newValue
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cell.dataset.original = newValue;
            cell.innerHTML = '<span class="badge type-badge type-json">JSON</span> ' + 
                            newValue.substring(0, 30) + '...';
            jsonModalInstance.hide();
            showSuccess('تم حفظ JSON بنجاح');
        } else {
            throw new Error(data.message || 'فشل الحفظ');
        }
    } catch (error) {
        showError(error.message);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// حذف سجل
// ═══════════════════════════════════════════════════════════════════════════════
async function deleteRow(pkValue) {
    const confirmed = await showConfirm(
        'تأكيد الحذف',
        'هل أنت متأكد من حذف هذا السجل؟ لا يمكن التراجع عن هذا الإجراء.',
        'نعم، احذف',
        'إلغاء'
    );
    
    if (!confirmed) return;
    
    try {
        const response = await fetch('<?= url('api/universal_action.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({
                action: 'delete',
                table: currentTable,
                pk_column: primaryKey,
                pk_value: pkValue
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // حذف الصف من الجدول
            const row = document.querySelector(`tr[data-pk="${pkValue}"]`);
            if (row) {
                row.style.background = '#ffebee';
                setTimeout(() => row.remove(), 300);
            }
            showSuccess('تم الحذف بنجاح');
        } else {
            throw new Error(data.message || 'فشل الحذف');
        }
    } catch (error) {
        showError(error.message);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// عرض سجل
// ═══════════════════════════════════════════════════════════════════════════════
function viewRow(pkValue) {
    const row = document.querySelector(`tr[data-pk="${pkValue}"]`);
    if (!row) return;
    
    let html = '<table class="table table-sm table-bordered">';
    html += '<thead><tr><th style="width:30%;">العمود</th><th>القيمة</th></tr></thead><tbody>';
    
    columns.forEach((col, index) => {
        const cell = row.cells[index + 1]; // +1 بسبب عمود الترقيم
        const value = cell.dataset.original || cell.textContent;
        const isJson = jsonColumns.includes(col.name);
        
        html += `<tr>
            <td><strong>${col.name}</strong><br><small class="text-muted">${col.type}</small></td>
            <td>${isJson ? `<pre class="mb-0" style="max-height:200px;overflow:auto;font-size:0.75rem;">${escapeHtml(value)}</pre>` : escapeHtml(value)}</td>
        </tr>`;
    });
    
    html += '</tbody></table>';
    
    document.getElementById('viewModalBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// ═══════════════════════════════════════════════════════════════════════════════
// إضافة سجل جديد
// ═══════════════════════════════════════════════════════════════════════════════
function openAddModal() {
    let html = '';
    
    columns.forEach(col => {
        // تخطي الأعمدة التلقائية
        if (col.extra === 'auto_increment') return;
        if (col.name === 'created_at' || col.name === 'updated_at') return;
        
        const isJson = jsonColumns.includes(col.name);
        const isRequired = !col.null && col.default === null;
        
        html += `<div class="mb-3">
            <label class="form-label fw-bold">
                ${col.name}
                ${isRequired ? '<span class="text-danger">*</span>' : ''}
                <small class="text-muted fw-normal">(${col.type})</small>
            </label>`;
        
        if (isJson) {
            html += `<textarea class="form-control font-monospace" name="${col.name}" rows="3" 
                     placeholder='{"key": "value"}'></textarea>`;
        } else if (col.type.includes('text')) {
            html += `<textarea class="form-control" name="${col.name}" rows="2"></textarea>`;
        } else if (col.type.includes('enum')) {
            // استخراج قيم ENUM
            const enumMatch = col.type.match(/enum\((.+)\)/i);
            if (enumMatch) {
                const options = enumMatch[1].split(',').map(o => o.trim().replace(/'/g, ''));
                html += `<select class="form-select" name="${col.name}">
                    <option value="">-- اختر --</option>
                    ${options.map(o => `<option value="${o}">${o}</option>`).join('')}
                </select>`;
            }
        } else {
            html += `<input type="text" class="form-control" name="${col.name}" 
                     ${col.default ? `value="${col.default}"` : ''}>`;
        }
        
        html += '</div>';
    });
    
    document.getElementById('addForm').innerHTML = html;
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

// ═══════════════════════════════════════════════════════════════════════════════
// حفظ سجل جديد
// ═══════════════════════════════════════════════════════════════════════════════
async function saveNewRecord() {
    const form = document.getElementById('addForm');
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (value !== '') {
            data[key] = value;
        }
    }
    
    try {
        const response = await fetch('<?= url('api/universal_action.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: JSON.stringify({
                action: 'insert',
                table: currentTable,
                data: data
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('تم إضافة السجل بنجاح');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(result.message || 'فشل الإضافة');
        }
    } catch (error) {
        showError(error.message);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// دالة مساعدة للهروب من HTML
// ═══════════════════════════════════════════════════════════════════════════════
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
