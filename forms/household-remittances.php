<?php
/**
 * تحويلات البيت
 * سجل شخصي للحوالات بالجنيه المصري مع أرشفة إيصالات PDF والصور.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function moneyEgp(float $amount): string {
    return 'EGP ' . number_format($amount, 2);
}

function receiptDiskPath(?string $relativePath): ?string {
    if (!$relativePath || !str_starts_with($relativePath, 'uploads/household-remittances/')) {
        return null;
    }
    return dirname(__DIR__) . '/' . $relativePath;
}

function removeReceipt(?string $relativePath): void {
    $path = receiptDiskPath($relativePath);
    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads/household-remittances');
    if ($path && is_file($path) && $uploadsRoot) {
        $realPath = realpath($path);
        if ($realPath && str_starts_with($realPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            @unlink($realPath);
        }
    }
}

function saveReceipt(string $fieldName): ?string {
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('تعذر رفع الإيصال. أعد المحاولة بملف آخر.');
    }
    if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
        throw new RuntimeException('حجم الإيصال أكبر من الحد المسموح: 15 MB.');
    }

    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('نوع الملف غير مدعوم. المسموح: PDF أو JPG أو PNG أو WEBP أو HEIC.');
    }

    $directory = dirname(__DIR__) . '/uploads/household-remittances';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('تعذر تجهيز مجلد حفظ الإيصالات.');
    }

    $fileName = 'home_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = $directory . '/' . $fileName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('تعذر حفظ الإيصال على الاستضافة.');
    }

    return 'uploads/household-remittances/' . $fileName;
}

$message = '';
$messageType = 'success';

try {
    $pdo = getDbConnection();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS household_remittances (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            amount_egp DECIMAL(14,2) NOT NULL,
            transfer_date DATE NOT NULL,
            recipient VARCHAR(180) NULL,
            purpose VARCHAR(255) NULL,
            notes TEXT NULL,
            receipt_path VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_household_remittances_date (transfer_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $amount = (float)($_POST['amount_egp'] ?? 0);
            $date = trim((string)($_POST['transfer_date'] ?? ''));
            $recipient = trim((string)($_POST['recipient'] ?? ''));
            $purpose = trim((string)($_POST['purpose'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($amount <= 0) {
                throw new RuntimeException('اكتب مبلغ التحويل بالجنيه المصري.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('اختر تاريخ التحويل.');
            }

            $receiptPath = saveReceipt('receipt_file');
            $stmt = $pdo->prepare('INSERT INTO household_remittances (amount_egp, transfer_date, recipient, purpose, notes, receipt_path) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                round($amount, 2), $date,
                $recipient !== '' ? $recipient : null,
                $purpose !== '' ? $purpose : null,
                $notes !== '' ? $notes : null,
                $receiptPath
            ]);
            $message = 'تم حفظ التحويل وإيصال السداد بنجاح.';
        }

        if ($action === 'update') {
            $id = (int)($_POST['edit_id'] ?? 0);
            $amount = (float)($_POST['edit_amount_egp'] ?? 0);
            $date = trim((string)($_POST['edit_transfer_date'] ?? ''));
            $recipient = trim((string)($_POST['edit_recipient'] ?? ''));
            $purpose = trim((string)($_POST['edit_purpose'] ?? ''));
            $notes = trim((string)($_POST['edit_notes'] ?? ''));
            if ($id <= 0 || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('راجع مبلغ التحويل وتاريخه قبل الحفظ.');
            }

            $existingStmt = $pdo->prepare('SELECT receipt_path FROM household_remittances WHERE id = ?');
            $existingStmt->execute([$id]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                throw new RuntimeException('لم يتم العثور على التحويل المطلوب.');
            }

            $newReceipt = saveReceipt('edit_receipt_file');
            $receiptPath = $newReceipt ?: $existing['receipt_path'];
            $stmt = $pdo->prepare('UPDATE household_remittances SET amount_egp = ?, transfer_date = ?, recipient = ?, purpose = ?, notes = ?, receipt_path = ? WHERE id = ?');
            $stmt->execute([
                round($amount, 2), $date,
                $recipient !== '' ? $recipient : null,
                $purpose !== '' ? $purpose : null,
                $notes !== '' ? $notes : null,
                $receiptPath, $id
            ]);
            if ($newReceipt) {
                removeReceipt($existing['receipt_path']);
            }
            $message = 'تم تحديث التحويل بنجاح.';
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $existingStmt = $pdo->prepare('SELECT receipt_path FROM household_remittances WHERE id = ?');
            $existingStmt->execute([$id]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                throw new RuntimeException('لم يتم العثور على التحويل المطلوب.');
            }
            $pdo->prepare('DELETE FROM household_remittances WHERE id = ?')->execute([$id]);
            removeReceipt($existing['receipt_path']);
            $message = 'تم حذف التحويل والإيصال المرتبط به.';
        }
    }

    $records = $pdo->query('SELECT * FROM household_remittances ORDER BY transfer_date DESC, id DESC')->fetchAll();
    $stats = $pdo->query('SELECT COUNT(*) AS transfers_count, COALESCE(SUM(amount_egp), 0) AS total_egp, MAX(transfer_date) AS last_transfer_date FROM household_remittances')->fetch();
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $messageType = 'danger';
    $records = $records ?? [];
    $stats = $stats ?? ['transfers_count' => 0, 'total_egp' => 0, 'last_transfer_date' => null];
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تحويلات البيت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --green: #168a58; --mint: #1bc48f; --ink: #172033; --paper: #f4f7f6; }
        body { background: var(--paper); color: var(--ink); font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
        .home-page { width: 100%; max-width: none; }
        .page-title { color: var(--green); letter-spacing: -.5px; }
        .page-subtitle { color: #667085; }
        .hero-note { background: linear-gradient(135deg, #ecfdf5, #f8fffc); border: 1px solid #b7ead1; color: #176440; border-radius: 16px; }
        .stat-card { min-height: 135px; border: 0; border-radius: 18px; color: #fff; box-shadow: 0 10px 25px rgba(27, 52, 44, .12); }
        .stat-card .stat-label { font-size: 1rem; font-weight: 700; opacity: .96; }
        .stat-card .stat-value { direction: ltr; font-size: clamp(1.65rem, 2.5vw, 2.35rem); font-weight: 800; margin: .4rem 0 0; white-space: nowrap; }
        .stat-total { background: linear-gradient(135deg, #1dc3e3, #159dcc); color: #12313b; }
        .stat-count { background: linear-gradient(135deg, #21c997, #16a573); }
        .stat-last { background: linear-gradient(135deg, #188b57, #126d44); }
        .card { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 28px rgba(29, 48, 39, .08); }
        .card-header { background: #fff; border-bottom: 1px solid #e8efeb; padding: 1.1rem 1.35rem; }
        .table-responsive { max-height: 560px; overflow: auto; }
        .table { margin: 0; min-width: 830px; }
        .table th { background: #f7faf8; color: #344054; font-size: .88rem; white-space: nowrap; }
        .table td, .table th { vertical-align: middle; padding: .85rem .7rem; }
        .empty-state { padding: 4rem 1.5rem; color: #667085; text-align: center; }
        .empty-state i { color: #b2c7bb; font-size: 3.2rem; display: block; margin-bottom: 1rem; }
        .receipt-btn { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; }
        .field-label { color: #475467; font-size: .88rem; font-weight: 700; }
        .form-control, .form-select { padding: .7rem .85rem; border-color: #d7e2dc; }
        .form-control:focus { border-color: #21b37a; box-shadow: 0 0 0 .2rem rgba(33, 179, 122, .14); }
        .btn-save { background: var(--green); border-color: var(--green); padding: .78rem; }
        .btn-save:hover { background: #107648; border-color: #107648; }
        @media (min-width: 1200px) { .home-page { padding-right: 2rem !important; padding-left: 2rem !important; } }
    </style>
</head>
<body>
<main class="container-fluid home-page px-3 px-lg-4 mt-3 mb-4">
    <header class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title h2 fw-bold mb-1"><i class="fa-solid fa-house-heart ms-2"></i>تحويلات البيت</h1>
            <p class="page-subtitle mb-0">سجل التحويلات المنزلية وإيصالات السداد بالجنيه المصري</p>
        </div>
        <a href="../index.php" class="btn btn-outline-success px-3"><i class="fa-solid fa-arrow-right ms-2"></i>العودة للبوابة الرئيسية</a>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= h($messageType) ?> border-0 shadow-sm mb-4" role="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="hero-note d-flex align-items-center gap-3 px-4 py-3 mb-4">
        <i class="fa-solid fa-file-shield fs-3"></i>
        <div><strong>أرشيف دائم.</strong> كل تحويل يمكن ربطه بإيصال PDF أو صورة، وفتحه من أي جهاز عبر هذه الصفحة.</div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-card stat-total p-4"><div class="stat-label">إجمالي التحويلات</div><div class="stat-value"><?= h(moneyEgp((float)$stats['total_egp'])) ?></div></div></div>
        <div class="col-md-4"><div class="stat-card stat-count p-4"><div class="stat-label">عدد التحويلات المسجلة</div><div class="stat-value"><?= number_format((int)$stats['transfers_count']) ?></div></div></div>
        <div class="col-md-4"><div class="stat-card stat-last p-4"><div class="stat-label">آخر تحويل مسجل</div><div class="stat-value"><?= $stats['last_transfer_date'] ? h($stats['last_transfer_date']) : '—' ?></div></div></div>
    </section>

    <section class="row g-4 align-items-start">
        <div class="col-xl-8 order-2 order-xl-1">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h2 class="h5 text-success fw-bold mb-0"><i class="fa-solid fa-list-check ms-2"></i>سجل التحويلات</h2>
                    <span class="badge text-bg-light border text-secondary rounded-pill px-3 py-2"><?= number_format((int)$stats['transfers_count']) ?> تحويل</span>
                </div>
                <?php if (!$records): ?>
                    <div class="empty-state"><i class="fa-solid fa-money-bill-transfer"></i><strong class="d-block mb-1">لا توجد تحويلات مسجلة بعد</strong><span>أضف أول تحويل من النموذج المجاور.</span></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover text-center">
                            <thead class="sticky-top"><tr><th>#</th><th>التاريخ</th><th>المبلغ</th><th>المستلم / الجهة</th><th>الغرض</th><th>الإيصال</th><th>إجراءات</th></tr></thead>
                            <tbody>
                            <?php foreach ($records as $index => $record): ?>
                                <tr>
                                    <td class="text-muted"><?= number_format($index + 1) ?></td>
                                    <td dir="ltr" class="fw-semibold"><?= h($record['transfer_date']) ?></td>
                                    <td dir="ltr" class="text-success fw-bold"><?= h(moneyEgp((float)$record['amount_egp'])) ?></td>
                                    <td><?= $record['recipient'] ? h($record['recipient']) : '<span class="text-muted">—</span>' ?></td>
                                    <td><?= $record['purpose'] ? h($record['purpose']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <?php if (!empty($record['receipt_path'])): ?>
                                            <a class="btn btn-outline-secondary receipt-btn" href="../<?= h($record['receipt_path']) ?>" target="_blank" rel="noopener" title="فتح الإيصال"><i class="fa-solid fa-paperclip"></i></a>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-outline-primary btn-sm" title="تعديل" onclick='openEditModal(<?= (int)$record['id'] ?>, <?= json_encode((float)$record['amount_egp']) ?>, <?= json_encode($record['transfer_date']) ?>, <?= json_encode($record['recipient'] ?? '') ?>, <?= json_encode($record['purpose'] ?? '') ?>, <?= json_encode($record['notes'] ?? '') ?>)'><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('هل تريد حذف هذا التحويل والإيصال المرتبط به نهائياً؟');">
                                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
                                            <button class="btn btn-outline-danger btn-sm" title="حذف"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-xl-4 order-1 order-xl-2">
            <div class="card">
                <div class="card-header"><h2 class="h5 text-success fw-bold mb-0"><i class="fa-solid fa-circle-plus ms-2"></i>تسجيل تحويل جديد</h2></div>
                <div class="card-body p-4">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save">
                        <div class="mb-3"><label class="field-label form-label">المبلغ بالجنيه المصري <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">EGP</span><input name="amount_egp" type="number" min="0.01" step="0.01" class="form-control" required></div></div>
                        <div class="mb-3"><label class="field-label form-label">تاريخ التحويل <span class="text-danger">*</span></label><input name="transfer_date" type="date" class="form-control" required></div>
                        <div class="mb-3"><label class="field-label form-label">المستلم أو الجهة <span class="text-muted fw-normal">(اختياري)</span></label><input name="recipient" type="text" maxlength="180" class="form-control" placeholder="مثال: البيت / مدرسة الأولاد"></div>
                        <div class="mb-3"><label class="field-label form-label">الغرض <span class="text-muted fw-normal">(اختياري)</span></label><input name="purpose" type="text" maxlength="255" class="form-control" placeholder="مثال: مصروفات شهر نوفمبر"></div>
                        <div class="mb-3"><label class="field-label form-label">إيصال التحويل <span class="text-muted fw-normal">(اختياري)</span></label><input name="receipt_file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic"><div class="form-text">PDF أو صورة، حتى 15 MB.</div></div>
                        <div class="mb-4"><label class="field-label form-label">ملاحظات <span class="text-muted fw-normal">(اختياري)</span></label><textarea name="notes" class="form-control" rows="3"></textarea></div>
                        <button class="btn btn-success btn-save w-100 fw-bold" type="submit"><i class="fa-solid fa-floppy-disk ms-2"></i>حفظ التحويل</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <div class="modal-header"><h2 class="modal-title h5 fw-bold text-success"><i class="fa-solid fa-pen-to-square ms-2"></i>تعديل تحويل</h2><button class="btn-close m-0 me-auto" type="button" data-bs-dismiss="modal"></button></div>
        <form method="post" enctype="multipart/form-data"><div class="modal-body p-4">
            <input type="hidden" name="action" value="update"><input type="hidden" name="edit_id" id="edit_id">
            <div class="mb-3"><label class="field-label form-label">المبلغ بالجنيه المصري</label><div class="input-group"><span class="input-group-text">EGP</span><input id="edit_amount_egp" name="edit_amount_egp" type="number" min="0.01" step="0.01" class="form-control" required></div></div>
            <div class="mb-3"><label class="field-label form-label">تاريخ التحويل</label><input id="edit_transfer_date" name="edit_transfer_date" type="date" class="form-control" required></div>
            <div class="mb-3"><label class="field-label form-label">المستلم أو الجهة</label><input id="edit_recipient" name="edit_recipient" type="text" maxlength="180" class="form-control"></div>
            <div class="mb-3"><label class="field-label form-label">الغرض</label><input id="edit_purpose" name="edit_purpose" type="text" maxlength="255" class="form-control"></div>
            <div class="mb-3"><label class="field-label form-label">استبدال الإيصال <span class="text-muted fw-normal">(اختياري)</span></label><input name="edit_receipt_file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic"><div class="form-text">اتركه فارغًا للاحتفاظ بالإيصال الحالي.</div></div>
            <div><label class="field-label form-label">ملاحظات</label><textarea id="edit_notes" name="edit_notes" class="form-control" rows="3"></textarea></div>
        </div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-success" type="submit">حفظ التعديل</button></div></form>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(id, amount, date, recipient, purpose, notes) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_amount_egp').value = amount;
    document.getElementById('edit_transfer_date').value = date;
    document.getElementById('edit_recipient').value = recipient || '';
    document.getElementById('edit_purpose').value = purpose || '';
    document.getElementById('edit_notes').value = notes || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
