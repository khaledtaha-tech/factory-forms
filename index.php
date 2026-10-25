<?php
/**
 * ==============================================================================
 * بوابة النماذج الإلكترونية - مصنع المنار للبلاستيك
 * Electronic Forms Portal - Al-Manar Plastic Factory
 * ==============================================================================
 * فحص ديناميكي تلقائي لكافة ملفات النماذج (.html و .php) داخل مجلد forms/
 * مجهزة للعمل على خوادم الاستضافة (Hostinger / Apache / LiteSpeed)
 * ==============================================================================
 */

// إعدادات المسارات
$formsDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'forms';
$webFormsPath   = 'forms/';

// دوال المساعدة وتنسيق التواريخ والأحجام
function formatArabicDate($timestamp) {
    $months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    $day   = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)] ?? date('m', $timestamp);
    $year  = date('Y', $timestamp);
    $time  = date('h:i', $timestamp);
    $ampm  = date('A', $timestamp) === 'AM' ? 'صباحاً' : 'مساءً';
    return "{$day} {$month} {$year} - {$time} {$ampm}";
}

function formatFileSizeHuman($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' م.ب';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' ك.ب';
    } else {
        return $bytes . ' بايت';
    }
}

function getCategoryMeta($code, $title, $filename) {
    $haystack = mb_strtolower($code . ' ' . $title . ' ' . $filename, 'UTF-8');

    if (str_contains($haystack, 'pr-') || str_contains($haystack, 'شراء') || str_contains($haystack, 'purchase') || str_contains($haystack, 'requisition')) {
        return [
            'name'       => 'المشتريات والطلبات',
            'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-500/20',
            'dotClass'   => 'bg-emerald-500',
            'iconColor'  => 'text-emerald-600',
            'gradient'   => 'from-emerald-500 to-teal-600',
            'btnClass'   => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500'
        ];
    } elseif (str_contains($haystack, 'hr-') || str_contains($haystack, 'رواتب') || str_contains($haystack, 'حضور') || str_contains($haystack, 'salary') || str_contains($haystack, 'attendance') || str_contains($haystack, 'موظف')) {
        return [
            'name'       => 'الموارد البشرية وشؤون الموظفين',
            'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200 ring-blue-500/20',
            'dotClass'   => 'bg-blue-500',
            'iconColor'  => 'text-blue-600',
            'gradient'   => 'from-blue-600 to-indigo-600',
            'btnClass'   => 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
        ];
    } elseif (str_contains($haystack, 'qc-') || str_contains($haystack, 'جودة') || str_contains($haystack, 'quality') || str_contains($haystack, 'فحص')) {
        return [
            'name'       => 'مراقبة وتأكيد الجودة',
            'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200 ring-purple-500/20',
            'dotClass'   => 'bg-purple-500',
            'iconColor'  => 'text-purple-600',
            'gradient'   => 'from-purple-600 to-indigo-600',
            'btnClass'   => 'bg-purple-600 hover:bg-purple-700 focus:ring-purple-500'
        ];
    } elseif (str_contains($haystack, 'prd-') || str_contains($haystack, 'إنتاج') || str_contains($haystack, 'تشغيل') || str_contains($haystack, 'production')) {
        return [
            'name'       => 'الإنتاج والتشغيل الفني',
            'badgeClass' => 'bg-amber-50 text-amber-800 border-amber-200 ring-amber-500/20',
            'dotClass'   => 'bg-amber-500',
            'iconColor'  => 'text-amber-600',
            'gradient'   => 'from-amber-500 to-orange-600',
            'btnClass'   => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500'
        ];
    } elseif (str_contains($haystack, 'wh-') || str_contains($haystack, 'مستودع') || str_contains($haystack, 'مخازن') || str_contains($haystack, 'inventory')) {
        return [
            'name'       => 'المستودعات والمخزون',
            'badgeClass' => 'bg-cyan-50 text-cyan-800 border-cyan-200 ring-cyan-500/20',
            'dotClass'   => 'bg-cyan-500',
            'iconColor'  => 'text-cyan-600',
            'gradient'   => 'from-cyan-600 to-teal-600',
            'btnClass'   => 'bg-cyan-600 hover:bg-cyan-700 focus:ring-cyan-500'
        ];
    }

    return [
        'name'       => 'نماذج عامة وإدارية',
        'badgeClass' => 'bg-slate-50 text-slate-700 border-slate-200 ring-slate-500/20',
        'dotClass'   => 'bg-slate-400',
        'iconColor'  => 'text-slate-600',
        'gradient'   => 'from-slate-700 to-slate-900',
        'btnClass'   => 'bg-slate-800 hover:bg-slate-900 focus:ring-slate-500'
    ];
}

// مسح المجلد واستخراج البيانات تلقائياً
$forms = [];
$totalSize = 0;
$categoriesCount = [];

if (is_dir($formsDirectory)) {
    $scannedEntries = scandir($formsDirectory);
    if ($scannedEntries !== false) {
        foreach ($scannedEntries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $formsDirectory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($fullPath)) {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, ['html', 'htm', 'php'])) {
                continue;
            }

            $size = filesize($fullPath);
            $mtime = filemtime($fullPath);
            $totalSize += $size;

            // قراءة جزء كافٍ من الملف لاستخراج العنوان (حتى 128 كيلوبايت)
            $buffer = '';
            $handle = @fopen($fullPath, 'r');
            if ($handle) {
                $buffer = fread($handle, 131072);
                fclose($handle);
            }

            $parsedTitle = '';
            $subtitle = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $buffer, $matches)) {
                $rawTitle = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (!empty($rawTitle)) {
                    if (str_contains($rawTitle, '|')) {
                        $parts = array_map('trim', explode('|', $rawTitle, 2));
                        $parsedTitle = $parts[0];
                        $subtitle    = $parts[1] ?? '';
                    } elseif (str_contains($rawTitle, ' - ')) {
                        $parts = array_map('trim', explode(' - ', $rawTitle, 2));
                        $parsedTitle = $parts[0];
                        $subtitle    = $parts[1] ?? '';
                    } else {
                        $parsedTitle = $rawTitle;
                    }
                }
            }

            // استخراج كود أو رقم النموذج من اسم الملف أو من الحقول
            $baseName = pathinfo($entry, PATHINFO_FILENAME);
            $formCode = $baseName;
            if (preg_match('/^([A-Za-z0-9]+-[A-Za-z0-9\-]+)/', $baseName, $codeMatch)) {
                $formCode = strtoupper($codeMatch[1]);
            }

            // إذا لم يتوفر عنوان بالملف نستخدم اسم الملف المعرّب
            if (empty($parsedTitle)) {
                $parsedTitle = ucwords(str_replace(['-', '_'], ' ', $baseName));
            }

            // استخراج التصنيف والألوان
            $categoryMeta = getCategoryMeta($formCode, $parsedTitle, $entry);
            $categoryName = $categoryMeta['name'];
            $categoriesCount[$categoryName] = ($categoriesCount[$categoryName] ?? 0) + 1;

            $forms[] = [
                'filename'        => $entry,
                'basename'        => $baseName,
                'code'            => $formCode,
                'title'           => $parsedTitle,
                'subtitle'        => $subtitle,
                'extension'       => strtoupper($ext),
                'size_bytes'      => $size,
                'size_formatted'  => formatFileSizeHuman($size),
                'mtime'           => $mtime,
                'date_formatted'  => formatArabicDate($mtime),
                'relative_url'    => $webFormsPath . rawurlencode($entry),
                'category'        => $categoryName,
                'meta'            => $categoryMeta
            ];
        }

        // ترتيب النماذج تنازلياً حسب تاريخ آخر تحديث
        usort($forms, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    }
}

$totalForms = count($forms);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>بوابة النماذج الإلكترونية | مصنع المنار للبلاستيك</title>
    
    <!-- خط تجوال من Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    
    <!-- مكتبة Tailwind CSS عبر CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f8fafc;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex flex-col justify-between selection:bg-emerald-500 selection:text-white">

    <!-- شريط الإشعار العلوي وحالة النظام -->
    <header class="bg-white/80 backdrop-blur-md sticky top-0 z-40 border-b border-slate-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- الشعار والهوية -->
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">بوابة النماذج الإلكترونية</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                نظام ذكي
                            </span>
                        </div>
                        <p class="text-xs font-medium text-slate-500">مصنع المنار للبلاستيك | نظام إدارة الوثائق والعمليات</p>
                    </div>
                </div>

                <!-- شارات الحالة والأدوات -->
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex items-center gap-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200/70 px-3 py-1.5 rounded-full">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span>فحص المجلد نشط</span>
                    </div>

                    <a href="https://github.com/khaledtaha-tech/factory-forms" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs"
                       title="المستودع البرمجي على GitHub">
                        <svg class="w-4 h-4 text-slate-600" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"></path>
                        </svg>
                        <span class="hidden sm:inline">GitHub</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- المحتوى الرئيسي -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <!-- بطاقة الترحيب والإحصائيات السريعة -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 p-6 sm:p-8 text-white shadow-xl shadow-slate-900/10 mb-8">
            <div class="absolute -left-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -right-12 -top-12 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-7 space-y-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-emerald-300 border border-white/10 backdrop-blur-xs">
                        🚀 استضافة سحابية جاهزة (Hostinger)
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                        إدارة واستعراض نماذج مصنع المنار للبلاستيك
                    </h2>
                    <p class="text-sm text-slate-300 leading-relaxed max-w-2xl font-normal">
                        منظومة مركزية ديناميكية تقرأ النماذج من مجلد <code class="px-1.5 py-0.5 rounded bg-white/15 text-emerald-300 font-mono text-xs">forms/</code> مباشرة. يمكنك معاينة أي نموذج في تبويب مستقل وطباعته أو تنزيل نسخة HTML محلياً.
                    </p>
                </div>

                <!-- إحصائيات النظام -->
                <div class="lg:col-span-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5 backdrop-blur-xs text-center">
                        <span class="block text-2xl font-black text-emerald-400"><?= $totalForms ?></span>
                        <span class="text-xs text-slate-300 font-medium">نماذج متاحة</span>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5 backdrop-blur-xs text-center">
                        <span class="block text-2xl font-black text-teal-300"><?= count($categoriesCount) ?></span>
                        <span class="text-xs text-slate-300 font-medium">أقسام وتصنيفات</span>
                    </div>
                    <div class="col-span-2 sm:col-span-1 bg-white/5 border border-white/10 rounded-2xl p-3.5 backdrop-blur-xs text-center">
                        <span class="block text-2xl font-black text-amber-300"><?= formatFileSizeHuman($totalSize) ?></span>
                        <span class="text-xs text-slate-300 font-medium">الحجم الكلي</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- شريط البحث السريع والفلترة الفورية -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 sm:p-5 mb-8">
            <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
                
                <!-- حقل البحث الفوري -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" id="liveSearchInput" 
                           placeholder="ابحث باسم النموذج، الرقم التعريفي، أو القسم... (مثال: PR-2026 أو رواتب أو المنار)" 
                           class="w-full pr-11 pl-10 py-3 rounded-xl border border-slate-200 bg-slate-50/60 text-slate-800 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent focus:bg-white transition"
                           autocomplete="off">
                    
                    <!-- زر مسح البحث -->
                    <button type="button" id="clearSearchBtn" class="hidden absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 hover:text-slate-600 transition" title="مسح البحث">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- عداد النتائج وخصائص الفرز -->
                <div class="flex items-center justify-between md:justify-end gap-3 text-xs">
                    <span class="text-slate-500 font-medium">
                        النتائج: <span id="visibleFormsCount" class="font-bold text-slate-900"><?= $totalForms ?></span> من <?= $totalForms ?>
                    </span>
                    <select id="sortSelect" class="bg-slate-50 border border-slate-200 text-slate-700 py-2.5 px-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-xs font-semibold">
                        <option value="newest">الأحدث تعديلاً</option>
                        <option value="oldest">الأقدم تعديلاً</option>
                        <option value="title">الاسم أبجدياً</option>
                        <option value="size">حجم الملف</option>
                    </select>
                </div>
            </div>

            <!-- أزرار التصنيفات السريعة -->
            <?php if (!empty($categoriesCount)): ?>
            <div class="mt-4 pt-3.5 border-t border-slate-100 flex flex-wrap items-center gap-2" id="categoryFilterContainer">
                <span class="text-xs font-bold text-slate-400 ml-1">التصنيف:</span>
                <button type="button" data-filter="all" class="category-pill active px-3 py-1 rounded-lg text-xs font-bold transition bg-emerald-600 text-white shadow-xs">
                    الكل (<?= $totalForms ?>)
                </button>
                <?php foreach ($categoriesCount as $catName => $count): ?>
                <button type="button" data-filter="<?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>" class="category-pill px-3 py-1 rounded-lg text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                    <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?> (<?= $count ?>)
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- شبكة عرض البطاقات -->
        <?php if ($totalForms === 0): ?>
            <!-- حالة فراغ المجلد أصلاً -->
            <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center max-w-xl mx-auto shadow-xs">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">لا توجد نماذج مضافة حتى الآن</h3>
                <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                    لم يتم العثور على أي ملفات ذات امتداد <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-700">.html</code> أو <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-700">.php</code> في مجلد <code class="font-mono text-emerald-600 font-bold">forms/</code>.
                </p>
                <div class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 bg-slate-100 px-4 py-2 rounded-xl">
                    <span>ضع ملفات النماذج داخل مجلد forms وستظهر هنا فوراً</span>
                </div>
            </div>
        <?php else: ?>
            <div id="formsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($forms as $form): ?>
                <div class="form-card bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between overflow-hidden group"
                     data-title="<?= htmlspecialchars(mb_strtolower($form['title'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-subtitle="<?= htmlspecialchars(mb_strtolower($form['subtitle'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-code="<?= htmlspecialchars(mb_strtolower($form['code'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-filename="<?= htmlspecialchars(mb_strtolower($form['filename'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-category="<?= htmlspecialchars($form['category'], ENT_QUOTES, 'UTF-8') ?>"
                     data-mtime="<?= $form['mtime'] ?>"
                     data-size="<?= $form['size_bytes'] ?>">
                    
                    <!-- الجزء العلوي من البطاقة -->
                    <div class="p-5 flex-1 flex flex-col">
                        
                        <!-- الرأس: الكود والتصنيف والامتداد -->
                        <div class="flex items-center justify-between gap-2 mb-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold font-mono tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                </svg>
                                <?= htmlspecialchars($form['code'], ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-extrabold uppercase <?= $form['extension'] === 'PHP' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                                    <?= htmlspecialchars($form['extension'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                        <!-- شارة التصنيف -->
                        <div class="mb-2.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $form['meta']['badgeClass'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $form['meta']['dotClass'] ?>"></span>
                                <?= htmlspecialchars($form['category'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <!-- عنوان النموذج واسمه المعرّب -->
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-emerald-700 transition line-clamp-2 leading-snug mb-1">
                            <?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <?php if (!empty($form['subtitle'])): ?>
                        <p class="text-xs text-slate-500 font-medium mb-3 line-clamp-1">
                            <?= htmlspecialchars($form['subtitle'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php endif; ?>

                        <div class="mt-auto pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-500">
                            <!-- تاريخ التعديل -->
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>آخر تحديث:</span>
                                </span>
                                <span class="font-medium text-slate-700" title="<?= date('Y-m-d H:i:s', $form['mtime']) ?>">
                                    <?= htmlspecialchars($form['date_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <!-- حجم الملف واسم الملف الحقيقي -->
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7h16m-5 4h.01m-4 0h.01m-4 0h.01"></path>
                                    </svg>
                                    <span>حجم الملف:</span>
                                </span>
                                <span class="font-semibold text-slate-800 font-mono">
                                    <?= htmlspecialchars($form['size_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- الجزء السفلي / أزرار الإجراءات -->
                    <div class="p-4 bg-slate-50/80 border-t border-slate-100 flex items-center gap-2">
                        <!-- زر فتح ومعاينة في تبويب جديد -->
                        <a href="<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="flex-1 inline-flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-bold text-white <?= $form['meta']['btnClass'] ?> shadow-sm transition active:scale-[0.98]"
                           title="معاينة النموذج في تبويب جديد">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            <span>فتح ومعاينة</span>
                        </a>

                        <!-- زر تحميل الملف مباشرة -->
                        <a href="<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>" 
                           download="<?= htmlspecialchars($form['filename'], ENT_QUOTES, 'UTF-8') ?>"
                           class="inline-flex items-center justify-center p-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-100 hover:border-slate-300 transition shadow-2xs active:scale-[0.98]"
                           title="تحميل الملف مباشرة">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </a>

                        <!-- زر نسخ الرابط المباشر -->
                        <button type="button" 
                                onclick="copyFormLink('<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>')"
                                class="inline-flex items-center justify-center p-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-100 hover:border-slate-300 transition shadow-2xs active:scale-[0.98]"
                                title="نسخ رابط النموذج">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <!-- رسالة حالة الفراغ عند البحث بدون نتائج (Search Empty State) -->
            <div id="noResultsState" class="hidden bg-white rounded-3xl border border-slate-200 p-12 text-center max-w-lg mx-auto shadow-sm my-6">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">لم يتم العثور على أي نتائج مطابقة</h3>
                <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                    لا يوجد أي نموذج يطابق استعلام البحث الحالي. تحقق من صحة الأحرف أو حاول بالرقم التعريفي.
                </p>
                <button type="button" id="resetSearchBtn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>إعادة ضبط البحث وعرض الكل</span>
                </button>
            </div>
        <?php endif; ?>

    </main>

    <!-- التذييل (Footer) -->
    <footer class="bg-white border-t border-slate-200/90 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-700">مصنع المنار للبلاستيك</span>
                    <span>•</span>
                    <span>جميع الحقوق محفوظة © <?= date('Y') ?></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1 text-[11px] font-mono bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md">
                        PHP <?= PHP_VERSION ?>
                    </span>
                    <span class="text-slate-300">|</span>
                    <span class="text-slate-500 font-medium">مجهزة للاستضافة على Hostinger</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- توست الإشعارات (Toast Notification) -->
    <div id="toastNotification" class="fixed bottom-6 left-6 z-50 transform translate-y-24 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="bg-slate-900 text-white text-xs font-bold px-4 py-3 rounded-xl shadow-xl flex items-center gap-2 border border-slate-800">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span id="toastMessage">تم نسخ الرابط بنجاح!</span>
        </div>
    </div>

    <!-- كود JavaScript للبحث الفوري والفلترة -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('liveSearchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            const cards = Array.from(document.querySelectorAll('.form-card'));
            const noResults = document.getElementById('noResultsState');
            const visibleCounter = document.getElementById('visibleFormsCount');
            const resetSearchBtn = document.getElementById('resetSearchBtn');
            const sortSelect = document.getElementById('sortSelect');
            const categoryPills = document.querySelectorAll('.category-pill');
            const formsGrid = document.getElementById('formsGrid');

            let currentCategory = 'all';

            // تصفية النماذج بناءً على البحث والتصنيف
            function filterForms() {
                const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

                if (clearBtn) {
                    if (query.length > 0) {
                        clearBtn.classList.remove('hidden');
                    } else {
                        clearBtn.classList.add('hidden');
                    }
                }

                let visibleCount = 0;

                cards.forEach(card => {
                    const title = card.getAttribute('data-title') || '';
                    const subtitle = card.getAttribute('data-subtitle') || '';
                    const code = card.getAttribute('data-code') || '';
                    const filename = card.getAttribute('data-filename') || '';
                    const category = card.getAttribute('data-category') || '';

                    const matchesQuery = query === '' || 
                                         title.includes(query) || 
                                         subtitle.includes(query) || 
                                         code.includes(query) || 
                                         filename.includes(query) ||
                                         category.toLowerCase().includes(query);

                    const matchesCategory = currentCategory === 'all' || category === currentCategory;

                    if (matchesQuery && matchesCategory) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (visibleCounter) {
                    visibleCounter.textContent = visibleCount;
                }

                if (noResults) {
                    if (visibleCount === 0 && cards.length > 0) {
                        noResults.classList.remove('hidden');
                    } else {
                        noResults.classList.add('hidden');
                    }
                }
            }

            // الفرز
            if (sortSelect && formsGrid) {
                sortSelect.addEventListener('change', () => {
                    const sortMode = sortSelect.value;
                    const sortedCards = [...cards].sort((a, b) => {
                        if (sortMode === 'newest') {
                            return Number(b.dataset.mtime) - Number(a.dataset.mtime);
                        } else if (sortMode === 'oldest') {
                            return Number(a.dataset.mtime) - Number(b.dataset.mtime);
                        } else if (sortMode === 'title') {
                            return a.dataset.title.localeCompare(b.dataset.title, 'ar');
                        } else if (sortMode === 'size') {
                            return Number(b.dataset.size) - Number(a.dataset.size);
                        }
                        return 0;
                    });

                    sortedCards.forEach(card => formsGrid.appendChild(card));
                });
            }

            // استماع للأحداث
            if (searchInput) {
                searchInput.addEventListener('input', filterForms);
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    searchInput.focus();
                    filterForms();
                });
            }

            if (resetSearchBtn) {
                resetSearchBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    currentCategory = 'all';
                    categoryPills.forEach(p => {
                        p.classList.remove('active', 'bg-emerald-600', 'text-white', 'shadow-xs');
                        p.classList.add('text-slate-600', 'bg-slate-100');
                        if (p.getAttribute('data-filter') === 'all') {
                            p.classList.add('active', 'bg-emerald-600', 'text-white', 'shadow-xs');
                            p.classList.remove('text-slate-600', 'bg-slate-100');
                        }
                    });
                    filterForms();
                });
            }

            // أزرار الفلترة حسب التصنيف
            categoryPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    categoryPills.forEach(p => {
                        p.classList.remove('active', 'bg-emerald-600', 'text-white', 'shadow-xs');
                        p.classList.add('text-slate-600', 'bg-slate-100');
                    });
                    pill.classList.add('active', 'bg-emerald-600', 'text-white', 'shadow-xs');
                    pill.classList.remove('text-slate-600', 'bg-slate-100');

                    currentCategory = pill.getAttribute('data-filter') || 'all';
                    filterForms();
                });
            });

            // اختصار لوحة المفاتيح: الضغط على "/" يركز على مربع البحث
            window.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== searchInput) {
                    e.preventDefault();
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
            });
        });

        // نسخ الرابط المباشر للنموذج مع إشعار توست
        function copyFormLink(relativeUrl) {
            const fullUrl = new URL(relativeUrl, window.location.href).href;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(fullUrl).then(() => showToast('تم نسخ الرابط المباشر للنموذج بنجاح!'));
            } else {
                const temp = document.createElement('input');
                temp.value = fullUrl;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                showToast('تم نسخ الرابط المباشر للنموذج بنجاح!');
            }
        }

        function showToast(message) {
            const toast = document.getElementById('toastNotification');
            const msgEl = document.getElementById('toastMessage');
            if (toast && msgEl) {
                msgEl.textContent = message;
                toast.classList.remove('translate-y-24', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
                setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('translate-y-24', 'opacity-0');
                }, 3000);
            }
        }
    </script>
</body>
</html>
