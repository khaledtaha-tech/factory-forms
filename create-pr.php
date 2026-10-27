<?php
/**
 * ==============================================================================
 * Departmental PR Generator Engine
 * Electronic Purchase Requisition System - Al-Manar Plastic Factory
 * ==============================================================================
 * Dedicated portal for factory departments to submit purchase requests directly.
 * Auto-generates self-contained, print-perfect single-page A4 HTML forms in forms/
 * ==============================================================================
 */

$formsDir = __DIR__ . DIRECTORY_SEPARATOR . 'forms';

// Handle AJAX PR Generation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_pr') {
    header('Content-Type: application/json; charset=UTF-8');

    $dept = trim($_POST['department'] ?? '');
    $reqDate = trim($_POST['req_date'] ?? date('Y-m-d'));
    $neededDate = trim($_POST['needed_date'] ?? '');
    $requestedBy = trim($_POST['requested_by'] ?? '');
    $priority = trim($_POST['priority'] ?? 'normal');
    $justification = trim($_POST['justification'] ?? '');
    $itemsJson = $_POST['items'] ?? '[]';
    $rawItems = json_decode($itemsJson, true);

    if (empty($dept)) {
        echo json_encode(['success' => false, 'error' => 'يرجى اختيار القسم أو المصنع الطالب.']);
        exit;
    }

    if (!is_array($rawItems) || empty($rawItems)) {
        echo json_encode(['success' => false, 'error' => 'يرجى إضافة بند واحد على الأقل لطلب الشراء.']);
        exit;
    }

    // Filter valid items
    $validItems = [];
    foreach ($rawItems as $item) {
        $desc = trim($item['desc'] ?? '');
        if ($desc !== '') {
            $validItems[] = [
                'code'    => trim($item['code'] ?? ''),
                'desc'    => $desc,
                'unit'    => trim($item['unit'] ?? 'عدد'),
                'qty'     => trim($item['qty'] ?? '1'),
                'stock'   => trim($item['stock'] ?? ''),
                'remarks' => trim($item['remarks'] ?? '')
            ];
        }
    }

    if (empty($validItems)) {
        echo json_encode(['success' => false, 'error' => 'يرجى إدخال وصف الصنف لبند واحد على الأقل.']);
        exit;
    }

    // Determine year and next sequential PR number
    $year = date('Y', strtotime($reqDate ?: 'now'));
    if (!$year || strlen($year) !== 4) {
        $year = '2026';
    }

    $maxCounter = 42; // Base minimum counter
    if (is_dir($formsDir)) {
        $files = scandir($formsDir);
        if ($files !== false) {
            foreach ($files as $file) {
                if (preg_match('/^PR-(\d{4})-(\d{4})\.html$/i', $file, $matches)) {
                    $fileYear = $matches[1];
                    $fileCounter = (int)$matches[2];
                    if ($fileYear === $year && $fileCounter > $maxCounter) {
                        $maxCounter = $fileCounter;
                    }
                }
            }
        }
    }

    $nextCounter = $maxCounter + 1;
    $prNumber = sprintf("PR-%s-%04d", $year, $nextCounter);
    $targetFilename = $prNumber . '.html';
    $targetFilePath = $formsDir . DIRECTORY_SEPARATOR . $targetFilename;

    // Generate HTML from template function
    $htmlContent = buildPurchaseRequisitionHtml($prNumber, $dept, $reqDate, $neededDate, $requestedBy, $priority, $justification, $validItems);

    $writeResult = file_put_contents($targetFilePath, $htmlContent);
    if ($writeResult === false) {
        echo json_encode(['success' => false, 'error' => 'فشل حفظ الملف على الخادم. يرجى مراجعة الصلاحيات.']);
        exit;
    }

    echo json_encode([
        'success'     => true,
        'pr_number'   => $prNumber,
        'filename'    => $targetFilename,
        'url'         => 'forms/' . rawurlencode($targetFilename),
        'department'  => $dept,
        'date'        => $reqDate,
        'items_count' => count($validItems)
    ]);
    exit;
}

/**
 * Builds standalone, print-perfect single-page A4 Purchase Requisition HTML document
 */
function buildPurchaseRequisitionHtml($prNumber, $dept, $reqDate, $neededDate, $requestedBy, $priority, $justification, $items) {
    $titleEsc = htmlspecialchars("طلب شراء داخلي - {$prNumber} | مصنع المنار للبلاستيك", ENT_QUOTES, 'UTF-8');
    $prNumEsc = htmlspecialchars($prNumber, ENT_QUOTES, 'UTF-8');
    $deptEsc = htmlspecialchars($dept, ENT_QUOTES, 'UTF-8');
    $reqDateEsc = htmlspecialchars($reqDate, ENT_QUOTES, 'UTF-8');
    $neededDateEsc = htmlspecialchars($neededDate, ENT_QUOTES, 'UTF-8');
    $requestedByEsc = htmlspecialchars($requestedBy, ENT_QUOTES, 'UTF-8');
    $justificationEsc = htmlspecialchars($justification, ENT_QUOTES, 'UTF-8');

    $isNormal = ($priority === 'normal') ? 'checked="checked"' : '';
    $isUrgent = ($priority === 'urgent') ? 'checked="checked"' : '';
    $isEmergency = ($priority === 'emergency') ? 'checked="checked"' : '';
    if (!$isNormal && !$isUrgent && !$isEmergency) {
        $isNormal = 'checked="checked"';
    }

    // Build items table rows (pad up to 10 rows for clean presentation)
    $tableRowsHtml = '';
    $displayRowsCount = max(10, count($items));
    for ($i = 0; $i < $displayRowsCount; $i++) {
        $idx = $i + 1;
        $item = $items[$i] ?? ['code' => '', 'desc' => '', 'unit' => '', 'qty' => '', 'stock' => '', 'remarks' => ''];
        $code = htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8');
        $unit = htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8');
        $qty = htmlspecialchars($item['qty'], ENT_QUOTES, 'UTF-8');
        $stock = htmlspecialchars($item['stock'], ENT_QUOTES, 'UTF-8');
        $remarks = htmlspecialchars($item['remarks'], ENT_QUOTES, 'UTF-8');

        $tableRowsHtml .= <<<ROW
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="font-black text-slate-950 text-center bg-slate-50/70 row-index">{$idx}</td>
        <td>
          <input type="text" class="table-input font-mono text-center text-slate-950 font-bold item-code" value="{$code}">
        </td>
        <td class="text-right">
          <input type="text" class="table-input font-bold text-slate-950 item-desc" value="{$desc}">
        </td>
        <td>
          <input type="text" list="unitsList" class="table-input text-center font-bold text-slate-900 item-unit" value="{$unit}">
        </td>
        <td>
          <input type="text" class="table-input font-mono font-black text-center text-blue-900 item-qty" value="{$qty}">
        </td>
        <td>
          <input type="text" class="table-input font-mono text-center text-slate-800 font-bold item-stock" value="{$stock}">
        </td>
        <td>
          <input type="text" class="table-input text-right text-slate-700 font-semibold item-remarks" value="{$remarks}">
        </td>
        <td class="no-print action-col text-center">
          <button onclick="deleteThisRow(this)" title="حذف هذا الصف" class="text-slate-300 hover:text-rose-600 font-black text-base px-1 transition">&times;</button>
        </td>
      </tr>
ROW;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$titleEsc}</title>
  
  <!-- Fonts: Cairo & Inter for high legibility & sharp print -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Cairo', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .en-sub {
      font-family: 'Inter', sans-serif;
      font-size: 8.5px;
      line-height: 1.1;
      letter-spacing: 0.25px;
      font-weight: 700;
      color: #475569;
      text-transform: uppercase;
      display: block;
    }

    /* ==============================================================================
       PRINT ENGINE: A4 PORTRAIT SINGLE-PAGE FIT WITH FULL BORDERS & ZERO RTL CLIPPING
       ============================================================================== */
    @page {
      size: A4 portrait;
      margin: 6mm 5mm;
    }

    @media print {
      *, *::before, *::after {
        box-sizing: border-box !important;
      }

      html, body {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
        height: auto !important;
        overflow: visible !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* Outer Container must fit 100% inside printable area with visible 4 borders */
      .print-container, #print-area, body > div:first-child, .container, .requisition-sheet {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 auto !important;
        padding: 2mm !important;
        border: 1.5px solid #000 !important;
        box-sizing: border-box !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        page-break-after: avoid !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
      }

      .page-container {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        border: none !important;
      }

      /* Prevent CSS Grid tracks from expanding beyond 100% in RTL */
      .grid, .grid-cols-12, .grid-cols-4 {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }

      .grid > *,
      .grid-cols-12 > *,
      .grid-cols-4 > * {
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }

      /* Ensure table never overflows horizontally */
      table, .table-bordered {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        box-sizing: border-box !important;
      }

      th, td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        box-sizing: border-box !important;
        padding: 2px 3px !important;
        font-size: 10.5px !important;
        line-height: 1.2 !important;
        border-color: #000 !important;
      }

      .table-bordered tr {
        height: auto !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
      }

      .table-bordered th:nth-child(1), .table-bordered td:nth-child(1) { width: 5% !important; }
      .table-bordered th:nth-child(2), .table-bordered td:nth-child(2) { width: 14% !important; }
      .table-bordered th:nth-child(3), .table-bordered td:nth-child(3) { width: 35% !important; }
      .table-bordered th:nth-child(4), .table-bordered td:nth-child(4) { width: 8% !important; }
      .table-bordered th:nth-child(5), .table-bordered td:nth-child(5) { width: 11% !important; }
      .table-bordered th:nth-child(6), .table-bordered td:nth-child(6) { width: 11% !important; }
      .table-bordered th:nth-child(7), .table-bordered td:nth-child(7) { width: 16% !important; }
      .table-bordered th.action-col, .table-bordered td.action-col { display: none !important; width: 0 !important; }

      /* Vertical space optimization so the entire form fits on exactly ONE A4 page */
      .header-section { margin-bottom: 2px !important; padding-bottom: 1px !important; }
      .meta-grid-section { margin-top: 2px !important; margin-bottom: 2px !important; }
      .signature-section { margin-top: 3px !important; }
      .remarks-section { margin-top: 2px !important; padding: 2px !important; }
      .attachments-section { margin-top: 2px !important; padding: 1.5px 3px !important; }
      .footer-section { margin-top: 2px !important; padding-top: 1.5px !important; }

      .signature-section > div > div {
        height: auto !important;
        min-height: 18mm !important;
        max-height: 22mm !important;
        padding: 2px 4px !important;
        box-sizing: border-box !important;
      }

      #justification {
        height: 12mm !important;
        min-height: 10mm !important;
        padding: 2px !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
      }

      .table-input, .field-input, input, textarea, select {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
      }

      .table-input {
        font-size: 10.5px !important;
        padding: 1px 2px !important;
      }

      .quality-box {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        padding: 2px !important;
        font-size: 8px !important;
      }

      .quality-box * {
        font-size: 7.5px !important;
        line-height: 1.1 !important;
      }

      #attOtherText {
        max-width: 120px !important;
      }

      input[type="checkbox"], input[type="radio"] {
        appearance: none;
        -webkit-appearance: none;
        width: 12px;
        height: 12px;
        border: 1.2px solid #000 !important;
        border-radius: 2px;
        display: inline-grid;
        place-content: center;
      }

      input[type="checkbox"]:checked::before {
        content: "✓";
        font-size: 9px;
        font-weight: 900;
        color: #000;
      }

      input[type="radio"]:checked::before {
        content: "●";
        font-size: 8px;
        color: #000;
      }

      /* Hide all non-printable elements */
      .no-print, button, .action-bar, .action-col, #toast, #settingsModal {
        display: none !important;
      }
    }

    /* Screen and Sheet Styling */
    .table-bordered {
      border-collapse: collapse;
      width: 100%;
      table-layout: fixed;
    }
    .table-bordered th, .table-bordered td {
      border: 1.2px solid #0f172a;
      padding: 3.5px 5px;
      vertical-align: middle;
    }
    .table-bordered th {
      background-color: #f1f5f9;
      font-weight: 800;
      color: #0f172a;
    }
    .table-bordered tbody tr {
      height: 29px;
    }

    /* Field Inputs */
    .field-input {
      width: 100%;
      border-bottom: 1.5px dashed #94a3b8;
      padding: 1px 4px;
      font-size: 12.5px;
      font-weight: 700;
      color: #0f172a;
      outline: none;
      background: transparent;
      transition: all 0.2s ease;
    }
    .field-input:focus {
      border-bottom: 2px solid #2563eb;
      background: #f8fafc;
    }

    .table-input {
      width: 100%;
      border: none;
      outline: none;
      background: transparent;
      font-size: 12px;
      padding: 2px 3px;
      color: #0f172a;
    }
    .table-input:focus {
      background-color: #eff6ff;
      border-radius: 3px;
    }

    .custom-check {
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      user-select: none;
    }

    #toast {
      visibility: hidden;
      min-width: 280px;
      background-color: #0f172a;
      color: #fff;
      text-align: center;
      border-radius: 8px;
      padding: 12px 20px;
      position: fixed;
      z-index: 999;
      left: 50%;
      bottom: 25px;
      transform: translateX(-50%);
      font-size: 13px;
      font-weight: 700;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
      opacity: 0;
      transition: all 0.3s ease;
    }
    #toast.show {
      visibility: visible;
      opacity: 1;
      bottom: 35px;
    }
  </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900 antialiased p-2 md:p-5">

  <!-- ================= TOP TOOLBAR (Hidden in Print) ================= -->
  <header class="no-print max-w-[1240px] mx-auto mb-3 bg-white rounded-xl shadow-sm border border-slate-200 p-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
      
      <!-- Brand & Document Code -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-slate-950 flex items-center justify-center text-white shadow-inner">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
          </svg>
        </div>
        <div>
          <h1 class="text-base font-black text-slate-950 leading-tight">مصنع المنار للبلاستيك | نظام طلبات الشراء الذكي</h1>
          <p class="text-xs text-slate-500 flex items-center gap-2">
            <span>نظام الجودة المعتمد ISO 9001:2015</span>
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span class="font-mono text-slate-800 font-bold">AMP-PR-F01 Rev.00</span>
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2">
        <a href="../create-pr.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-lg text-xs font-bold flex items-center gap-1.5 transition">
          <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
          </svg>
          <span>بوابة تقديم طلب جديد</span>
        </a>

        <!-- Print / PDF Button -->
        <button onclick="window.print()" title="طباعة فورية في صفحة واحدة A4 دون انقسام" class="px-4 py-2 bg-slate-950 hover:bg-slate-800 text-white rounded-lg text-xs font-black flex items-center gap-1.5 shadow transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
          </svg>
          <span>طباعة / PDF (صفحة واحدة)</span>
        </button>

        <!-- Download HTML Standalone -->
        <button onclick="downloadSelfHTML()" title="تنزيل نسخة من هذه الصفحة ببياناتها" class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-sm transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
          </svg>
          <span>تنزيل ملف HTML</span>
        </button>
      </div>
    </div>
  </header>

  <!-- ================= MAIN DOCUMENT (A4 Purchase Requisition) ================= -->
  <main class="page-container max-w-[1240px] mx-auto overflow-x-auto pb-4">
    <div class="requisition-sheet print-container bg-white border-[2px] border-slate-950 rounded-sm shadow-xl p-4 md:p-5 mx-auto w-full max-w-[1200px] text-slate-950 transition-all">
      
      <!-- ================= 1. HEADER SECTION ================= -->
      <div class="header-section grid grid-cols-12 pb-2 border-b-[2px] border-slate-950 items-center gap-2">
        
        <!-- Document Title (Right Side) -->
        <div class="col-span-4 text-right">
          <h1 class="text-3xl font-black text-slate-950 tracking-tight leading-none mb-1">طلب شراء</h1>
          <h2 class="text-xs font-black tracking-widest text-slate-700 uppercase" style="font-family: 'Inter', sans-serif;">PURCHASE REQUISITION</h2>
          
          <div class="mt-1.5 inline-flex items-center gap-1.5 bg-slate-100 border border-slate-300 px-2.5 py-0.5 rounded text-[11px] font-mono">
            <span class="font-extrabold text-slate-950">PR No:</span>
            <span id="prSerialBadge" class="text-blue-700 font-black tracking-wider text-xs">{$prNumEsc}</span>
          </div>
        </div>

        <!-- Factory Name & Identity (Center) -->
        <div class="col-span-5 flex items-center justify-center gap-3 text-center">
          <div class="w-12 h-12 border-[1.5px] border-slate-950 rounded-lg flex items-center justify-center p-1 bg-white shadow-sm">
            <svg viewBox="0 0 48 48" class="w-10 h-10 text-slate-950" fill="currentColor">
              <path d="M4 40h40v4H4zM8 36V18l10-8v6l10-8v6l12-10v32H8zm6-14v4h4v-4h-4zm0 8v4h4v-4h-4zm10-8v4h4v-4h-4zm0 8v4h4v-4h-4zm10-14v4h4v-4h-4zm0 8v4h4v-4h-4zm0 8v4h4v-4h-4z"></path>
            </svg>
          </div>
          <div>
            <h2 id="factoryNameAr" class="text-2xl font-black text-slate-950 tracking-tight leading-none">مصنع المنار للبلاستيك</h2>
            <h3 id="factoryNameEn" class="text-xs font-black tracking-wider text-slate-700 uppercase mt-1" style="font-family: 'Inter', sans-serif;">AL MANAR PLASTIC FACTORY</h3>
            <span class="text-[9px] text-slate-600 block font-bold mt-0.5">قسم التوريدات والمشتريات المركزية | إدارة سلاسل الإمداد</span>
          </div>
        </div>

        <!-- ISO Document Control Box (Left Side) -->
        <div class="col-span-3 flex justify-end">
          <div class="quality-box border-[1.2px] border-slate-950 rounded bg-slate-50 text-[10px] w-full p-1.5 shadow-sm">
            <div class="flex justify-between items-center border-b border-slate-300 pb-1 mb-1 font-bold text-slate-950">
              <span class="flex items-center gap-1 font-black">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                نظام إدارة الجودة | ISO
              </span>
              <span class="text-[8.5px] bg-emerald-100 text-emerald-800 px-1 py-0.5 rounded font-black">9001:2015</span>
            </div>
            <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 text-slate-800 text-[9px] leading-tight">
              <div><span class="font-extrabold text-slate-950">كود الوثيقة:</span> <span class="font-mono font-bold text-slate-950">AMP-PR-F01</span></div>
              <div><span class="font-extrabold text-slate-950">تاريخ الإصدار:</span> <span class="font-mono font-bold">2026/10</span></div>
              <div><span class="font-extrabold text-slate-950">رقم الإصدار:</span> <span class="font-mono font-bold">01</span></div>
              <div><span class="font-extrabold text-slate-950">رقم التعديل:</span> <span class="font-mono font-bold">00</span></div>
            </div>
          </div>
        </div>

      </div>

      <!-- ================= 2. META DATA GRID ================= -->
      <div class="meta-grid-section mt-2 text-[12px]">
        <!-- Row 1: Requesting Department | PR No | Request Date | Required By -->
        <div class="grid grid-cols-12 gap-3 border-b border-slate-300 pb-2 items-end">
          
          <div class="col-span-4">
            <div class="flex items-center gap-2">
              <label class="whitespace-nowrap font-black text-slate-950 leading-tight">
                القسم الطالب
                <span class="en-sub">Requesting department</span>
              </label>
              <input type="text" id="reqDept" value="{$deptEsc}" class="field-input font-black text-blue-900">
            </div>
          </div>

          <div class="col-span-3">
            <div class="flex items-center gap-2">
              <label class="whitespace-nowrap font-black text-slate-950 leading-tight">
                رقم الطلب
                <span class="en-sub">PR No.</span>
              </label>
              <input type="text" id="prNoInput" value="{$prNumEsc}" class="field-input font-mono font-black text-rose-700 tracking-wider">
            </div>
          </div>

          <div class="col-span-3">
            <div class="flex items-center gap-2">
              <label class="whitespace-nowrap font-black text-slate-950 leading-tight">
                تاريخ الطلب
                <span class="en-sub">Request date</span>
              </label>
              <input type="date" id="reqDate" class="field-input font-mono font-black text-slate-900" value="{$reqDateEsc}">
            </div>
          </div>

          <div class="col-span-2">
            <div class="flex items-center gap-2">
              <label class="whitespace-nowrap font-black text-slate-950 leading-tight">
                تاريخ الاحتياج
                <span class="en-sub">Required by</span>
              </label>
              <input type="date" id="neededDate" class="field-input font-mono font-bold text-slate-900" value="{$neededDateEsc}">
            </div>
          </div>

        </div>

        <!-- Row 2: Requested by & Priority (مقدم الطلب والأولوية) -->
        <div class="grid grid-cols-12 gap-4 pt-2 pb-2 border-b-[1.8px] border-slate-950 items-center">
          
          <div class="col-span-7">
            <div class="flex items-center gap-2">
              <label class="whitespace-nowrap font-black text-slate-950 leading-tight text-[12.5px]">
                مقدم الطلب
                <span class="en-sub">Requested by</span>
              </label>
              <input type="text" id="requestedBy" class="field-input font-bold text-slate-900 text-[13px]" value="{$requestedByEsc}">
            </div>
          </div>

          <div class="col-span-5 flex items-center justify-end gap-3">
            <span class="font-black text-slate-950 whitespace-nowrap text-[12.5px]">
              الأولوية
              <span class="en-sub">Priority</span>
            </span>
            <div class="flex items-center gap-4 pr-1 text-[11.5px]">
              <label class="custom-check">
                <input type="radio" name="priority" value="normal" {$isNormal}>
                <span class="font-bold text-slate-800">عادي <span class="en-sub inline">Normal</span></span>
              </label>
              <label class="custom-check">
                <input type="radio" name="priority" value="urgent" {$isUrgent}>
                <span class="text-amber-800 font-black">عاجل <span class="en-sub inline">Urgent</span></span>
              </label>
              <label class="custom-check">
                <input type="radio" name="priority" value="emergency" {$isEmergency}>
                <span class="text-rose-700 font-black">طارئ <span class="en-sub inline">Emergency</span></span>
              </label>
            </div>
          </div>

        </div>
      </div>

      <!-- ================= 3. ITEMS TABLE ================= -->
      <div class="mt-1.5 flex-grow">
        <table class="table-bordered text-center text-[12px]" id="requisitionTable">
          <thead>
            <tr class="text-slate-950 font-black text-[12px]">
              <th style="width: 5%;" class="py-1">
                م
                <span class="en-sub">No.</span>
              </th>
              <th style="width: 14%;" class="py-1">
                كود الصنف
                <span class="en-sub">Item code</span>
              </th>
              <th style="width: 35%;" class="py-1 text-right pr-3">
                وصف الصنف والمواصفات الفنية
                <span class="en-sub">Item description &amp; technical specifications</span>
              </th>
              <th style="width: 8%;" class="py-1">
                الوحدة
                <span class="en-sub">Unit</span>
              </th>
              <th style="width: 11%;" class="py-1">
                الكمية المطلوبة
                <span class="en-sub">Qty required</span>
              </th>
              <th style="width: 11%;" class="py-1">
                رصيد المخزن
                <span class="en-sub">Stock on hand</span>
              </th>
              <th style="width: 16%;" class="py-1">
                ملاحظات
                <span class="en-sub">Remarks</span>
              </th>
              <th style="width: 4%;" class="py-1 no-print action-col">
                حذف
              </th>
            </tr>
          </thead>
          <tbody id="tableBody">
{$tableRowsHtml}
          </tbody>
        </table>
      </div>

      <!-- Quick Add Row & Table Actions (Hidden in Print) -->
      <div class="no-print mt-2 flex flex-wrap items-center justify-between gap-2 p-2 bg-slate-50 border border-slate-200 rounded-lg">
        <div class="flex items-center gap-2">
          <button onclick="addRow()" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-bold text-xs shadow-sm flex items-center gap-1.5 transition">
            <span class="text-sm font-black">+</span>
            <span>إضافة صف جديد</span>
          </button>
          
          <button onclick="removeLastRow()" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 rounded-md font-bold text-xs transition flex items-center gap-1">
            <span class="text-sm font-black">−</span>
            <span>حذف آخر صف</span>
          </button>
        </div>

        <div class="flex items-center gap-3 text-xs">
          <span class="text-slate-600 font-medium">عدد البنود الحالية: <strong id="rowCountDisplay" class="font-mono text-slate-950 bg-white px-2 py-0.5 rounded border border-slate-300">{$displayRowsCount}</strong></span>
          <span class="text-[11px] text-slate-400">| عند الطباعة يخرج الجدول متناسقاً بدقة A4</span>
        </div>
      </div>

      <!-- Units Auto-Complete Datalist -->
      <datalist id="unitsList">
        <option value="عدد"></option>
        <option value="حبة / Pc"></option>
        <option value="ربطة"></option>
        <option value="كرتون / Box"></option>
        <option value="لفة / Roll"></option>
        <option value="طقم / Set"></option>
        <option value="علبة"></option>
        <option value="كجم / Kg"></option>
        <option value="طن / Ton"></option>
        <option value="متر / Meter"></option>
        <option value="برميل / Drum"></option>
        <option value="لتر / Liter"></option>
        <option value="شيكارة / Bag"></option>
      </datalist>

      <!-- ================= 4. PURPOSE / JUSTIFICATION ================= -->
      <div class="remarks-section mt-2 border-[1.5px] border-slate-950 rounded-sm p-2 bg-slate-50/50">
        <div class="flex items-center gap-2 mb-1">
          <label class="font-black text-slate-950 text-[12px]">
            الغرض من الشراء / مبررات الطلب:
            <span class="en-sub inline font-bold">Purpose / Justification</span>
          </label>
        </div>
        <textarea id="justification" rows="2" class="w-full text-[12px] font-semibold p-1.5 bg-white border border-slate-300 rounded outline-none focus:border-blue-600 transition leading-relaxed">{$justificationEsc}</textarea>
      </div>

      <!-- ================= 5. ATTACHMENTS ================= -->
      <div class="attachments-section mt-1.5 flex flex-wrap items-center justify-between text-[11px] px-2 py-1.5 bg-white border-b border-slate-400">
        <div class="flex items-center gap-6">
          <span class="font-black text-slate-950">
            المرفقات:
            <span class="en-sub inline font-bold">Attachments</span>
          </span>
          <label class="custom-check">
            <input type="checkbox" id="attDrawing">
            <span class="font-bold">رسم أو عينة <span class="en-sub inline">Drawing or sample</span></span>
          </label>
          <label class="custom-check">
            <input type="checkbox" id="attQuotation" checked="checked">
            <span class="font-black text-slate-900">عرض سعر <span class="en-sub inline">Quotation</span></span>
          </label>
          <label class="custom-check">
            <input type="checkbox" id="attOther">
            <span class="font-bold">أخرى <span class="en-sub inline">Other:</span></span>
          </label>
          <input type="text" id="attOtherText" class="border-b border-dashed border-slate-400 text-[11px] font-bold px-1 outline-none w-52" value="">
        </div>

        <div class="text-[10px] text-slate-600 font-mono font-bold">
          FORM-ID: <span id="formIdTrack">AMP-PR-F01-{$prNumEsc}</span>
        </div>
      </div>

      <!-- ================= 6. OFFICIAL SIGNATURES BLOCK ================= -->
      <div class="signature-section mt-2 border-[1.5px] border-slate-950">
        <div class="grid grid-cols-4 divide-x divide-x-reverse divide-slate-900 text-center">
          
          <!-- 1. Store Verification -->
          <div class="p-2 flex flex-col justify-between h-28 bg-white">
            <div class="border-b border-slate-300 pb-1">
              <h4 class="font-black text-[12px] text-slate-950">مراجعة المخزن</h4>
              <span class="en-sub">Store verification</span>
            </div>
            <div class="text-right text-[11px] space-y-1.5">
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">الاسم / Name:</span>
                <input type="text" id="storeName" class="table-input font-bold text-[11px]" value="">
              </div>
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">التوقيع / Sign:</span>
                <span class="text-slate-400">........................</span>
              </div>
            </div>
          </div>

          <!-- 2. Production Manager -->
          <div class="p-2 flex flex-col justify-between h-28 bg-white">
            <div class="border-b border-slate-300 pb-1">
              <h4 class="font-black text-[12px] text-slate-950">مدير الإنتاج</h4>
              <span class="en-sub">Production Manager</span>
            </div>
            <div class="text-right text-[11px] space-y-1.5">
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">الاسم / Name:</span>
                <input type="text" id="pmName" class="table-input font-bold text-[11px]" value="">
              </div>
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">التوقيع / Sign:</span>
                <span class="text-slate-400">........................</span>
              </div>
            </div>
          </div>

          <!-- 3. Purchasing Department -->
          <div class="p-2 flex flex-col justify-between h-28 bg-white">
            <div class="border-b border-slate-300 pb-1">
              <h4 class="font-black text-[12px] text-slate-950">إدارة المشتريات</h4>
              <span class="en-sub">Purchasing department</span>
            </div>
            <div class="text-right text-[11px] space-y-1.5">
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">الاسم / Name:</span>
                <input type="text" id="purchName" class="table-input font-bold text-[11px]" value="">
              </div>
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">التوقيع / Sign:</span>
                <span class="text-slate-400">........................</span>
              </div>
            </div>
          </div>

          <!-- 4. Management Approval -->
          <div class="p-2 flex flex-col justify-between h-28 bg-slate-50/70">
            <div class="border-b border-slate-300 pb-1">
              <h4 class="font-black text-[12px] text-slate-950">اعتماد الإدارة</h4>
              <span class="en-sub">Management approval</span>
            </div>
            <div class="text-right text-[11px] space-y-1.5">
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">الاسم / Name:</span>
                <input type="text" id="mgmtName" class="table-input font-bold text-[11px]" value="">
              </div>
              <div class="flex items-center gap-1">
                <span class="font-bold text-slate-800">التوقيع / Sign:</span>
                <span class="text-slate-400">........................</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- ================= 7. OFFICIAL FOOTER ================= -->
      <div class="footer-section mt-2 pt-2 border-t border-slate-400 flex items-center justify-between text-[10px] text-slate-700 font-bold">
        <div class="flex items-center gap-2">
          <span class="font-black text-slate-950 tracking-wider">PRODUCTION | PURCHASE REQUISITION</span>
          <span class="text-slate-400">|</span>
          <span class="text-slate-600 font-mono">ISO 9001:2015 CERTIFIED DOC</span>
        </div>
        <div class="text-center font-black text-slate-950">
          * طلب داخلي للشراء - لا يعد أمر شراء للمورد (Internal Requisition Only) *
        </div>
        <div>
          صفحة 1 من 1 | نظام إدارة الجودة المعتمد
        </div>
      </div>

    </div>
  </main>

  <!-- Toast Notification -->
  <div id="toast"></div>

  <script>
    function addRow() {
      const tbody = document.getElementById('tableBody');
      const count = tbody.children.length + 1;
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-slate-50 transition-colors';
      tr.innerHTML = `
        <td class="font-black text-slate-950 text-center bg-slate-50/70 row-index">\${count}</td>
        <td><input type="text" class="table-input font-mono text-center text-slate-950 font-bold item-code" value=""></td>
        <td class="text-right"><input type="text" class="table-input font-bold text-slate-950 item-desc" value=""></td>
        <td><input type="text" list="unitsList" class="table-input text-center font-bold text-slate-900 item-unit" value="عدد"></td>
        <td><input type="text" class="table-input font-mono font-black text-center text-blue-900 item-qty" value="1"></td>
        <td><input type="text" class="table-input font-mono text-center text-slate-800 font-bold item-stock" value=""></td>
        <td><input type="text" class="table-input text-right text-slate-700 font-semibold item-remarks" value=""></td>
        <td class="no-print action-col text-center">
          <button onclick="deleteThisRow(this)" title="حذف هذا الصف" class="text-slate-300 hover:text-rose-600 font-black text-base px-1 transition">&times;</button>
        </td>
      `;
      tbody.appendChild(tr);
      updateRowCount();
      showToast('تمت إضافة صف جديد');
    }

    function removeLastRow() {
      const tbody = document.getElementById('tableBody');
      if (tbody.children.length > 1) {
        tbody.removeChild(tbody.lastChild);
        updateRowCount();
        showToast('تم حذف آخر صف');
      }
    }

    function deleteThisRow(btn) {
      const tbody = document.getElementById('tableBody');
      if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        renumberRows();
        updateRowCount();
        showToast('تم حذف الصف');
      }
    }

    function renumberRows() {
      const tbody = document.getElementById('tableBody');
      Array.from(tbody.children).forEach((tr, idx) => {
        const indexCell = tr.querySelector('.row-index');
        if (indexCell) indexCell.innerText = idx + 1;
      });
    }

    function updateRowCount() {
      const countEl = document.getElementById('rowCountDisplay');
      const tbody = document.getElementById('tableBody');
      if (countEl && tbody) {
        countEl.innerText = tbody.children.length;
      }
    }

    function downloadSelfHTML() {
      document.querySelectorAll('input').forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
          if (input.checked) input.setAttribute('checked', 'checked');
          else input.removeAttribute('checked');
        } else {
          input.setAttribute('value', input.value);
        }
      });

      document.querySelectorAll('textarea').forEach(ta => {
        ta.textContent = ta.value;
      });

      const currentSerial = document.getElementById('prNoInput').value.trim() || 'PR-2026';
      const filename = `طلب_شراء_\${currentSerial}.html`;
      const htmlContent = "<!DOCTYPE html>\\n" + document.documentElement.outerHTML;

      const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      showToast(`📥 تم تنزيل الملف كاملاً: \${filename}`);
    }

    function showToast(message) {
      const toast = document.getElementById('toast');
      if (toast) {
        toast.innerText = message;
        toast.className = 'show';
        setTimeout(() => {
          toast.className = toast.className.replace('show', '');
        }, 3000);
      }
    }
  </script>
</body>
</html>
HTML;
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة تقديم طلبات الشراء للأقسام | مصنع المنار للبلاستيك</title>
    
    <!-- Google Fonts: Tajawal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex flex-col justify-between selection:bg-emerald-500 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="bg-white/90 backdrop-blur-md sticky top-0 z-40 border-b border-slate-200 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand & Factory Logo -->
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">بوابة طلبات الشراء للأقسام</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                توليد مباشر
                            </span>
                        </div>
                        <p class="text-xs font-medium text-slate-500">مصنع المنار للبلاستيك | نظام إصدار طلبات الشراء الداخلية (PR)</p>
                    </div>
                </div>

                <!-- Central Dashboard Shortcut -->
                <div class="flex items-center gap-2.5">
                    <a href="index.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span>لوحة التحكم الرئيسية (إدارة)</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <!-- Welcome / Instructions Card -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 p-6 sm:p-8 text-white shadow-xl shadow-slate-900/10 mb-8">
            <div class="absolute -left-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10 grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                <div class="md:col-span-8 space-y-2.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-emerald-300 border border-white/10">
                        ⚡ إصدار فوري لطلبات الشراء
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                        تقديم طلب شراء داخلي رسمي جديد (Purchase Requisition)
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-normal">
                        قم بتحديد قسمك وتعبئة الأصناف المطلوبة. سيقوم النظام بحجز رقم تسلسلي تلقائي وتوليد مستند رسمي معتمد جاهز للطباعة الفورية على ورقة A4 أو حفظه بصيغة PDF.
                    </p>
                </div>
                <div class="md:col-span-4 flex flex-col items-center md:items-end justify-center">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/15 text-center w-full max-w-xs">
                        <span class="text-xs text-slate-300 block mb-1">تاريخ اليوم المعتمد:</span>
                        <span class="font-mono text-base font-extrabold text-emerald-300"><?= date('d / m / Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 mb-10">
            <form id="prForm" onsubmit="handlePrSubmit(event)">
                
                <!-- Section 1: Department & Priority -->
                <div class="border-b border-slate-100 pb-6 mb-6">
                    <h3 class="text-sm font-black text-slate-900 mb-4 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-extrabold">1</span>
                        <span>بيانات القسم وأولوية الطلب</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <!-- Department Dropdown -->
                        <div class="md:col-span-6">
                            <label for="deptSelect" class="block text-xs font-bold text-slate-700 mb-1.5">
                                القسم أو المصنع الطالب <span class="text-rose-600">*</span>
                            </label>
                            <select id="deptSelect" name="department" required class="w-full py-2.5 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                                <option value="" disabled selected>-- اختر القسم أو المصنع من القائمة --</option>
                                <option value="مصنع المواسير (Pipes Factory)">مصنع المواسير (Pipes Factory)</option>
                                <option value="مصنع الحقن (Injection Factory)">مصنع الحقن (Injection Factory)</option>
                                <option value="مصنع HDPE (HDPE Factory)">مصنع HDPE (HDPE Factory)</option>
                                <option value="إدارة الصيانة (Maintenance Dept)">إدارة الصيانة (Maintenance Dept)</option>
                                <option value="إدارة الجودة (Quality Control)">إدارة الجودة (Quality Control)</option>
                            </select>
                        </div>

                        <!-- Requisitioner Name -->
                        <div class="md:col-span-6">
                            <label for="requestedByInput" class="block text-xs font-bold text-slate-700 mb-1.5">
                                اسم مقدم الطلب (المسؤول) <span class="text-slate-400 font-normal">(اختياري)</span>
                            </label>
                            <input type="text" id="requestedByInput" name="requested_by" placeholder="مثال: م. أحمد عبد العزيز / فني التشغيل" class="w-full py-2.5 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                        </div>

                        <!-- Request Date -->
                        <div class="md:col-span-4">
                            <label for="reqDateInput" class="block text-xs font-bold text-slate-700 mb-1.5">
                                تاريخ الطلب
                            </label>
                            <input type="date" id="reqDateInput" name="req_date" value="<?= $today ?>" class="w-full py-2.5 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                        </div>

                        <!-- Needed Date -->
                        <div class="md:col-span-4">
                            <label for="neededDateInput" class="block text-xs font-bold text-slate-700 mb-1.5">
                                تاريخ الاحتياج المطلوب
                            </label>
                            <input type="date" id="neededDateInput" name="needed_date" class="w-full py-2.5 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                        </div>

                        <!-- Priority Radios -->
                        <div class="md:col-span-4">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                درجة الأولوية
                            </label>
                            <div class="flex items-center gap-4 py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="priority" value="normal" checked class="text-emerald-600 focus:ring-emerald-500">
                                    <span class="font-bold text-slate-700">عادي</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="priority" value="urgent" class="text-amber-600 focus:ring-amber-500">
                                    <span class="font-bold text-amber-700">عاجل</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="priority" value="emergency" class="text-rose-600 focus:ring-rose-500">
                                    <span class="font-bold text-rose-700">طارئ</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Items Table -->
                <div class="border-b border-slate-100 pb-6 mb-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-extrabold">2</span>
                            <span>جدول الأصناف والبنود المطلوبة</span>
                            <span id="itemsBadgeCount" class="text-xs bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-full font-mono font-bold">1 بند</span>
                        </h3>

                        <button type="button" onclick="addNewItemRow()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-xs active:scale-[0.98]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>+ إضافة صنف جديد</span>
                        </button>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-x-auto border border-slate-200 rounded-2xl shadow-2xs bg-white">
                        <table class="w-full text-xs text-right border-collapse" id="itemsInputTable">
                            <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="p-2.5 text-center w-12">م</th>
                                    <th class="p-2.5 w-32">كود الصنف</th>
                                    <th class="p-2.5 min-w-[220px]">وصف الصنف والمواصفات الفنية <span class="text-rose-600">*</span></th>
                                    <th class="p-2.5 w-32">الوحدة</th>
                                    <th class="p-2.5 w-24">الكمية <span class="text-rose-600">*</span></th>
                                    <th class="p-2.5 w-28">رصيد المخزن</th>
                                    <th class="p-2.5 min-w-[150px]">ملاحظات</th>
                                    <th class="p-2.5 text-center w-12">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody" class="divide-y divide-slate-100 font-medium">
                                <!-- Initial Item Row -->
                                <tr class="item-row hover:bg-slate-50/70 transition">
                                    <td class="p-2.5 text-center font-bold text-slate-500 row-num">1</td>
                                    <td class="p-2">
                                        <input type="text" placeholder="كود الصنف" class="row-code w-full p-2 rounded-lg border border-slate-200 font-mono text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" required placeholder="اكتب اسم الصنف ومواصفاته الفنية..." class="row-desc w-full p-2 rounded-lg border border-slate-200 font-semibold text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" list="formUnitsList" value="عدد" class="row-unit w-full p-2 rounded-lg border border-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" required value="1" class="row-qty w-full p-2 rounded-lg border border-slate-200 font-mono font-bold text-center text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" placeholder="رصيد المخزن" class="row-stock w-full p-2 rounded-lg border border-slate-200 text-center text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" placeholder="ملاحظات..." class="row-remarks w-full p-2 rounded-lg border border-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                                    </td>
                                    <td class="p-2 text-center">
                                        <button type="button" onclick="deleteItemRow(this)" title="حذف هذا الصنف" class="text-slate-300 hover:text-rose-600 font-black text-lg p-1 transition">&times;</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 3: Justification / Purpose -->
                <div class="mb-8">
                    <h3 class="text-sm font-black text-slate-900 mb-2 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-extrabold">3</span>
                        <span>الغرض من الشراء والمبررات التشغيلية</span>
                    </h3>
                    <textarea id="justificationInput" name="justification" rows="3" placeholder="وضح الغرض من الطلب، خط الإنتاج أو الماكينة المستهدفة، أو أسباب الاحتياج العاجل..." class="w-full p-3 rounded-2xl border border-slate-200 bg-slate-50 text-slate-800 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition leading-relaxed"></textarea>
                </div>

                <!-- Submit Button Bar -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
                    <p class="text-xs text-slate-400">
                        * سيتم حفظ الطلب فوراً في مجلد النماذج وتوليد نسخة HTML معتمدة برقم تسلسلي جديد.
                    </p>
                    <button type="submit" id="submitBtn" class="inline-flex items-center justify-center gap-2 py-3.5 px-8 rounded-2xl text-xs font-extrabold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-lg shadow-emerald-600/25 transition active:scale-[0.98]">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>🚀 توليد وحفظ طلب الشراء الرسمي (Generate PR)</span>
                    </button>
                </div>

            </form>
        </div>

        <!-- Section 4: Local Device History (LocalStorage Isolation) -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>سجل طلبات هذا الجهاز (Local Device History)</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        يوثق كافة الطلبات الصادرة من هذا الجهاز فقط عبر التخزين المحلي الآمن، لتمكينك من متابعة وإعادة طباعة طلبات قسمك بسهولة.
                    </p>
                </div>

                <button type="button" onclick="clearDeviceHistory()" class="text-xs text-slate-400 hover:text-rose-600 font-bold transition">
                    مسح السجل المحلي
                </button>
            </div>

            <!-- History Table Container -->
            <div id="historyTableContainer" class="overflow-x-auto">
                <!-- Dynamically populated via JS -->
            </div>
        </div>

    </main>

    <!-- Global Units Datalist for Form -->
    <datalist id="formUnitsList">
        <option value="عدد"></option>
        <option value="حبة / Pc"></option>
        <option value="ربطة"></option>
        <option value="كرتون / Box"></option>
        <option value="لفة / Roll"></option>
        <option value="طقم / Set"></option>
        <option value="علبة"></option>
        <option value="كجم / Kg"></option>
        <option value="طن / Ton"></option>
        <option value="متر / Meter"></option>
        <option value="برميل / Drum"></option>
        <option value="لتر / Liter"></option>
        <option value="شيكارة / Bag"></option>
    </datalist>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-all">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 text-center border border-slate-100 transform transition-all">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h3 class="text-xl font-black text-slate-900 mb-1">تم إصدار طلب الشراء بنجاح!</h3>
            <p class="text-xs text-slate-500 mb-4">تم حفظ النموذج رسمياً في أرشيف المصنع بالرقم التسلسلي التالي:</p>

            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl py-3 px-4 mb-6">
                <span class="block text-2xl font-black font-mono text-emerald-700 tracking-wider" id="modalPrNumber">PR-2026-0043</span>
                <span class="text-xs text-emerald-800 font-bold block mt-1" id="modalDeptName">مصنع المواسير</span>
            </div>

            <!-- Actions -->
            <div class="space-y-2.5">
                <a id="modalOpenBtn" href="#" target="_blank" class="w-full py-3 px-4 rounded-xl text-xs font-black text-white bg-emerald-600 hover:bg-emerald-700 flex items-center justify-center gap-2 shadow-sm transition active:scale-[0.98]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    <span>معاينة وطباعة النموذج (Print / PDF)</span>
                </a>

                <button type="button" onclick="copyModalLink()" class="w-full py-2.5 px-4 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 flex items-center justify-center gap-2 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    <span id="modalCopyBtnText">نسخ الرابط المباشر للمستند</span>
                </button>

                <button type="button" onclick="closeSuccessModal()" class="w-full py-2.5 px-4 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                    إغلاق والعودة لإنشاء طلب جديد
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200/90 py-6 mt-12 text-center text-xs text-slate-500">
        <p>مصنع المنار للبلاستيك • نظام إدارة النماذج والوثائق الإلكترونية © <?= date('Y') ?></p>
    </footer>

    <!-- Scripts -->
    <script>
        const STORAGE_KEY = 'almanar_device_pr_history';
        let latestGeneratedUrl = '';

        document.addEventListener('DOMContentLoaded', () => {
            renderDeviceHistory();
        });

        function addNewItemRow() {
            const tbody = document.getElementById('itemsTableBody');
            const rowCount = tbody.children.length + 1;
            const tr = document.createElement('tr');
            tr.className = 'item-row hover:bg-slate-50/70 transition';
            tr.innerHTML = `
                <td class="p-2.5 text-center font-bold text-slate-500 row-num">\${rowCount}</td>
                <td class="p-2">
                    <input type="text" placeholder="كود الصنف" class="row-code w-full p-2 rounded-lg border border-slate-200 font-mono text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </td>
                <td class="p-2">
                    <input type="text" required placeholder="اكتب اسم الصنف ومواصفاته الفنية..." class="row-desc w-full p-2 rounded-lg border border-slate-200 font-semibold text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </td>
                <td class="p-2">
                    <input type="text" list="formUnitsList" value="عدد" class="row-unit w-full p-2 rounded-lg border border-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </td>
                <td class="p-2">
                    <input type="text" required value="1" class="row-qty w-full p-2 rounded-lg border border-slate-200 font-mono font-bold text-center text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </td>
                <td class="p-2">
                    <input type="text" placeholder="رصيد المخزن" class="row-stock w-full p-2 rounded-lg border border-slate-200 text-center text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </td>
                <td class="p-2">
                    <input type="text" placeholder="ملاحظات..." class="row-remarks w-full p-2 rounded-lg border border-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 outline-none">
                </td>
                <td class="p-2 text-center">
                    <button type="button" onclick="deleteItemRow(this)" title="حذف هذا الصنف" class="text-slate-300 hover:text-rose-600 font-black text-lg p-1 transition">&times;</button>
                </td>
            `;
            tbody.appendChild(tr);
            renumberItems();
        }

        function deleteItemRow(btn) {
            const tbody = document.getElementById('itemsTableBody');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
                renumberItems();
            } else {
                alert('يجب الإبقاء على بند واحد على الأقل في طلب الشراء.');
            }
        }

        function renumberItems() {
            const rows = document.querySelectorAll('#itemsTableBody tr');
            rows.forEach((row, idx) => {
                const numCell = row.querySelector('.row-num');
                if (numCell) numCell.innerText = idx + 1;
            });
            const badge = document.getElementById('itemsBadgeCount');
            if (badge) badge.innerText = rows.length + ' بند';
        }

        async function handlePrSubmit(event) {
            event.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const originalBtnHtml = submitBtn.innerHTML;

            // Collect items
            const rows = document.querySelectorAll('#itemsTableBody tr');
            const items = [];
            rows.forEach(r => {
                const desc = (r.querySelector('.row-desc')?.value || '').trim();
                if (desc) {
                    items.push({
                        code: (r.querySelector('.row-code')?.value || '').trim(),
                        desc: desc,
                        unit: (r.querySelector('.row-unit')?.value || '').trim(),
                        qty: (r.querySelector('.row-qty')?.value || '').trim(),
                        stock: (r.querySelector('.row-stock')?.value || '').trim(),
                        remarks: (r.querySelector('.row-remarks')?.value || '').trim()
                    });
                }
            });

            if (items.length === 0) {
                alert('يرجى إدخال وصف الصنف لبند واحد على الأقل.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'generate_pr');
            formData.append('department', document.getElementById('deptSelect').value);
            formData.append('requested_by', document.getElementById('requestedByInput').value);
            formData.append('req_date', document.getElementById('reqDateInput').value);
            formData.append('needed_date', document.getElementById('neededDateInput').value);
            formData.append('priority', document.querySelector('input[name="priority"]:checked')?.value || 'normal');
            formData.append('justification', document.getElementById('justificationInput').value);
            formData.append('items', JSON.stringify(items));

            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>جاري إصدار وتوليد النموذج...</span>';

                const response = await fetch('create-pr.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!data.success) {
                    alert('خطأ: ' + (data.error || 'فشلت معالجة الطلب'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                    return;
                }

                // Save to LocalStorage History
                saveToDeviceHistory({
                    prNumber: data.pr_number,
                    date: data.date,
                    department: data.department,
                    itemsCount: data.items_count,
                    url: data.url,
                    timestamp: new Date().toISOString()
                });

                // Show Success Modal
                latestGeneratedUrl = data.url;
                document.getElementById('modalPrNumber').innerText = data.pr_number;
                document.getElementById('modalDeptName').innerText = data.department;
                document.getElementById('modalOpenBtn').href = data.url;
                document.getElementById('successModal').classList.remove('hidden');

                // Reset form inputs except department
                document.getElementById('requestedByInput').value = '';
                document.getElementById('neededDateInput').value = '';
                document.getElementById('justificationInput').value = '';
                resetItemsTable();

            } catch (err) {
                console.error(err);
                alert('حدث خطأ في الاتصال بالخادم. يرجى إعادة المحاولة.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }

        function resetItemsTable() {
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = `
                <tr class="item-row hover:bg-slate-50/70 transition">
                    <td class="p-2.5 text-center font-bold text-slate-500 row-num">1</td>
                    <td class="p-2"><input type="text" placeholder="كود الصنف" class="row-code w-full p-2 rounded-lg border border-slate-200 font-mono text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></td>
                    <td class="p-2"><input type="text" required placeholder="اكتب اسم الصنف ومواصفاته الفنية..." class="row-desc w-full p-2 rounded-lg border border-slate-200 font-semibold text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></td>
                    <td class="p-2"><input type="text" list="formUnitsList" value="عدد" class="row-unit w-full p-2 rounded-lg border border-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></td>
                    <td class="p-2"><input type="text" required value="1" class="row-qty w-full p-2 rounded-lg border border-slate-200 font-mono font-bold text-center text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></td>
                    <td class="p-2"><input type="text" placeholder="رصيد المخزن" class="row-stock w-full p-2 rounded-lg border border-slate-200 text-center text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></td>
                    <td class="p-2"><input type="text" placeholder="ملاحظات..." class="row-remarks w-full p-2 rounded-lg border border-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 outline-none"></td>
                    <td class="p-2 text-center"><button type="button" onclick="deleteItemRow(this)" title="حذف هذا الصنف" class="text-slate-300 hover:text-rose-600 font-black text-lg p-1 transition">&times;</button></td>
                </tr>
            `;
            renumberItems();
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }

        function copyModalLink() {
            if (!latestGeneratedUrl) return;
            const fullUrl = new URL(latestGeneratedUrl, window.location.href).href;
            const copyBtnText = document.getElementById('modalCopyBtnText');
            navigator.clipboard.writeText(fullUrl).then(() => {
                copyBtnText.innerText = '✓ تم نسخ الرابط بنجاح!';
                setTimeout(() => {
                    copyBtnText.innerText = 'نسخ الرابط المباشر للمستند';
                }, 2500);
            });
        }

        // --- LOCAL STORAGE HISTORY FUNCTIONS ---
        function getDeviceHistory() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            } catch (e) {
                return [];
            }
        }

        function saveToDeviceHistory(item) {
            const list = getDeviceHistory();
            list.unshift(item);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
            renderDeviceHistory();
        }

        function clearDeviceHistory() {
            if (confirm('هل أنت متأكد من رغبتك في مسح سجل الطلبات المحفوظة على هذا الجهاز فقط؟')) {
                localStorage.removeItem(STORAGE_KEY);
                renderDeviceHistory();
            }
        }

        function renderDeviceHistory() {
            const container = document.getElementById('historyTableContainer');
            const history = getDeviceHistory();

            if (!history || history.length === 0) {
                container.innerHTML = `
                    <div class="py-8 text-center text-slate-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-xs font-semibold">لم يتم إصدار أي طلبات شراء من هذا الجهاز بعد.</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">ستظهر هنا كافة الطلبات التي تنشئها من هذا المتصفح تلقائياً.</p>
                    </div>
                `;
                return;
            }

            let rows = '';
            history.forEach(item => {
                rows += `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-3 font-mono font-bold text-slate-900">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono font-black bg-blue-50 text-blue-800 border border-blue-200">
                                \${item.prNumber}
                            </span>
                        </td>
                        <td class="p-3 font-medium text-slate-700 text-xs">\${item.department}</td>
                        <td class="p-3 font-mono text-slate-600 text-xs">\${item.date}</td>
                        <td class="p-3 text-center font-bold text-slate-700 text-xs">\${item.itemsCount} صنف</td>
                        <td class="p-3 text-center">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="\${item.url}" target="_blank" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] transition shadow-2xs">
                                    معاينة / طباعة
                                </a>
                                <button type="button" onclick="copySpecificUrl('\${item.url}')" class="px-2 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 text-[11px] font-bold transition" title="نسخ الرابط">
                                    نسخ
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            container.innerHTML = `
                <table class="w-full text-right text-xs border border-slate-200 rounded-xl overflow-hidden">
                    <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                        <tr>
                            <th class="p-3">رقم الطلب (PR)</th>
                            <th class="p-3">القسم / المصنع</th>
                            <th class="p-3">تاريخ الطلب</th>
                            <th class="p-3 text-center">عدد البنود</th>
                            <th class="p-3 text-center">إجراءات المستند</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        \${rows}
                    </tbody>
                </table>
            `;
        }

        function copySpecificUrl(url) {
            const fullUrl = new URL(url, window.location.href).href;
            navigator.clipboard.writeText(fullUrl).then(() => {
                alert('تم نسخ الرابط المباشر للطلب بنجاح!');
            });
        }
    </script>
</body>
</html>