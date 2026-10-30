<?php
// === إعدادات قاعدة البيانات ===
$db_host = 'localhost';
$db_user = 'u976858450_forms_admin'; 
$db_pass = 'FormsHub2026SecurePassX9'; 
$db_name = 'u976858450_forms_hub'; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// === إعدادات الدورات (تواريخ ومبالغ) ===
$cycles_config = [
    '2026-2027' => [
        'req_amount' => 2000,
        'start_date' => '2026-03-11',
        'end_date'   => '2027-03-10'
    ],
    '2027-2028' => [
        'req_amount' => 2000,
        'start_date' => '2027-03-11',
        'end_date'   => '2028-03-10'
    ]
];

$current_cycle = isset($_GET['cycle']) ? $_GET['cycle'] : '2026-2027';
$cycle_info = isset($cycles_config[$current_cycle]) ? $cycles_config[$current_cycle] : $cycles_config['2026-2027'];

$message = '';
$upload_dir = 'uploads/iqama/';

// === معالجة حفظ دفعة جديدة ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_installment'])) {
    $cycle_code = $_POST['cycle_code'];
    $amount = $_POST['amount'];
    $transfer_date = $_POST['transfer_date'];
    $notes = $_POST['notes'];
    
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    $file_path = '';
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == 0) {
        $file_name = time() . '_' . basename($_FILES['receipt_file']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        if (in_array($file_type, ['pdf', 'png', 'jpg', 'jpeg'])) {
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_file)) {
                $file_path = $target_file;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO iqama_installments (cycle_code, amount, transfer_date, file_path, notes) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$cycle_code, $amount, $transfer_date, $file_path, $notes])) {
        $message = '<div class="alert alert-success">تم حفظ الدفعة بنجاح.</div>';
    } else {
        $message = '<div class="alert alert-danger">حدث خطأ أثناء الحفظ.</div>';
    }
}

// === معالجة تعديل دفعة موجودة ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_installment'])) {
    $edit_id = $_POST['edit_id'];
    $edit_amount = $_POST['edit_amount'];
    $edit_transfer_date = $_POST['edit_transfer_date'];
    $edit_notes = $_POST['edit_notes'];

    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    $update_query = "UPDATE iqama_installments SET amount = ?, transfer_date = ?, notes = ?";
    $params = [$edit_amount, $edit_transfer_date, $edit_notes];

    // لو رفع إيصال جديد وقت التعديل
    if (isset($_FILES['edit_receipt_file']) && $_FILES['edit_receipt_file']['error'] == 0) {
        $file_name = time() . '_update_' . basename($_FILES['edit_receipt_file']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        if (in_array($file_type, ['pdf', 'png', 'jpg', 'jpeg'])) {
            if (move_uploaded_file($_FILES['edit_receipt_file']['tmp_name'], $target_file)) {
                $update_query .= ", file_path = ?";
                $params[] = $target_file;
            }
        }
    }

    $update_query .= " WHERE id = ?";
    $params[] = $edit_id;

    $stmt = $pdo->prepare($update_query);
    if ($stmt->execute($params)) {
        $message = '<div class="alert alert-success">تم تعديل الدفعة بنجاح.</div>';
    } else {
        $message = '<div class="alert alert-danger">حدث خطأ أثناء التعديل.</div>';
    }
}

// === جلب البيانات والحسابات ===
$stmt = $pdo->prepare("SELECT * FROM iqama_installments WHERE cycle_code = ? ORDER BY transfer_date ASC");
$stmt->execute([$current_cycle]);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$required_amount = $cycle_info['req_amount'];
$total_paid = 0;
foreach ($installments as $inst) {
    $total_paid += $inst['amount'];
}

$remaining = $required_amount - $total_paid;
$surplus = 0;
if ($remaining < 0) {
    $surplus = abs($remaining);
    $remaining = 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>متابعة تجديد الإقامة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .stat-card { border-radius: 10px; padding: 20px; text-align: center; color: white; transition: 0.3s; }
        .card-info { background-color: #0dcaf0; color: #333; }
        .card-success { background-color: #20c997; }
        .card-danger { background-color: #dc3545; }
        .card-completed { background-color: #198754; }
        .card-warning { background-color: #ffc107; color: #333; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .cycle-badge { background-color: #e9ecef; color: #495057; border: 1px solid #ced4da; }
    </style>
</head>
<body>

<div class="container mt-4">
    <!-- الترويسة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-success fw-bold"><i class="fa-solid fa-id-card me-2"></i>متابعة تجديد إقامة الزوجة</h3>
            <p class="text-muted mb-0">نظام إدارة الحوالات وسجل الدفعات</p>
        </div>
        <a href="../index.php" class="btn btn-outline-success"><i class="fa-solid fa-arrow-right me-2"></i>العودة للبوابة الرئيسية</a>
    </div>

    <?= $message ?>

    <!-- شريط معلومات الدورة -->
    <div class="alert cycle-badge d-flex justify-content-between align-items-center mb-4 shadow-sm">
        <div>
            <strong>إعدادات الدورة الحالية:</strong> تغطي الفترة من <span class="text-primary fw-bold" dir="ltr"><?= $cycle_info['start_date'] ?></span> وحتى <span class="text-primary fw-bold" dir="ltr"><?= $cycle_info['end_date'] ?></span>
        </div>
        <div>
            <strong>تاريخ انتهاء الإقامة:</strong> <span class="text-danger fw-bold" dir="ltr"><?= $cycle_info['end_date'] ?></span>
        </div>
    </div>

    <!-- لوحة المؤشرات -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card card-info shadow-sm"><h6>المبلغ المطلوب</h6><h3 dir="ltr">SAR <?= number_format($required_amount, 2) ?></h3></div></div>
        <div class="col-md-3"><div class="stat-card card-success shadow-sm"><h6>إجمالي المحول</h6><h3 dir="ltr">SAR <?= number_format($total_paid, 2) ?></h3></div></div>
        <div class="col-md-3"><div class="stat-card <?= $remaining == 0 ? 'card-completed' : 'card-danger' ?> shadow-sm"><h6>المتبقي</h6><h3 dir="ltr">SAR <?= number_format($remaining, 2) ?></h3></div></div>
        <div class="col-md-3"><div class="stat-card card-warning shadow-sm"><h6>الفائض</h6><h3 dir="ltr">SAR <?= number_format($surplus, 2) ?></h3></div></div>
    </div>

    <div class="row">
        <!-- جدول الحوالات -->
        <div class="col-md-8">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="text-success fw-bold"><i class="fa-solid fa-list-check me-2"></i>سجل الدفعات المسجلة</span>
                    <form method="GET" class="d-flex align-items-center m-0 p-0">
                        <label class="me-2 text-muted small fw-bold">الدورة:</label>
                        <select name="cycle" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <option value="2026-2027" <?= $current_cycle == '2026-2027' ? 'selected' : '' ?>>دورة 2026-2027</option>
                            <option value="2027-2028" <?= $current_cycle == '2027-2028' ? 'selected' : '' ?>>دورة 2027-2028</option>
                        </select>
                    </form>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover text-center mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>تصنيف الدفعة</th>
                                <th>المبلغ</th>
                                <th>تراكمي</th>
                                <th>الإيصال</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $cumulative = 0;
                            $count = 1;
                            foreach ($installments as $inst): 
                                $cumulative += $inst['amount'];
                                $is_advance = strtotime($inst['transfer_date']) < strtotime($cycle_info['start_date']);
                                $badge_class = $is_advance ? 'bg-primary' : 'bg-secondary';
                                $badge_text = $is_advance ? 'دفع مقدم' : 'داخل الدورة';
                            ?>
                            <tr>
                                <td class="align-middle text-muted"><?= $count++ ?></td>
                                <td class="align-middle" dir="ltr"><?= htmlspecialchars($inst['transfer_date']) ?></td>
                                <td class="align-middle"><span class="badge <?= $badge_class ?> rounded-pill px-2"><?= $badge_text ?></span></td>
                                <td class="align-middle text-success fw-bold" dir="ltr">SAR <?= number_format($inst['amount'], 2) ?></td>
                                <td class="align-middle fw-bold text-muted" dir="ltr">SAR <?= number_format($cumulative, 2) ?></td>
                                <td class="align-middle">
                                    <?php if($inst['file_path']): ?>
                                        <a href="<?= htmlspecialchars($inst['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="عرض الإيصال"><i class="fa-solid fa-paperclip"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="تعديل" 
                                            onclick="openEditModal(<?= $inst['id'] ?>, <?= $inst['amount'] ?>, '<?= $inst['transfer_date'] ?>', '<?= htmlspecialchars(addslashes($inst['notes']), ENT_QUOTES) ?>')">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- نموذج إضافة الدفعة -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white text-success fw-bold py-3"><i class="fa-solid fa-circle-plus me-2"></i>تسجيل دفعة جديدة</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">كود الدورة</label>
                            <input type="text" class="form-control bg-light" name="cycle_code" value="<?= htmlspecialchars($current_cycle) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">المبلغ (ريال)</label>
                            <input type="number" class="form-control" name="amount" step="any" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">تاريخ الحوالة</label>
                            <input type="date" class="form-control" name="transfer_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">إيصال السداد (اختياري)</label>
                            <input type="file" class="form-control" name="receipt_file" accept=".pdf,.png,.jpg,.jpeg">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">ملاحظات</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <button type="submit" name="save_installment" class="btn btn-success w-100 fw-bold shadow-sm">حفظ الدفعة</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal تعديل الدفعة -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold text-primary" id="editModalLabel"><i class="fa-solid fa-pen-to-square me-2"></i>تعديل بيانات الدفعة</h5>
        <button type="button" class="btn-close m-0 ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">المبلغ (ريال)</label>
                <input type="number" class="form-control" name="edit_amount" id="edit_amount" step="any" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">تاريخ الحوالة</label>
                <input type="date" class="form-control" name="edit_transfer_date" id="edit_transfer_date" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">تحديث الإيصال (اختياري)</label>
                <input type="file" class="form-control" name="edit_receipt_file" accept=".pdf,.png,.jpg,.jpeg">
                <small class="text-muted d-block mt-1">اتركه فارغاً إذا كنت لا تريد تغيير الإيصال الحالي.</small>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">ملاحظات</label>
                <textarea class="form-control" name="edit_notes" id="edit_notes" rows="2"></textarea>
            </div>
            <div class="text-end mt-4">
                <button type="button" class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" name="update_installment" class="btn btn-primary shadow-sm fw-bold">حفظ التعديلات</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(id, amount, date, notes) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_amount').value = amount;
        document.getElementById('edit_transfer_date').value = date;
        document.getElementById('edit_notes').value = notes;
        
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
</script>
</body>
</html>
