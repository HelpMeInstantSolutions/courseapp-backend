<?php
// backend/admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../api/config.php';

// Quick server-side stats for zero-latency initial render
$serverStats = [
    'courses' => (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'videos' => (int)$pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn(),
    'categories' => (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

$pageTitle = $pageTitle ?? 'CourseApp Admin Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | courseapp.mybhiwani.in</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        darkbg: '#0B0F19',
                        darkcard: '#111827',
                        darkborder: '#1F2937'
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <!-- Toastify JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0B0F19; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 9999px; }
    </style>
</head>
<body class="bg-[#0B0F19] text-slate-100 antialiased font-sans min-h-screen flex"
      x-data="adminPortal()" x-init="init()" x-cloak>
