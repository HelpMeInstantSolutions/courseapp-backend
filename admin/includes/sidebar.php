<?php
// backend/admin/includes/sidebar.php
$activePage = $activePage ?? 'dashboard';
?>
<!-- SIDEBAR COMPONENT (LIGHT THEME) -->
<aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0 md:sticky md:top-0 md:h-screen transition-all duration-300 z-40">
    <!-- Brand / Logo Header -->
    <div class="h-16 flex items-center px-6 gap-3 border-b border-slate-100 bg-white">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-brand-500 flex items-center justify-center shadow-md shadow-indigo-500/20 text-white font-bold">
            <i data-lucide="play" class="w-5 h-5 fill-current"></i>
        </div>
        <div class="flex-1 truncate">
            <a href="/admin/" class="font-bold text-slate-900 tracking-tight text-base hover:text-indigo-600 transition block truncate">
                CourseApp Admin
            </a>
            <p class="text-[11px] text-slate-400 font-medium truncate">courseapp.mybhiwani.in</p>
        </div>
    </div>

    <!-- Navigation Links (Multi-Page PHP) -->
    <nav class="p-4 space-y-1.5 flex-1 overflow-y-auto">
        <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Main Navigation</p>

        <!-- Dashboard Link -->
        <a href="/admin/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'dashboard' ? 'bg-indigo-50 text-indigo-700 font-semibold border-r-4 border-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4 <?= $activePage === 'dashboard' ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
            <span>Dashboard</span>
        </a>

        <!-- Courses Link -->
        <a href="/admin/courses/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'courses' ? 'bg-indigo-50 text-indigo-700 font-semibold border-r-4 border-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
            <i data-lucide="book-open" class="w-4 h-4 <?= $activePage === 'courses' ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Courses</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'courses' ? 'bg-indigo-200/60 text-indigo-800' : 'bg-slate-100 text-slate-600' ?>">
                <?= $serverStats['courses'] ?? 0 ?>
            </span>
        </a>

        <!-- Videos Link -->
        <a href="/admin/videos/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'videos' ? 'bg-indigo-50 text-indigo-700 font-semibold border-r-4 border-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
            <i data-lucide="video" class="w-4 h-4 <?= $activePage === 'videos' ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Video Lessons</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'videos' ? 'bg-indigo-200/60 text-indigo-800' : 'bg-slate-100 text-slate-600' ?>">
                <?= $serverStats['videos'] ?? 0 ?>
            </span>
        </a>

        <!-- Students Link -->
        <a href="/admin/users/" 
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 <?= $activePage === 'users' ? 'bg-indigo-50 text-indigo-700 font-semibold border-r-4 border-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
            <i data-lucide="users" class="w-4 h-4 <?= $activePage === 'users' ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
            <span class="flex-1">Students</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $activePage === 'users' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                <?= $serverStats['users'] ?? 0 ?>
            </span>
        </a>

        <div class="pt-4 pb-2">
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Shortcuts</p>
            <a href="/admin/videos/?new=1" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50 transition">
                <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-indigo-500"></i>
                <span>Add New Video</span>
            </a>
            <a href="/admin/courses/?new=1" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50 transition">
                <i data-lucide="folder-plus" class="w-3.5 h-3.5 text-emerald-500"></i>
                <span>Create New Course</span>
            </a>
        </div>
    </nav>

    <!-- Admin Account Profile & Logout Footer -->
    <div class="p-4 border-t border-slate-100 bg-slate-50/70">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-full bg-indigo-100 border border-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-sm">
                A
            </div>
            <div class="truncate flex-1">
                <p class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator') ?></p>
                <p class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> System Online
                </p>
            </div>
        </div>
        <button onclick="handleLogout()" 
                class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition duration-150">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            <span>Sign Out</span>
        </button>
    </div>
</aside>
