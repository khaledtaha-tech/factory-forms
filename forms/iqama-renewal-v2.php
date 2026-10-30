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

// === معالجة حفظ البيانات ورفع الملف ===
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_installment'])) {
    $cycle_code = $_POST['cycle_code'];
    $amount = $_POST['amount'];
    $transfer_date = $_POST['transfer_date'];
    $notes = $_POST['notes'];
    
    // إنشاء مجلد للمرفقات إذا لم يكن موجوداً
    $upload_dir = 'uploads/iqama/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_path = '';
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == 0) {
        $file_name = time() . '_' . basename($_FILES['receipt_file']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // التحقق من نوع الملف (PDF, PNG, JPG)
        if (in_array($file_type, ['pdf', 'png', 'jpg', 'jpeg'])) {
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_file)) {
                $file_path = $target_file;
            }
        }
    }

    if ($file_path != '') {
        $stmt = $pdo->prepare("INSERT INTO iqama_installments (cycle_code, amount, transfer_date, file_path, notes) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$cycle_code, $amount, $transfer_date, $file_path, $notes])) {
            $message = '<div class="alert alert-success">تم حفظ الدفعة ورفع الإيصال بنجاح.</div>';
        } else {
            $message = '<div class="alert alert-danger">حدث خطأ أثناء حفظ البيانات.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">الرجاء إرفاق إيصال بصيغة صحيحة (PDF, PNG, JPG).</div>';
    }
}

// === جلب البيانات لعرضها ===
$current_cycle = isset($_GET['cycle']) ? $_GET['cycle'] : '2026-2027';
$stmt = $pdo->prepare("SELECT * FROM iqama_installments WHERE cycle_code = ? ORDER BY transfer_date ASC");
$stmt->execute([$current_cycle]);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الحسابات
$required_amount = 2000;
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
    </style>
</head>
<body>

<div class="container mt-4">
    <!-- الترويسة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-success"><i class="fa-solid fa-id-card"></i> متابعة تجديد إقامة الزوجة</h3>
            <p class="text-muted mb-0">نظام إدارة الحوالات وإيصالات السداد</p>
        </div>
        <a href="../index.php" class="btn btn-outline-success"><i class="fa-solid fa-arrow-right"></i> عودة للبوابة</a>
    </div>

    <?= $message ?>

    <!-- لوحة المؤشرات -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card card-info"><h6>المطلوب</h6><h3 id="reqAmount"><?= $required_amount ?> SAR</h3></div></div>
        <div class="col-md-3"><div class="stat-card card-success"><h6>إجمالي المحول</h6><h3 id="totalPaid"><?= $total_paid ?> SAR</h3></div></div>
        <div class="col-md-3"><div class="stat-card <?= $remaining == 0 ? 'card-completed' : 'card-danger' ?>" id="remCard"><h6>المتبقي</h6><h3 id="remAmount"><?= $remaining ?> SAR</h3></div></div>
        <div class="col-md-3"><div class="stat-card card-warning"><h6>الفائض</h6><h3 id="surplusAmount"><?= $surplus ?> SAR</h3></div></div>
    </div>

    <div class="row">
        <!-- نموذج إضافة الدفعة -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header bg-white text-success fw-bold"><i class="fa-solid fa-plus-circle"></i> تسجيل دفعة جديدة</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label small">كود الدورة</label>
                            <input type="text" class="form-control" name="cycle_code" id="cycleCode" value="2026-2027" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">المبلغ (ريال)</label>
                            <input type="number" class="form-control" name="amount" step="any" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">تاريخ الحوالة</label>
                            <input type="date" class="form-control" name="transfer_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">إيصال السداد</label>
                            <input type="file" class="form-control" name="receipt_file" accept=".pdf,.png,.jpg,.jpeg" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">ملاحظات</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <button type="submit" name="save_installment" class="btn btn-success w-100" id="submitBtn">حفظ ورفع الإيصال</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- جدول الحوالات -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="text-success fw-bold"><i class="fa-solid fa-list"></i> سجل الدفعات</span>
                    <form method="GET" class="d-flex m-0 p-0">
                        <select name="cycle" id="cycleFilter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <option value="2026-2027" <?= $current_cycle == '2026-2027' ? 'selected' : '' ?>>دورة 2026-2027</option>
                            <option value="2027-2028" <?= $current_cycle == '2027-2028' ? 'selected' : '' ?>>دورة 2027-2028</option>
                        </select>
                    </form>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped text-center mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>تراكمي</th>
                                <th>الإيصال</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php 
                            $cumulative = 0;
                            foreach ($installments as $inst): 
                                $cumulative += $inst['amount'];
                            ?>
                            <tr>
                                <td class="align-middle"><?= htmlspecialchars($inst['transfer_date']) ?></td>
                                <td class="align-middle text-success fw-bold"><?= htmlspecialchars($inst['amount']) ?></td>
                                <td class="align-middle fw-bold text-muted"><?= $cumulative ?></td>
                                <td class="align-middle">
                                    <a href="<?= htmlspecialchars($inst['file_path']) ?>" target="_blank" class="badge bg-secondary p-2 text-decoration-none">
                                        <i class="fa-solid fa-paperclip"></i> عرض
                                    </a>
                                </td>
                                <td class="align-middle text-muted small"><?= htmlspecialchars($inst['notes']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
