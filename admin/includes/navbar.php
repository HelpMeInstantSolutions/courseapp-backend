<?php
// backend/admin/includes/navbar.php
$headerTitle = $headerTitle ?? 'Dashboard';
$headerSubtitle = $headerSubtitle ?? 'Manage courses, private Dailymotion streams, and students';
?>
<!-- TOP NAVBAR (LIGHT THEME) -->
<header class="bg-white border-b border-slate-200 px-6 py-4 sticky top-0 z-30 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
    <div class="flex items-center gap-3">
        <!-- Mobile Sidebar Toggle -->
        <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')" 
                class="md:hidden p-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight"><?= htmlspecialchars($headerTitle) ?></h1>
            <p class="text-xs text-slate-500 font-medium"><?= htmlspecialchars($headerSubtitle) ?></p>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="flex items-center gap-2.5 flex-wrap">
        <a href="/admin/courses/?new=1" 
           class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-xl border border-indigo-200/80 transition duration-150">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>New Course</span>
        </a>

        <a href="/admin/videos/?new=1" 
           class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm shadow-indigo-500/20 transition duration-150">
            <i data-lucide="video" class="w-4 h-4"></i>
            <span>Add Video</span>
        </a>

        <button onclick="window.location.reload()" 
                title="Refresh Page" 
                class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition border border-slate-200">
            <i data-lucide="rotate-cw" class="w-4 h-4"></i>
        </button>
    </div>
</header>
