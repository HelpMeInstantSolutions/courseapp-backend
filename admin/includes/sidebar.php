<?php
// backend/admin/includes/sidebar.php
$activeNav = $activeNav ?? 'dashboard';
?>
<!-- REUSABLE DYNAMIC PHP SIDEBAR -->
<aside class="w-64 bg-[#111827] border-r border-[#1F2937] flex flex-col fixed inset-y-0 z-50">
    <!-- Brand / Logo Header -->
    <div class="h-16 flex items-center px-6 gap-3 border-b border-[#1F2937]">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center shadow-lg shadow-indigo-500/30 text-white font-bold">
            <i data-lucide="play" class="w-5 h-5 fill-current"></i>
        </div>
        <div>
            <h1 class="font-bold text-white tracking-wide text-base">CourseApp</h1>
            <p class="text-[11px] text-slate-400">courseapp.mybhiwani.in</p>
        </div>
    </div>

    <!-- Navigation Menu Items -->
    <nav class="p-4 space-y-1.5 flex-1 overflow-y-auto">
        <button @click="currentTab = 'dashboard'" 
                :class="currentTab === 'dashboard' ? 'bg-indigo-600/10 text-indigo-400 font-semibold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition-all duration-150 text-left">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
            <span>Dashboard</span>
        </button>

        <button @click="currentTab = 'courses'" 
                :class="currentTab === 'courses' ? 'bg-indigo-600/10 text-indigo-400 font-semibold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition-all duration-150 text-left">
            <i data-lucide="book-open" class="w-4 h-4"></i>
            <span>Manage Courses</span>
            <span class="ml-auto text-xs bg-slate-800 text-slate-300 px-2 py-0.5 rounded-full" 
                  x-text="stats.courses || '<?= $serverStats['courses'] ?>'">
                <?= $serverStats['courses'] ?>
            </span>
        </button>

        <button @click="currentTab = 'videos'" 
                :class="currentTab === 'videos' ? 'bg-indigo-600/10 text-indigo-400 font-semibold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition-all duration-150 text-left">
            <i data-lucide="video" class="w-4 h-4"></i>
            <span>Video Lessons</span>
            <span class="ml-auto text-xs bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded-full" 
                  x-text="stats.videos || '<?= $serverStats['videos'] ?>'">
                <?= $serverStats['videos'] ?>
            </span>
        </button>

        <button @click="currentTab = 'users'" 
                :class="currentTab === 'users' ? 'bg-indigo-600/10 text-indigo-400 font-semibold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition-all duration-150 text-left">
            <i data-lucide="users" class="w-4 h-4"></i>
            <span>Enrolled Students</span>
            <span class="ml-auto text-xs bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full" 
                  x-text="stats.users || '<?= $serverStats['users'] ?>'">
                <?= $serverStats['users'] ?>
            </span>
        </button>
    </nav>

    <!-- Admin Account & Session Controls -->
    <div class="p-4 border-t border-[#1F2937] bg-[#0E1424]">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-full bg-indigo-600/20 border border-indigo-500/40 text-indigo-400 flex items-center justify-center font-bold text-sm">
                A
            </div>
            <div class="truncate">
                <p class="text-xs font-semibold text-white"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Super Admin') ?></p>
                <p class="text-[10px] text-emerald-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> PHP Session Active
                </p>
            </div>
        </div>
        <button @click="logout()" 
                class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-medium text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-lg transition-colors">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            <span>Sign Out</span>
        </button>
    </div>
</aside>
