<?php
/**
 * ==============================================================================
 * CENTRAL ACCESS GATEKEEPER & TRAFFIC ROUTER (guard.php)
 * Al Manar Pipes Factory | Central Form Security Shield
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$masterPin = '2026';

// 1. Handle Background Authentication API call (for portal unlock integration)
if (isset($_GET['action']) && $_GET['action'] === 'auth_session') {
    header('Content-Type: application/json; charset=utf-8');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;
    $pin = trim($data['pin'] ?? '');

    if ($pin === $masterPin) {
        $_SESSION['vault_auth'] = true;
        setcookie('vault_auth', '1', time() + (86400 * 30), '/');
        echo json_encode(['status' => 'success', 'message' => 'Authenticated successfully.']);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid PIN provided.']);
    }
    exit;
}

// 2. Parse Requested File Parameter
$file = basename($_GET['file'] ?? '');
if (empty($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('404 Not Found: No file specified.');
}

$formsDir = __DIR__ . '/forms';
$filePath = $formsDir . '/' . $file;

// Safety check: ensure file exists and is a valid file
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('404 Not Found: Form file does not exist.');
}

// 3. Read Protected Forms Registry
$configFile = __DIR__ . '/config/protected_forms.json';
$protectedForms = [];
if (file_exists($configFile)) {
    $rawConfig = file_get_contents($configFile);
    $decodedConfig = json_decode($rawConfig, true);
    if (is_array($decodedConfig)) {
        $protectedForms = $decodedConfig;
    }
}

// 4. If File Is Not Protected, Immediately Serve It
if (!in_array($file, $protectedForms)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($filePath);
    exit;
}

// 5. Check Session & Cookie Authentication
$isAuthenticated = (!empty($_SESSION['vault_auth']) && $_SESSION['vault_auth'] === true)
                   || (isset($_COOKIE['vault_auth']) && $_COOKIE['vault_auth'] === '1');

// Handle PIN Submission on this Guard page
$authError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vault_pin'])) {
    $submittedPin = trim($_POST['vault_pin']);
    if ($submittedPin === $masterPin) {
        $_SESSION['vault_auth'] = true;
        setcookie('vault_auth', '1', time() + (86400 * 30), '/');
        $isAuthenticated = true;
        header('Location: guard.php?file=' . urlencode($file));
        exit;
    } else {
        $authError = true;
    }
}

// If Authenticated, Serve Protected File
if ($isAuthenticated) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($filePath);
    exit;
}

// 6. Render Standalone Glassmorphism PIN Prompt Screen (0% literal Arabic, Unicode escapes)
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>\u0645\u0646\u0637\u0642\u0629 \u0645\u062d\u0645\u064a\u0629 \u0628\u0631\u0645\u0632 \u0633\u0631\u064a | \u0645\u0635\u0646\u0639 \u0627\u0644\u0645\u0646\u0627\u0631</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Tajawal', sans-serif; }
    </style>
</head>
<body class="min-h-full bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100 flex items-center justify-center p-4 selection:bg-emerald-500 selection:text-white">
    <div class="relative w-full max-w-md">
        <!-- Ambient Background Glow -->
        <div class="absolute -top-16 -right-16 w-48 h-48 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-16 -left-16 w-48 h-48 bg-teal-500/15 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Glassmorphism Container Card -->
        <div class="relative backdrop-blur-xl bg-slate-900/80 border border-slate-700/60 rounded-3xl shadow-2xl p-7 sm:p-9 text-center">
            
            <!-- Shield Icon -->
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white mx-auto shadow-lg shadow-emerald-500/30 mb-5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>

            <!-- Title & Subtitle with Unicode Escaped Text -->
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight mb-2">
                \u0645\u0646\u0637\u0642\u0629 \u0645\u062d\u0645\u064a\u0629 \u0628\u0631\u0645\u0632 \u0633\u0631\u064a
            </h1>
            <p class="text-xs text-slate-400 font-medium mb-4">
                \u0647\u0630\u0627 \u0627\u0644\u0646\u0645\u0648\u0630\u062c \u0645\u062d\u0645\u064a \u0628\u0631\u0645\u0632 \u0623\u0645\u0627\u0646 \u062e\u0627\u0635. \u064a\u0631\u062c\u0649 \u0625\u062f\u062e\u0627\u0644 \u0631\u0645\u0632 PIN \u0644\u0644\u0645\u062a\u0627\u0628\u0639\u0629.
            </p>

            <!-- Form Filename Tag -->
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-slate-800/80 border border-slate-700 text-xs font-mono text-emerald-400 mb-6">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <!-- Error Notice -->
            <?php if ($authError): ?>
            <div class="mb-5 p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>\u0631\u0645\u0632 \u0627\u0644\u062d\u0645\u0627\u064a\u0629 \u063a\u064a\u0631 \u0635\u062d\u064a\u062d. \u064a\u0631\u062c\u0649 \u0625\u0639\u0627\u062f\u0629 \u0627\u0644\u0645\u062d\u0627\u0648\u0644\u0629.</span>
            </div>
            <?php endif; ?>

            <!-- PIN Form -->
            <form method="POST" action="guard.php?file=<?= urlencode($file) ?>" class="space-y-4">
                <div>
                    <label for="vault_pin" class="block text-xs font-bold text-slate-300 mb-2">
                        \u0631\u0645\u0632 \u0627\u0644\u0623\u0645\u0627\u0646 (PIN Code)
                    </label>
                    <input id="vault_pin" name="vault_pin" type="password" inputmode="numeric" autocomplete="current-password" required autofocus
                           placeholder="&#8226;&#8226;&#8226;&#8226;"
                           class="w-full text-center tracking-[0.5em] text-2xl font-bold py-3 px-4 rounded-xl border border-slate-700 bg-slate-800/90 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-lg shadow-emerald-900/40 transition active:scale-[0.98]">
                    \u062a\u0623\u0643\u064a\u062f \u0648\u0641\u062a\u062d \u0627\u0644\u0646\u0645\u0648\u0630\u062c
                </button>
            </form>

            <!-- Exit Link -->
            <div class="mt-6 pt-5 border-t border-slate-800">
                <a href="index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>\u0627\u0644\u0639\u0648\u062f\u0629 \u0644\u0644\u0648\u062d\u0629 \u0627\u0644\u062a\u062d\u0643\u0645 (Portal)</span>
                </a>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            \u0645\u0635\u0646\u0639 \u0627\u0644\u0645\u0646\u0627\u0631 \u0644\u0644\u0628\u0644\u0627\u0633\u062a\u064a\u0643 &copy; <?= date('Y') ?>
        </p>
    </div>

    <!-- Script to dynamically decode Unicode escapes in text nodes -->
    <script>
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
