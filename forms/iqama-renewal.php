<?php
/**
 * ==============================================================================
 * متابعة تجديد الإقامة - مصنع المنار للبلاستيك
 * Iqama Renewal & Installment Tracker - Al-Manar Plastic Factory
 * ==============================================================================
 */

require_once __DIR__ . '/../config.php';
$pdo = getDbConnection();
ensureIqamaInstallmentsTableExists();

// Handle New Installment Form Submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_installment'])) {
    $cycle_code    = trim($_POST['cycle_code'] ?? '2026-2027');
    $amount        = floatval($_POST['amount'] ?? 0);
    $transfer_date = trim($_POST['transfer_date'] ?? date('Y-m-d'));
    $notes         = trim($_POST['notes'] ?? '');

    $upload_dir = __DIR__ . '/../uploads/iqama/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    $file_path = '';
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['receipt_file']['name']));
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (in_array($file_type, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic'])) {
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_file)) {
                $file_path = 'uploads/iqama/' . $file_name;
            }
        }
    }

    if ($amount > 0 && !empty($transfer_date)) {
        $stmt = $pdo->prepare("INSERT INTO iqama_installments (cycle_code, amount, transfer_date, file_path, notes) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$cycle_code, $amount, $transfer_date, !empty($file_path) ? $file_path : null, !empty($notes) ? $notes : null])) {
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">تم حفظ الدفعة بنجاح.' . (!empty($file_path) ? ' ورفع ملف الإيصال.' : '') . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">حدث خطأ أثناء حفظ البيانات في قاعدة البيانات.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } else {
        $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">يرجى إدخال مبلغ صحيح وتاريخ الحوالة.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Fetch Installments for Selected Cycle
$current_cycle = isset($_GET['cycle']) ? trim($_GET['cycle']) : '2026-2027';
$stmt = $pdo->prepare("SELECT * FROM iqama_installments WHERE cycle_code = ? ORDER BY transfer_date ASC, id ASC");
$stmt->execute([$current_cycle]);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct cycles for filter dropdown
$cyclesStmt = $pdo->query("SELECT DISTINCT cycle_code FROM iqama_installments ORDER BY cycle_code DESC");
$availableCycles = $cyclesStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('2026-2027', $availableCycles)) {
    array_unshift($availableCycles, '2026-2027');
}

// Summary Metrics
$required_amount = 7500.00;
$total_paid = 0;
foreach ($installments as $inst) {
    $total_paid += floatval($inst['amount']);
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
    <title>متابعة تجديد الإقامة | مصنع المنار للبلاستيك</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="../favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .stat-card { border-radius: 12px; padding: 20px; text-align: center; color: white; transition: 0.3s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card-info { background: linear-gradient(135deg, #0284c7, #0ea5e9); }
        .card-success { background: linear-gradient(135deg, #059669, #10b981); }
        .card-danger { background: linear-gradient(135deg, #e11d48, #f43f5e); }
        .card-completed { background: linear-gradient(135deg, #15803d, #22c55e); }
        .card-warning { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .table-responsive { max-height: 520px; overflow-y: auto; }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h3 class="text-success fw-bold mb-1"><i class="fa-solid fa-id-card me-2"></i> متابعة تجديد إقامة الزوجة</h3>
            <p class="text-muted small mb-0">مصنع المنار للبلاستيك | نظام إدارة الحوالات وسجل الدفعات</p>
        </div>
        <a href="../index.php" class="btn btn-outline-success fw-bold px-3">
            <i class="fa-solid fa-arrow-right me-1"></i> العودة للبوابة الرئيسية
        </a>
    </div>

    <?= $message ?>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card card-info">
                <h6 class="text-white-50 small mb-1">المبلغ المطلوب</h6>
                <h3 class="fw-bold mb-0" id="reqAmount"><?= number_format($required_amount, 2) ?> <small class="fs-6">SAR</small></h3>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card card-success">
                <h6 class="text-white-50 small mb-1">إجمالي المحول</h6>
                <h3 class="fw-bold mb-0" id="totalPaid"><?= number_format($total_paid, 2) ?> <small class="fs-6">SAR</small></h3>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card <?= $remaining <= 0 ? 'card-completed' : 'card-danger' ?>" id="remCard">
                <h6 class="text-white-50 small mb-1">المتبقي</h6>
                <h3 class="fw-bold mb-0" id="remAmount"><?= number_format($remaining, 2) ?> <small class="fs-6">SAR</small></h3>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card card-warning">
                <h6 class="text-white-50 small mb-1">الفائض</h6>
                <h3 class="fw-bold mb-0" id="surplusAmount"><?= number_format($surplus, 2) ?> <small class="fs-6">SAR</small></h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- New Payment Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white text-success fw-bold py-3 border-bottom">
                    <i class="fa-solid fa-plus-circle me-1"></i> تسجيل دفعة جديدة
                </div>
                <div class="card-body p-3 p-sm-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">كود الدورة</label>
                            <input type="text" class="form-control" name="cycle_code" id="cycleCode" value="<?= htmlspecialchars($current_cycle) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">المبلغ (ريال)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">تاريخ الحوالة</label>
                            <input type="date" class="form-control" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">إيصال السداد (صورة أو PDF)</label>
                            <input type="file" class="form-control" name="receipt_file" accept=".pdf,.png,.jpg,.jpeg,.webp,.heic">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ملاحظات (اختياري)</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="تفاصيل البنك أو رقم الحوالة..."></textarea>
                        </div>
                        <button type="submit" name="save_installment" class="btn btn-success w-100 py-2 fw-bold" id="submitBtn">
                            <i class="fa-solid fa-check me-1"></i> حفظ ورفع الإيصال
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Remittances Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <span class="text-success fw-bold"><i class="fa-solid fa-list me-1"></i> سجل الدفعات المسجلة</span>
                    <form method="GET" class="d-flex align-items-center gap-2 m-0 p-0">
                        <label class="small text-muted fw-bold text-nowrap">الدورة:</label>
                        <select name="cycle" id="cycleFilter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <?php foreach ($availableCycles as $cyc): ?>
                            <option value="<?= htmlspecialchars($cyc) ?>" <?= $current_cycle === $cyc ? 'selected' : '' ?>>دورة <?= htmlspecialchars($cyc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped text-center mb-0 align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>تراكمي</th>
                                <th>الإيصال</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($installments)): ?>
                            <tr>
                                <td colspan="6" class="py-5 text-muted">لا توجد أي دفعات مسجلة لهذه الدورة حتى الآن.</td>
                            </tr>
                            <?php else: ?>
                            <?php 
                            $cumulative = 0;
                            $seq = 1;
                            foreach ($installments as $inst): 
                                $cumulative += floatval($inst['amount']);
                            ?>
                            <tr>
                                <td class="text-muted small"><?= $seq++ ?></td>
                                <td><?= htmlspecialchars($inst['transfer_date']) ?></td>
                                <td class="text-success fw-bold"><?= number_format($inst['amount'], 2) ?> SAR</td>
                                <td class="fw-bold text-muted"><?= number_format($cumulative, 2) ?> SAR</td>
                                <td>
                                    <?php if (!empty($inst['file_path'])): ?>
                                    <a href="../<?= htmlspecialchars($inst['file_path']) ?>" target="_blank" class="badge bg-secondary p-2 text-decoration-none">
                                        <i class="fa-solid fa-paperclip me-1"></i> عرض الإيصال
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($inst['notes'] ?? '') ?>">
                                    <?= htmlspecialchars($inst['notes'] ?? '—') ?>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
