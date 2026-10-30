<?php
/**
 * ==============================================================================
 * Electronic Forms Portal - Al-Manar Plastic Factory
 * ==============================================================================
 * Central Dynamic Forms Dashboard with Per-Form Security Shield
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unicode translation helper to ensure 0% non-ASCII in PHP source code
function u(string $unicodeEscapedStr): string {
    $decoded = json_decode('"' . $unicodeEscapedStr . '"');
    return $decoded !== null ? $decoded : $unicodeEscapedStr;
}

$masterPin = '2026';

// 1. Read Protected Forms Registry
$protectedConfigFile = __DIR__ . '/config/protected_forms.json';
$protectedFiles = [];
if (file_exists($protectedConfigFile)) {
    $rawConfig = file_get_contents($protectedConfigFile);
    $decodedConfig = json_decode($rawConfig, true);
    if (is_array($decodedConfig)) {
        $protectedFiles = $decodedConfig;
    }
}

// 2. Directory Configurations
$formsDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'forms';
$webFormsPath   = 'forms/';

// 3. Helper Functions for Formatting
function formatArabicDate($timestamp) {
    $months = [
        1 => '\u064a\u0646\u0627\u064a\u0631',
        2 => '\u0641\u0628\u0631\u0627\u064a\u0631',
        3 => '\u0645\u0627\u0631\u0633',
        4 => '\u0623\u0628\u0631\u064a\u0644',
        5 => '\u0645\u0627\u064a\u0648',
        6 => '\u064a\u0648\u0646\u064a\u0648',
        7 => '\u064a\u0648\u0644\u064a\u0648',
        8 => '\u0623\u063a\u0633\u0637\u0633',
        9 => '\u0633\u0628\u062a\u0645\u0628\u0631',
        10 => '\u0623\u0643\u062a\u0648\u0628\u0631',
        11 => '\u0646\u0648\u0641\u0645\u0628\u0631',
        12 => '\u062f\u064a\u0633\u0645\u0628\u0631'
    ];
    $day   = date('j', $timestamp);
    $mCode = $months[(int)date('n', $timestamp)] ?? '\u0634\u0647\u0631';
    $month = u($mCode);
    $year  = date('Y', $timestamp);
    $time  = date('h:i', $timestamp);
    $ampm  = date('A', $timestamp) === 'AM' ? u('\u0635\u0628\u0627\u062d\u0627\u064b') : u('\u0645\u0633\u0627\u0621\u064b');
    return "{$day} {$month} {$year} - {$time} {$ampm}";
}

function formatFileSizeHuman($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' ' . u('\u0645.\u0628');
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' ' . u('\u0643.\u0628');
    } else {
        return $bytes . ' ' . u('\u0628\u0627\u064a\u062a');
    }
}

function getCategoryMeta($code, $title, $filename) {
    $haystack = mb_strtolower($code . ' ' . $title . ' ' . $filename, 'UTF-8');

    if (str_contains($haystack, 'pr-') || str_contains($haystack, 'purchase') || str_contains($haystack, 'requisition') || str_contains($haystack, u('\u0634\u0631\u0627\u0621'))) {
        return [
            'name'       => u('\u0627\u0644\u0645\u0634\u062a\u0631\u064a\u0627\u062a \u0648\u0627\u0644\u0637\u0644\u0628\u0627\u062a'),
            'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-500/20',
            'dotClass'   => 'bg-emerald-500',
            'iconColor'  => 'text-emerald-600',
            'gradient'   => 'from-emerald-500 to-teal-600',
            'btnClass'   => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500'
        ];
    } elseif (str_contains($haystack, 'hr-') || str_contains($haystack, 'salary') || str_contains($haystack, 'attendance') || str_contains($haystack, u('\u0631\u0648\u0627\u062a\u0628')) || str_contains($haystack, u('\u062d\u0636\u0648\u0631')) || str_contains($haystack, u('\u0645\u0648\u0638\u0641'))) {
        return [
            'name'       => u('\u0627\u0644\u0645\u0648\u0627\u0631\u062f \u0627\u0644\u0628\u0634\u0631\u064a\u0629 \u0648\u0634\u0624\u0648\u0646 \u0627\u0644\u0645\u0648\u0638\u0641\u064a\u0646'),
            'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200 ring-blue-500/20',
            'dotClass'   => 'bg-blue-500',
            'iconColor'  => 'text-blue-600',
            'gradient'   => 'from-blue-600 to-indigo-600',
            'btnClass'   => 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
        ];
    } elseif (str_contains($haystack, 'qc-') || str_contains($haystack, 'quality') || str_contains($haystack, u('\u062c\u0648\u062f\u0629')) || str_contains($haystack, u('\u0641\u062d\u0635'))) {
        return [
            'name'       => u('\u0645\u0631\u0627\u0642\u0628\u0629 \u0648\u062a\u0623\u0643\u064a\u062f \u0627\u0644\u062c\u0648\u062f\u0629'),
            'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200 ring-purple-500/20',
            'dotClass'   => 'bg-purple-500',
            'iconColor'  => 'text-purple-600',
            'gradient'   => 'from-purple-600 to-indigo-600',
            'btnClass'   => 'bg-purple-600 hover:bg-purple-700 focus:ring-purple-500'
        ];
    } elseif (str_contains($haystack, 'prd-') || str_contains($haystack, 'production') || str_contains($haystack, u('\u0625\u0646\u062a\u0627\u062c')) || str_contains($haystack, u('\u062a\u0634\u063a\u064a\u0644'))) {
        return [
            'name'       => u('\u0627\u0644\u0625\u0646\u062a\u0627\u062c \u0648\u0627\u0644\u062a\u0634\u063a\u064a\u0644 \u0627\u0644\u0641\u0646\u064a'),
            'badgeClass' => 'bg-amber-50 text-amber-800 border-amber-200 ring-amber-500/20',
            'dotClass'   => 'bg-amber-500',
            'iconColor'  => 'text-amber-600',
            'gradient'   => 'from-amber-500 to-orange-600',
            'btnClass'   => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500'
        ];
    } elseif (str_contains($haystack, 'wh-') || str_contains($haystack, 'inventory') || str_contains($haystack, u('\u0645\u0633\u062a\u0648\u062f\u0639')) || str_contains($haystack, u('\u0645\u062e\u0627\u0632\u0646'))) {
        return [
            'name'       => u('\u0627\u0644\u0645\u0633\u062a\u0648\u062f\u0639\u0627\u062a \u0648\u0627\u0644\u0645\u062e\u0632\u0648\u0646'),
            'badgeClass' => 'bg-cyan-50 text-cyan-800 border-cyan-200 ring-cyan-500/20',
            'dotClass'   => 'bg-cyan-500',
            'iconColor'  => 'text-cyan-600',
            'gradient'   => 'from-cyan-600 to-teal-600',
            'btnClass'   => 'bg-cyan-600 hover:bg-cyan-700 focus:ring-cyan-500'
        ];
    }

    return [
        'name'       => u('\u0646\u0645\u0627\u0630\u062c \u0639\u0627\u0645\u0629 \u0648\u0625\u062f\u0627\u0631\u064a\u0629'),
        'badgeClass' => 'bg-slate-50 text-slate-700 border-slate-200 ring-slate-500/20',
        'dotClass'   => 'bg-slate-400',
        'iconColor'  => 'text-slate-600',
        'gradient'   => 'from-slate-700 to-slate-900',
        'btnClass'   => 'bg-slate-800 hover:bg-slate-900 focus:ring-slate-500'
    ];
}

// 4. Scan Forms Directory
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

            $baseName = pathinfo($entry, PATHINFO_FILENAME);
            $formCode = $baseName;
            if (preg_match('/^([A-Za-z0-9]+-[A-Za-z0-9\-]+)/', $baseName, $codeMatch)) {
                $formCode = strtoupper($codeMatch[1]);
            }

            if (empty($parsedTitle)) {
                $parsedTitle = ucwords(str_replace(['-', '_'], ' ', $baseName));
            }

            $categoryMeta = getCategoryMeta($formCode, $parsedTitle, $entry);
            $categoryName = $categoryMeta['name'];
            $categoriesCount[$categoryName] = ($categoriesCount[$categoryName] ?? 0) + 1;

            $isProtected = in_array($entry, $protectedFiles);

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
                'meta'            => $categoryMeta,
                'is_protected'    => $isProtected
            ];
        }

        usort($forms, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    }
}

$totalForms = count($forms);

// Calculate Public vs All Metrics
$publicForms = array_filter($forms, fn($f) => empty($f['is_protected']));
$publicCount = count($publicForms);
$publicSize  = array_sum(array_column($publicForms, 'size_bytes'));

$publicCategoryCounts = [];
foreach ($publicForms as $pf) {
    $c = $pf['category'];
    $publicCategoryCounts[$c] = ($publicCategoryCounts[$c] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>\u0628\u0648\u0627\u0628\u0629 \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u0625\u0644\u0643\u062a\u0631\u0648\u0646\u064a\u0629 | \u0645\u0635\u0646\u0639 \u0627\u0644\u0645\u0646\u0627\u0631 \u0644\u0644\u0628\u0644\u0627\u0633\u062a\u064a\u0643</title>
    
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="favicon.ico">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f8fafc;
        }
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

        /* Compatibility utilities for Iqama Renewal Card */
        .card { background-color: #ffffff; border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 1rem; }
        .card-body { display: flex; flex-direction: column; padding: 1.5rem; }
        .d-flex { display: flex !important; }
        .d-inline-flex { display: inline-flex !important; }
        .flex-column { flex-direction: column !important; }
        .flex-grow-1 { flex-grow: 1 !important; }
        .justify-content-between { justify-content: space-between !important; }
        .align-items-center { align-items: center !important; }
        .justify-content-center { justify-content: center !important; }
        .text-center { text-align: center !important; }
        .text-muted { color: #64748b !important; }
        .text-dark { color: #0f172a !important; }
        .fw-bold { font-weight: 700 !important; }
        .fw-medium { font-weight: 500 !important; }
        .font-monospace { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important; }
        .rounded-pill { border-radius: 9999px !important; }
        .rounded-circle { border-radius: 50% !important; }
        .bg-primary { background-color: #2563eb !important; color: #ffffff !important; }
        .bg-light { background-color: #f8fafc !important; }
        .bg-secondary { background-color: #64748b !important; }
        .border { border: 1px solid #e2e8f0 !important; }
        .border-top { border-top: 1px solid #e2e8f0 !important; }
        .badge { display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; line-height: 1; }
        .btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.75rem; font-size: 0.8125rem; transition: all 0.2s ease; cursor: pointer; text-decoration: none; padding: 0.5rem 0.75rem; }
        .btn-primary { background-color: #2563eb !important; color: #ffffff !important; border: 1px solid #2563eb !important; box-shadow: 0 1px 2px 0 rgba(37, 99, 235, 0.2); }
        .btn-primary:hover { background-color: #1d4ed8 !important; border-color: #1d4ed8 !important; }
        .btn-light { background-color: #ffffff !important; color: #334155 !important; border: 1px solid #e2e8f0 !important; }
        .btn-light:hover { background-color: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex flex-col justify-between selection:bg-emerald-500 selection:text-white">

    <!-- Header Navigation -->
    <header class="bg-white/80 backdrop-blur-md sticky top-0 z-40 border-b border-slate-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Logo & Portal Identity -->
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">\u0628\u0648\u0627\u0628\u0629 \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u0625\u0644\u0643\u062a\u0631\u0648\u0646\u064a\u0629</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                \u0646\u0638\u0627\u0645 \u0630\u0643\u064a
                            </span>
                        </div>
                        <p class="text-xs font-medium text-slate-500">\u0645\u0635\u0646\u0639 \u0627\u0644\u0645\u0646\u0627\u0631 \u0644\u0644\u0628\u0644\u0627\u0633\u062a\u064a\u0643 | \u0646\u0638\u0627\u0645 \u0625\u062f\u0627\u0631\u0629 \u0627\u0644\u0648\u062b\u0627\u0626\u0642 \u0648\u0627\u0644\u0639\u0645\u0644\u064a\u0627\u062a</p>
                    </div>
                </div>

                <!-- Navigation Controls & Security Buttons -->
                <div class="flex items-center gap-2.5">
                    
                    <!-- Scan Active Badge -->
                    <div class="hidden sm:flex items-center gap-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200/70 px-3 py-1.5 rounded-full">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span>\u0641\u062d\u0635 \u0627\u0644\u0645\u062c\u0644\u062f \u0646\u0634\u0637</span>
                    </div>

                    <!-- Create PR Button -->
                    <a href="create-pr.php" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white transition shadow-sm"
                       title="\u0627\u0644\u0627\u0646\u062a\u0642\u0627\u0644 \u0625\u0644\u0649 \u0628\u0648\u0627\u0628\u0629 \u0625\u0646\u0634\u0627\u0621 \u0637\u0644\u0628\u0627\u062a \u0627\u0644\u0634\u0631\u0627\u0621">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>\u0625\u0646\u0634\u0627\u0621 \u0637\u0644\u0628 \u0634\u0631\u0627\u0621</span>
                    </a>

                    <!-- Security Manager Button (Revealed after Master PIN unlock) -->
                    <button type="button" id="btnManageSecurity" onclick="openSecurityModal()" 
                            class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-xs font-bold text-white transition shadow-sm border border-slate-700"
                            title="\u0625\u062f\u0627\u0631\u0629 \u062d\u0645\u0627\u064a\u0629 \u0627\u0644\u0646\u0645\u0627\u0630\u062c">
                        <span>\ud83d\udee1\ufe0f \u0625\u062f\u0627\u0631\u0629 \u0627\u0644\u062d\u0645\u0627\u064a\u0629</span>
                    </button>

                    <!-- Master Lock Button -->
                    <button type="button" id="btnMasterLock" onclick="handleMasterLockClick()" 
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs"
                            title="\u0642\u0641\u0644 \u0627\u0644\u0623\u0645\u0627\u0646 / \u0641\u062a\u062d \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u0645\u062d\u0645\u064a\u0629">
                        <svg id="lockIconSvg" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span id="lockButtonLabel">\u0642\u0641\u0644 \u0627\u0644\u0623\u0645\u0627\u0646</span>
                    </button>

                    <!-- GitHub Repository Link -->
                    <a href="https://github.com/khaledtaha-tech/factory-forms" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs"
                       title="GitHub">
                        <svg class="w-4 h-4 text-slate-600" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"></path>
                        </svg>
                        <span class="hidden sm:inline">GitHub</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <!-- Welcome Hero & Dynamic Metrics -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 p-6 sm:p-8 text-white shadow-xl shadow-slate-900/10 mb-8">
            <div class="absolute -left-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -right-12 -top-12 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-7 space-y-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-emerald-300 border border-white/10 backdrop-blur-xs">
                        \ud83d\ude80 \u0627\u0633\u062a\u0636\u0627\u0641\u0629 \u0633\u062d\u0627\u0628\u064a\u0629 \u062c\u0627\u0647\u0632\u0629 (Hostinger)
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                        \u0625\u062f\u0627\u0631\u0629 \u0648\u0627\u0633\u062a\u0639\u0631\u0627\u0636 \u0646\u0645\u0627\u0630\u062c \u0645\u0635\u0646\u0639 \u0627\u0644\u0645\u0646\u0627\u0631 \u0644\u0644\u0628\u0644\u0627\u0633\u062a\u064a\u0643
                    </h2>
                    <p class="text-sm text-slate-300 leading-relaxed max-w-2xl font-normal">
                        \u0645\u0646\u0638\u0648\u0645\u0629 \u0645\u0631\u0643\u0632\u064a\u0629 \u062f\u064a\u0646\u0627\u0645\u064a\u0643\u064a\u0629 \u062a\u0642\u0631\u0623 \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0645\u0646 \u0645\u062c\u0644\u062f <code class="px-1.5 py-0.5 rounded bg-white/15 text-emerald-300 font-mono text-xs">forms/</code> \u0645\u0628\u0627\u0634\u0631\u0629 \u0645\u0639 \u0646\u0638\u0627\u0645 \u062d\u0645\u0627\u064a\u0629 \u0645\u0631\u0643\u0632\u064a.
                    </p>
                    <div class="pt-2 flex flex-wrap items-center gap-3">
                        <a href="create-pr.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-lg shadow-emerald-900/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>+ \u0625\u0646\u0634\u0627\u0621 \u0637\u0644\u0628 \u0634\u0631\u0627\u0621 \u0631\u0633\u0645\u064a \u062c\u062f\u064a\u062f</span>
                        </a>
                    </div>
                </div>

                <!-- Live Metrics Counters (Adjusts on Lock/Unlock) -->
                <div class="lg:col-span-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5 backdrop-blur-xs text-center">
                        <span id="statFormsCount" data-public="<?= $publicCount ?>" data-all="<?= $totalForms ?>" class="block text-2xl font-black text-emerald-400"><?= $publicCount ?></span>
                        <span class="text-xs text-slate-300 font-medium">\u0646\u0645\u0627\u0630\u062c \u0645\u062a\u0627\u062d\u0629</span>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5 backdrop-blur-xs text-center">
                        <span id="statCategoriesCount" data-public="<?= count($publicCategoryCounts) ?>" data-all="<?= count($categoriesCount) ?>" class="block text-2xl font-black text-teal-300"><?= count($publicCategoryCounts) ?></span>
                        <span class="text-xs text-slate-300 font-medium">\u0623\u0642\u0633\u0627\u0645 \u0648\u062a\u0635\u0646\u064a\u0641\u0627\u062a</span>
                    </div>
                    <div class="col-span-2 sm:col-span-1 bg-white/5 border border-white/10 rounded-2xl p-3.5 backdrop-blur-xs text-center">
                        <span id="statTotalSize" data-public="<?= formatFileSizeHuman($publicSize) ?>" data-all="<?= formatFileSizeHuman($totalSize) ?>" class="block text-2xl font-black text-amber-300"><?= formatFileSizeHuman($publicSize) ?></span>
                        <span class="text-xs text-slate-300 font-medium">\u0627\u0644\u062d\u062c\u0645 \u0627\u0644\u0643\u0644\u064a</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 sm:p-5 mb-8">
            <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
                
                <!-- Search Input -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" id="liveSearchInput" 
                           placeholder="\u0627\u0628\u062d\u062b \u0628\u0627\u0633\u0645 \u0627\u0644\u0646\u0645\u0648\u0630\u062c\u060c \u0627\u0644\u0631\u0642\u0645 \u0627\u0644\u062a\u0639\u0631\u064a\u0641\u064a\u060c \u0623\u0648 \u0627\u0644\u0642\u0633\u0645... (\u0645\u062b\u0627\u0644: PR-2026 \u0623\u0648 \u0631\u0648\u0627\u062a\u0628)" 
                           class="w-full pr-11 pl-10 py-3 rounded-xl border border-slate-200 bg-slate-50/60 text-slate-800 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent focus:bg-white transition"
                           autocomplete="off">
                    
                    <button type="button" id="clearSearchBtn" class="hidden absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 hover:text-slate-600 transition" title="\u0645\u0633\u062d \u0627\u0644\u0628\u062d\u062b">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Result Counter & Sort -->
                <div class="flex items-center justify-between md:justify-end gap-3 text-xs">
                    <span class="text-slate-500 font-medium">
                        \u0627\u0644\u0646\u062a\u0627\u0626\u062c: <span id="visibleFormsCount" class="font-bold text-slate-900"><?= $publicCount ?></span> \u0645\u0646 <span id="totalVisibleRef" data-public="<?= $publicCount ?>" data-all="<?= $totalForms ?>" class="font-bold text-slate-700"><?= $publicCount ?></span>
                    </span>
                    <select id="sortSelect" class="bg-slate-50 border border-slate-200 text-slate-700 py-2.5 px-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-xs font-semibold">
                        <option value="newest">\u0627\u0644\u0623\u062d\u062f\u062b \u062a\u0639\u062f\u064a\u0644\u0627\u064b</option>
                        <option value="oldest">\u0627\u0644\u0623\u0642\u062f\u0645 \u062a\u0639\u062f\u064a\u0644\u0627\u064b</option>
                        <option value="title">\u0627\u0644\u0627\u0633\u0645 \u0623\u0628\u062c\u062f\u064a\u0627\u064b</option>
                        <option value="size">\u062d\u062c\u0645 \u0627\u0644\u0645\u0644\u0641</option>
                    </select>
                </div>
            </div>

            <!-- Category Pills -->
            <?php if (!empty($categoriesCount)): ?>
            <div class="mt-4 pt-3.5 border-t border-slate-100 flex flex-wrap items-center gap-2" id="categoryFilterContainer">
                <span class="text-xs font-bold text-slate-400 ml-1">\u0627\u0644\u062a\u0635\u0646\u064a\u0641:</span>
                <button type="button" data-filter="all" class="category-pill active px-3 py-1 rounded-lg text-xs font-bold transition bg-emerald-600 text-white shadow-xs">
                    <span>\u0627\u0644\u0643\u0644</span> (<span class="pill-count" data-public="<?= $publicCount ?>" data-all="<?= $totalForms ?>"><?= $publicCount ?></span>)
                </button>
                <?php foreach ($categoriesCount as $catName => $count): ?>
                <button type="button" data-filter="<?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>" class="category-pill px-3 py-1 rounded-lg text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                    <span><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></span> (<span class="pill-count" data-public="<?= $publicCategoryCounts[$catName] ?? 0 ?>" data-all="<?= $count ?>"><?= $publicCategoryCounts[$catName] ?? 0 ?></span>)
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Forms Cards Grid -->
        <?php if ($totalForms === 0): ?>
            <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center max-w-xl mx-auto shadow-xs">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">\u0644\u0627 \u062a\u0648\u062c\u062f \u0646\u0645\u0627\u0630\u062c \u0645\u0636\u0627\u0641\u0629 \u062d\u062a\u0649 \u0627\u0644\u0622\u0646</h3>
                <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                    \u0644\u0645 \u064a\u062a\u0645 \u0627\u0644\u0639\u062b\u0648\u0631 \u0639\u0644\u0649 \u0623\u064a \u0645\u0644\u0641\u0627\u062a \u062f\u0627\u062e\u0644 \u0645\u062c\u0644\u062f <code class="font-mono text-emerald-600 font-bold">forms/</code>.
                </p>
            </div>
        <?php else: ?>
            <div id="formsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($forms as $form): ?>
                <?php if ($form['filename'] === 'iqama-renewal.php'): ?>
                <!-- Custom Card: Iqama Renewal Application -->
                <div class="col-12 col-md-6 col-lg-4 mb-4 form-card card h-100 position-relative bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between overflow-hidden group"
                     data-title="<?= htmlspecialchars(mb_strtolower($form['title'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-subtitle="<?= htmlspecialchars(mb_strtolower($form['subtitle'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-code="<?= htmlspecialchars(mb_strtolower($form['code'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-filename="<?= htmlspecialchars(mb_strtolower($form['filename'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-category="<?= htmlspecialchars($form['category'], ENT_QUOTES, 'UTF-8') ?>"
                     data-mtime="<?= $form['mtime'] ?>"
                     data-size="<?= $form['size_bytes'] ?>"
                     data-protected="<?= $form['is_protected'] ? 'true' : 'false' ?>"
                     <?php if ($form['is_protected']): ?>style="display: none;"<?php endif; ?>>
                    <div class="card-body d-flex flex-column p-4 flex-1">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="badge bg-primary text-white rounded-pill px-3 py-2 fw-medium">PHP</span>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2 border font-monospace text-muted">
                                <?= htmlspecialchars($form['code'], ENT_QUOTES, 'UTF-8') ?> #
                            </span>
                        </div>

                        <div class="text-center mb-4 flex-grow-1">
                            <span class="d-inline-flex align-items-center bg-light rounded-pill px-3 py-1 mb-3 text-muted small border">
                                <span class="p-1 bg-secondary rounded-circle me-2" style="width: 6px; height: 6px;"></span>
                                <?= htmlspecialchars($form['category'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <h4 class="card-title fw-bold text-dark mb-2 text-lg"><?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="card-text text-muted mb-0 text-xs"><?= htmlspecialchars($form['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div class="form-meta d-flex justify-content-between text-muted small mb-4 pt-3 border-top">
                            <span class="d-flex align-items-center">
                                <i class="fa-regular fa-calendar me-2"></i> <?= u('\u0622\u062e\u0631 \u062a\u062d\u062f\u064a\u062b:') ?>
                            </span>
                            <span><?= htmlspecialchars($form['date_formatted'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        
                        <div class="form-meta d-flex justify-content-between text-muted small mb-4">
                            <span class="d-flex align-items-center">
                                <i class="fa-solid fa-server me-2"></i> <?= u('\u062d\u062c\u0645 \u0627\u0644\u0645\u0644\u0641:') ?>
                            </span>
                            <span><?= htmlspecialchars($form['size_formatted'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="d-flex gap-2 mt-auto">
                            <button type="button" 
                                    onclick="copyFormLink('<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>')" 
                                    class="btn btn-light border p-2" 
                                    title="<?= u('\u0646\u0633\u062e \u0627\u0644\u0631\u0627\u0628\u0637') ?>">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                            <a href="<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>" 
                               download="<?= htmlspecialchars($form['filename'], ENT_QUOTES, 'UTF-8') ?>" 
                               class="btn btn-light border p-2" 
                               title="<?= u('\u062a\u062d\u0645\u064a\u0644') ?>">
                                <i class="fa-solid fa-download"></i>
                            </a>
                            <a href="<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="btn btn-primary flex-grow-1 fw-bold d-flex align-items-center justify-content-center">
                                <?= u('\u0641\u062a\u062d \u0648\u0645\u0639\u0627\u064a\u0646\u0629') ?> <i class="fa-solid fa-arrow-up-right-from-square ms-2"></i>
                            </a>
                        </div>
                        
                    </div>
                </div>
                <?php else: ?>
                <div class="form-card bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between overflow-hidden group"
                     data-title="<?= htmlspecialchars(mb_strtolower($form['title'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-subtitle="<?= htmlspecialchars(mb_strtolower($form['subtitle'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-code="<?= htmlspecialchars(mb_strtolower($form['code'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-filename="<?= htmlspecialchars(mb_strtolower($form['filename'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                     data-category="<?= htmlspecialchars($form['category'], ENT_QUOTES, 'UTF-8') ?>"
                     data-mtime="<?= $form['mtime'] ?>"
                     data-size="<?= $form['size_bytes'] ?>"
                     data-protected="<?= $form['is_protected'] ? 'true' : 'false' ?>"
                     <?php if ($form['is_protected']): ?>style="display: none;"<?php endif; ?>>
                    
                    <!-- Card Body -->
                    <div class="p-5 flex-1 flex flex-col">
                        
                        <!-- Header with Code, Protected Badge & Extension -->
                        <div class="flex items-center justify-between gap-2 mb-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold font-mono tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                </svg>
                                <?= htmlspecialchars($form['code'], ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <div class="flex items-center gap-1.5">
                                <?php if ($form['is_protected']): ?>
                                <span class="protected-pill inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200" title="\u0645\u062d\u0645\u064a \u0628\u0631\u0645\u0632 PIN">
                                    <svg class="w-3 h-3 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    <span>\u0645\u062d\u0645\u064a</span>
                                </span>
                                <?php endif; ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-extrabold uppercase <?= $form['extension'] === 'PHP' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                                    <?= htmlspecialchars($form['extension'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Category Badge -->
                        <div class="mb-2.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $form['meta']['badgeClass'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $form['meta']['dotClass'] ?>"></span>
                                <?= htmlspecialchars($form['category'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <!-- Form Title & Subtitle -->
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-emerald-700 transition line-clamp-2 leading-snug mb-1">
                            <?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <?php if (!empty($form['subtitle'])): ?>
                        <p class="text-xs text-slate-500 font-medium mb-3 line-clamp-1">
                            <?= htmlspecialchars($form['subtitle'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php endif; ?>

                        <!-- Metadata Info -->
                        <div class="mt-auto pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-500">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>\u0622\u062e\u0631 \u062a\u062d\u062f\u064a\u062b:</span>
                                </span>
                                <span class="font-medium text-slate-700" title="<?= date('Y-m-d H:i:s', $form['mtime']) ?>">
                                    <?= htmlspecialchars($form['date_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7h16m-5 4h.01m-4 0h.01m-4 0h.01"></path>
                                    </svg>
                                    <span>\u062d\u062c\u0645 \u0627\u0644\u0645\u0644\u0641:</span>
                                </span>
                                <span class="font-semibold text-slate-800 font-mono">
                                    <?= htmlspecialchars($form['size_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- Action Footer -->
                    <div class="p-4 bg-slate-50/80 border-t border-slate-100 flex items-center gap-2">
                        <!-- Open / Preview Link -->
                        <a href="<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="flex-1 inline-flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-bold text-white <?= $form['meta']['btnClass'] ?> shadow-sm transition active:scale-[0.98]"
                           title="\u0645\u0639\u0627\u064a\u0646\u0629 \u0627\u0644\u0646\u0645\u0648\u0630\u062c \u0641\u064a \u062a\u0628\u0648\u064a\u0628 \u062c\u062f\u064a\u062f">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            <span>\u0641\u062a\u062d \u0648\u0645\u0639\u0627\u064a\u0646\u0629</span>
                        </a>

                        <!-- Download File -->
                        <a href="<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>" 
                           download="<?= htmlspecialchars($form['filename'], ENT_QUOTES, 'UTF-8') ?>"
                           class="inline-flex items-center justify-center p-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-100 hover:border-slate-300 transition shadow-2xs active:scale-[0.98]"
                           title="\u062a\u062d\u0645\u064a\u0644 \u0627\u0644\u0645\u0644\u0641 \u0645\u0628\u0627\u0634\u0631\u0629">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </a>

                        <!-- Copy Link -->
                        <button type="button" 
                                onclick="copyFormLink('<?= htmlspecialchars($form['relative_url'], ENT_QUOTES, 'UTF-8') ?>')"
                                class="inline-flex items-center justify-center p-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-100 hover:border-slate-300 transition shadow-2xs active:scale-[0.98]"
                                title="\u0646\u0633\u062e \u0631\u0627\u0628\u0637 \u0627\u0644\u0646\u0645\u0648\u0630\u062c">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>

                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search State -->
            <div id="noResultsState" class="hidden bg-white rounded-3xl border border-slate-200 p-12 text-center max-w-lg mx-auto shadow-sm my-6">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">\u0644\u0645 \u064a\u062a\u0645 \u0627\u0644\u0639\u062b\u0648\u0631 \u0639\u0644\u0649 \u0623\u064a \u0646\u062a\u0627\u0626\u062c \u0645\u0637\u0627\u0628\u0642\u0629</h3>
                <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                    \u0644\u0627 \u064a\u0648\u062c\u062f \u0623\u064a \u0646\u0645\u0648\u0630\u062c \u064a\u0637\u0627\u0628\u0642 \u0627\u0633\u062a\u0639\u0644\u0627\u0645 \u0627\u0644\u0628\u062d\u062b \u0627\u0644\u062d\u0627\u0644\u064a.
                </p>
                <button type="button" id="resetSearchBtn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>\u0625\u0639\u0627\u062f\u0629 \u0636\u0628\u0637 \u0627\u0644\u0628\u062d\u062b \u0648\u0639\u0631\u0636 \u0627\u0644\u0643\u0644</span>
                </button>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200/90 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-700">\u0645\u0635\u0646\u0639 \u0627\u0644\u0645\u0646\u0627\u0631 \u0644\u0644\u0628\u0644\u0627\u0633\u062a\u064a\u0643</span>
                    <span>&bull;</span>
                    <span>\u062c\u0645\u064a\u0639 \u0627\u0644\u062d\u0642\u0648\u0642 \u0645\u062d\u0641\u0648\u0638\u0629 &copy; <?= date('Y') ?></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1 text-[11px] font-mono bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md">
                        PHP <?= PHP_VERSION ?>
                    </span>
                    <span class="text-slate-300">|</span>
                    <span class="text-slate-500 font-medium">\u0645\u062c\u0647\u0632\u0629 \u0644\u0644\u0627\u0633\u062a\u0636\u0627\u0641\u0629 \u0639\u0644\u0649 Hostinger</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Toast Notification -->
    <div id="toastNotification" class="fixed bottom-6 left-6 z-50 transform translate-y-24 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="bg-slate-900 text-white text-xs font-bold px-4 py-3 rounded-xl shadow-xl flex items-center gap-2 border border-slate-800">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span id="toastMessage">\u062a\u0645 \u0628\u0646\u062c\u0627\u062d</span>
        </div>
    </div>

    <!-- Master PIN Unlock Modal -->
    <div id="modalMasterLock" class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl border border-slate-100 p-6 sm:p-7 text-center">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200/60 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-black text-slate-900 mb-1">\u0641\u062a\u062d \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u0645\u062d\u0645\u064a\u0629</h3>
            <p class="text-xs text-slate-500 mb-4">\u0623\u062f\u062e\u0644 \u0631\u0645\u0632 \u0627\u0644\u0623\u0645\u0627\u0646 (PIN) \u0644\u0639\u0631\u0636 \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u062e\u0627\u0635\u0629 \u0648\u0625\u062f\u0627\u0631\u0629 \u0627\u0644\u062d\u0645\u0627\u064a\u0629.</p>

            <div id="masterLockError" class="hidden mb-3.5 p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
                \u0631\u0645\u0632 \u0627\u0644\u0623\u0645\u0627\u0646 \u063a\u064a\u0631 \u0635\u062d\u064a\u062d
            </div>

            <form id="masterLockForm" onsubmit="submitMasterLock(event)" class="space-y-4">
                <div>
                    <input id="masterLockInput" type="password" inputmode="numeric" required autofocus placeholder="&#8226;&#8226;&#8226;&#8226;" 
                           class="w-full text-center tracking-[0.5em] text-2xl font-bold py-2.5 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm">
                        \u062a\u0623\u0643\u064a\u062f \u0627\u0644\u0641\u062a\u062d
                    </button>
                    <button type="button" onclick="closeMasterLockModal()" class="py-2.5 px-4 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                        \u0625\u0644\u063a\u0627\u0621
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Security Management Modal -->
    <div id="modalManageSecurity" class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 p-6 sm:p-8 flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">\u0625\u062f\u0627\u0631\u0629 \u062d\u0645\u0627\u064a\u0629 \u0627\u0644\u0646\u0645\u0627\u0630\u062c (Security Manager)</h3>
                        <p class="text-xs text-slate-500">\u062a\u062d\u062f\u064a\u062f \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u0645\u062d\u0645\u064a\u0629 \u0628\u0631\u0645\u0632 PIN \u0648\u062d\u062c\u0628\u0647\u0627 \u0639\u0646 \u0627\u0644\u0639\u0627\u0645\u0629</p>
                    </div>
                </div>
                <button type="button" onclick="closeSecurityModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Form Items List with Toggles -->
            <div class="overflow-y-auto py-4 space-y-2.5 flex-1 pr-1 pl-1">
                <?php foreach ($forms as $form): ?>
                <div class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-50 transition">
                    <div class="flex-1 min-w-0 pr-3">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="font-bold text-xs text-slate-900 truncate"><?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200/70 text-slate-600"><?= htmlspecialchars($form['code'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="font-mono text-[11px] text-slate-400 block truncate"><?= htmlspecialchars($form['filename'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <!-- Toggle Control -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        <span class="toggle-status-label text-xs font-bold <?= $form['is_protected'] ? 'text-amber-700' : 'text-slate-500' ?>" id="label_toggle_<?= md5($form['filename']) ?>">
                            <?= $form['is_protected'] ? u('\u0645\u062d\u0645\u064a') : u('\u0639\u0627\u0645') ?>
                        </span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer security-toggle" 
                                   data-filename="<?= htmlspecialchars($form['filename'], ENT_QUOTES, 'UTF-8') ?>" 
                                   data-label-id="label_toggle_<?= md5($form['filename']) ?>"
                                   <?= $form['is_protected'] ? 'checked' : '' ?>
                                   onchange="handleSecurityToggleChange(this)">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer Actions -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">\u0627\u0644\u062a\u063a\u064a\u064a\u0631\u0627\u062a \u062a\u0637\u0628\u0642 \u0641\u0648\u0631\u0627\u064b \u0639\u0644\u0649 \u0627\u0644\u062e\u0627\u062f\u0645</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeSecurityModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                        \u0625\u0644\u063a\u0627\u0621
                    </button>
                    <button type="button" id="btnSaveSecurity" onclick="saveSecuritySettings()" class="px-5 py-2 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm flex items-center gap-1.5">
                        <span>\u062d\u0641\u0638 \u0627\u0644\u062a\u063a\u064a\u064a\u0631\u0627\u062a</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Client-Side Search, Filter & Security Logic -->
    <script>
        // Check if portal is currently unlocked
        function isPortalUnlocked() {
            return sessionStorage.getItem('portal_unlocked') === 'true' || document.cookie.includes('vault_auth=1');
        }

        // Apply unlocked or locked state to cards and navigation
        function applyUnlockedState(unlocked) {
            const btnManage = document.getElementById('btnManageSecurity');
            const lockBtnLabel = document.getElementById('lockButtonLabel');
            const lockBtn = document.getElementById('btnMasterLock');

            const statForms = document.getElementById('statFormsCount');
            const statCats = document.getElementById('statCategoriesCount');
            const statSize = document.getElementById('statTotalSize');
            const totalRef = document.getElementById('totalVisibleRef');
            const allFilterCount = document.querySelector('.category-pill[data-filter="all"] .pill-count');
            const categoryPills = document.querySelectorAll('.category-pill');

            if (unlocked) {
                if (btnManage) btnManage.classList.remove('hidden');
                if (lockBtnLabel) lockBtnLabel.textContent = '\u0642\u0641\u0644 \u0627\u0644\u0646\u0645\u0627\u0630\u062c';
                if (lockBtn) {
                    lockBtn.classList.add('bg-amber-50', 'text-amber-800', 'border-amber-200');
                    lockBtn.classList.remove('bg-white', 'text-slate-700', 'border-slate-200');
                }

                if (statForms) statForms.textContent = statForms.getAttribute('data-all');
                if (statCats) statCats.textContent = statCats.getAttribute('data-all');
                if (statSize) statSize.textContent = statSize.getAttribute('data-all');
                if (totalRef) totalRef.textContent = totalRef.getAttribute('data-all');
                if (allFilterCount) allFilterCount.textContent = allFilterCount.getAttribute('data-all');

                categoryPills.forEach(p => {
                    const countEl = p.querySelector('.pill-count');
                    if (countEl) countEl.textContent = countEl.getAttribute('data-all');
                });
            } else {
                if (btnManage) btnManage.classList.add('hidden');
                if (lockBtnLabel) lockBtnLabel.textContent = '\u0642\u0641\u0644 \u0627\u0644\u0623\u0645\u0627\u0646';
                if (lockBtn) {
                    lockBtn.classList.remove('bg-amber-50', 'text-amber-800', 'border-amber-200');
                    lockBtn.classList.add('bg-white', 'text-slate-700', 'border-slate-200');
                }

                if (statForms) statForms.textContent = statForms.getAttribute('data-public');
                if (statCats) statCats.textContent = statCats.getAttribute('data-public');
                if (statSize) statSize.textContent = statSize.getAttribute('data-public');
                if (totalRef) totalRef.textContent = totalRef.getAttribute('data-public');
                if (allFilterCount) allFilterCount.textContent = allFilterCount.getAttribute('data-public');

                categoryPills.forEach(p => {
                    const countEl = p.querySelector('.pill-count');
                    if (countEl) countEl.textContent = countEl.getAttribute('data-public');
                });
            }

            if (typeof window.filterForms === 'function') {
                window.filterForms();
            }
        }

        function handleMasterLockClick() {
            if (isPortalUnlocked()) {
                sessionStorage.removeItem('portal_unlocked');
                document.cookie = 'vault_auth=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
                applyUnlockedState(false);
                showToast('\u2705 \u062a\u0645 \u0642\u0641\u0644 \u0627\u0644\u0646\u0645\u0627\u0630\u062c \u0627\u0644\u0645\u062d\u0645\u064a\u0629');
            } else {
                openMasterLockModal();
            }
        }

        function openMasterLockModal() {
            const modal = document.getElementById('modalMasterLock');
            const input = document.getElementById('masterLockInput');
            const errBox = document.getElementById('masterLockError');
            if (modal) {
                if (errBox) errBox.classList.add('hidden');
                if (input) input.value = '';
                modal.classList.remove('hidden');
                setTimeout(() => { if (input) input.focus(); }, 100);
            }
        }

        function closeMasterLockModal() {
            const modal = document.getElementById('modalMasterLock');
            if (modal) modal.classList.add('hidden');
        }

        async function submitMasterLock(e) {
            e.preventDefault();
            const input = document.getElementById('masterLockInput');
            const errBox = document.getElementById('masterLockError');
            const pin = input ? input.value.trim() : '';

            if (pin === '2026') {
                sessionStorage.setItem('portal_unlocked', 'true');
                document.cookie = 'vault_auth=1; path=/; max-age=2592000; SameSite=Lax';

                try {
                    fetch('guard.php?action=auth_session', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ pin: '2026' })
                    }).catch(() => {});
                } catch (err) {}

                if (errBox) errBox.classList.add('hidden');
                closeMasterLockModal();
                applyUnlockedState(true);
                showToast('\u2705 \u062a\u0645 \u0625\u0644\u063a\u0627\u0621 \u0627\u0644\u0642\u0641\u0644 \u0648\u0639\u0631\u0636 \u0643\u0627\u0641\u0629 \u0627\u0644\u0646\u0645\u0627\u0630\u062c');
            } else {
                if (errBox) errBox.classList.remove('hidden');
                if (input) {
                    input.value = '';
                    input.focus();
                }
            }
        }

        function openSecurityModal() {
            const modal = document.getElementById('modalManageSecurity');
            if (modal) modal.classList.remove('hidden');
        }

        function closeSecurityModal() {
            const modal = document.getElementById('modalManageSecurity');
            if (modal) modal.classList.add('hidden');
        }

        function handleSecurityToggleChange(checkbox) {
            const labelId = checkbox.getAttribute('data-label-id');
            const label = document.getElementById(labelId);
            if (label) {
                if (checkbox.checked) {
                    label.textContent = '\u0645\u062d\u0645\u064a';
                    label.className = 'toggle-status-label text-xs font-bold text-amber-700';
                } else {
                    label.textContent = '\u0639\u0627\u0645';
                    label.className = 'toggle-status-label text-xs font-bold text-slate-500';
                }
            }
        }

        async function saveSecuritySettings() {
            const btn = document.getElementById('btnSaveSecurity');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>\u23f3 \u062c\u0627\u0631\u064a \u0627\u0644\u062d\u0641\u0638...</span>';
            }

            const toggles = document.querySelectorAll('.security-toggle');
            const protectedFiles = [];
            toggles.forEach(t => {
                if (t.checked) {
                    const fn = t.getAttribute('data-filename');
                    if (fn) protectedFiles.push(fn);
                }
            });

            try {
                const response = await fetch('api.php?action=update_protection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ protected_files: protectedFiles })
                });
                const res = await response.json();
                if (res && res.status === 'success') {
                    closeSecurityModal();
                    showToast('\u2705 \u062a\u0645 \u062a\u062d\u062f\u064a\u062b \u0625\u0639\u062f\u0627\u062f\u0627\u062a \u0627\u0644\u062d\u0645\u0627\u064a\u0629 \u0628\u0646\u062c\u0627\u062d');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert('Error saving protection: ' + (res.message || 'Unknown error'));
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<span>\u062d\u0641\u0638 \u0627\u0644\u062a\u063a\u064a\u064a\u0631\u0627\u062a</span>';
                    }
                }
            } catch (err) {
                alert('Network error while saving settings.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span>\u062d\u0641\u0638 \u0627\u0644\u062a\u063a\u064a\u064a\u0631\u0627\u062a</span>';
                }
            }
        }

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

            window.filterForms = function() {
                const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
                const unlocked = isPortalUnlocked();

                if (clearBtn) {
                    if (query.length > 0) {
                        clearBtn.classList.remove('hidden');
                    } else {
                        clearBtn.classList.add('hidden');
                    }
                }

                let visibleCount = 0;

                cards.forEach(card => {
                    const isProtected = card.getAttribute('data-protected') === 'true';
                    if (isProtected && !unlocked) {
                        card.style.display = 'none';
                        return;
                    }

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
            };

            // Sorting handler
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

            if (searchInput) {
                searchInput.addEventListener('input', window.filterForms);
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    searchInput.focus();
                    window.filterForms();
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
                    window.filterForms();
                });
            }

            categoryPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    categoryPills.forEach(p => {
                        p.classList.remove('active', 'bg-emerald-600', 'text-white', 'shadow-xs');
                        p.classList.add('text-slate-600', 'bg-slate-100');
                    });
                    pill.classList.add('active', 'bg-emerald-600', 'text-white', 'shadow-xs');
                    pill.classList.remove('text-slate-600', 'bg-slate-100');

                    currentCategory = pill.getAttribute('data-filter') || 'all';
                    window.filterForms();
                });
            });

            window.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== searchInput && document.activeElement.tagName !== 'INPUT') {
                    e.preventDefault();
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                if (e.key === 'Escape') {
                    closeMasterLockModal();
                    closeSecurityModal();
                }
            });

            // Initialize unlocked state from storage/cookie
            applyUnlockedState(isPortalUnlocked());
        });

        function copyFormLink(relativeUrl) {
            const fullUrl = new URL(relativeUrl, window.location.href).href;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(fullUrl).then(() => showToast('\u062a\u0645 \u0646\u0633\u062e \u0627\u0644\u0631\u0627\u0628\u0637 \u0628\u0646\u062c\u0627\u062d!'));
            } else {
                const temp = document.createElement('input');
                temp.value = fullUrl;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                showToast('\u062a\u0645 \u0646\u0633\u062e \u0627\u0644\u0631\u0627\u0628\u0637 \u0628\u0646\u062c\u0627\u062d!');
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

        function decodeUnicodeText() {
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
            let node;
            while (node = walker.nextNode()) {
                if (node.nodeValue && node.nodeValue.includes('\\u')) {
                    node.nodeValue = node.nodeValue.replace(/\\u([0-9a-fA-F]{4})/g, (match, grp) => String.fromCharCode(parseInt(grp, 16)));
                }
            }
        }
        decodeUnicodeText();
    </script>
</body>
</html>
