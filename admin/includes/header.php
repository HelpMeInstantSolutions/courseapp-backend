<?php
// backend/admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../api/config.php';

// Server-side database counts for instant display
$serverStats = [
    'courses' => 0,
    'chapters' => 0,
    'videos' => 0,
    'categories' => 0,
    'users' => 0,
    'dm_accounts' => 0,
];

try {
    $serverStats['courses'] = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $serverStats['chapters'] = (int)$pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn();
    $serverStats['videos'] = (int)$pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
    $serverStats['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $serverStats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $serverStats['dm_accounts'] = (int)$pdo->query("SELECT COUNT(*) FROM dm_accounts WHERE status = 1")->fetchColumn();
} catch (Exception $e) {
    // fallback if db error
}

$pageTitle = $pageTitle ?? 'Admin Dashboard';
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="h-full overflow-hidden bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | CourseApp Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS (Light Mode) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Toastify JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="h-full w-full overflow-hidden bg-slate-50 text-slate-800 antialiased flex flex-row">
    <!-- Client-side Auth Check -->
    <script>
        (function() {
            var token = localStorage.getItem('admin_logged');
            // If not logged in and not already on login page, redirect
            if (token !== 'true' && !window.location.pathname.includes('/login/')) {
                window.location.href = '/admin/login/';
            }
        })();
    </script>
