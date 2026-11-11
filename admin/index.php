<?php
// backend/admin/index.php
$pageTitle = 'Dashboard Overview';
$activePage = 'dashboard';
$headerTitle = 'Dashboard Overview';
$headerSubtitle = 'Real-time overview of courses, private Dailymotion videos, and enrolled students';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch recent videos with course title
$recentVideos = [];
try {
    $stmt = $pdo->query("SELECT v.*, c.title AS course_title 
                         FROM videos v 
                         LEFT JOIN courses c ON v.course_id = c.id 
                         ORDER BY v.id DESC LIMIT 6");
    $recentVideos = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch recent students
$recentUsers = [];
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 6");
    $recentUsers = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch courses summary
$recentCourses = [];
try {
    $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM videos v WHERE v.course_id = c.id) AS video_count 
                         FROM courses c ORDER BY c.id DESC LIMIT 4");
    $recentCourses = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER (INDEPENDENT SCROLL) -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-8 max-w-7xl">
        <!-- 4 Stat Metric Cards (Light Theme) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Card 1: Courses -->
            <a href="/admin/courses/" class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md hover:border-indigo-300 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Courses</span>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="book-open" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900"><?= $serverStats['courses'] ?></span>
                    <span class="text-xs font-semibold text-indigo-600 flex items-center gap-0.5">
                        Manage <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 2: Chapters (Course -> Chapter -> Video) -->
            <a href="/admin/chapters/" class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md hover:border-indigo-300 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Course Chapters</span>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900"><?= $serverStats['chapters'] ?? 0 ?></span>
                    <span class="text-xs font-semibold text-indigo-600 flex items-center gap-0.5">
                        Chapters <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 3: Videos -->
            <a href="/admin/videos/" class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md hover:border-indigo-300 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Video Lessons</span>
                    <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="video" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900"><?= $serverStats['videos'] ?></span>
                    <span class="text-xs font-semibold text-violet-600 flex items-center gap-0.5">
                        Dailymotion <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>

            <!-- Card 4: Students -->
            <a href="/admin/users/" class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md hover:border-emerald-300 transition group block">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Enrolled Students</span>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900"><?= $serverStats['users'] ?></span>
                    <span class="text-xs font-semibold text-emerald-600 flex items-center gap-0.5">
                        Active App <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>
        </div>

        <!-- Quick Course Shortcuts Grid -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Featured Courses</h2>
                    <p class="text-xs text-slate-500">Live batches visible in the student mobile application</p>
                </div>
                <a href="/admin/courses/" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                    View all (<?= $serverStats['courses'] ?>) <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <?php if (empty($recentCourses)): ?>
                    <p class="text-xs text-slate-400 col-span-4 py-4 text-center">No courses created yet.</p>
                <?php else: ?>
                    <?php foreach ($recentCourses as $c): ?>
                        <div class="border border-slate-200/70 rounded-xl p-4 hover:border-indigo-300 transition bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($c['thumbnail'])): ?>
                                    <img src="<?= htmlspecialchars($c['thumbnail']) ?>" class="w-12 h-12 rounded-lg object-cover border border-slate-200" alt="">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm">
                                        <i data-lucide="book" class="w-6 h-6"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="truncate flex-1">
                                    <h3 class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($c['title']) ?></h3>
                                    <p class="text-xs text-slate-500 font-medium"><?= $c['video_count'] ?> Lessons</p>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs pt-3 border-t border-slate-200/60">
                                <span class="font-bold text-emerald-600">
                                    <?= ($c['price'] ?? 0) > 0 ? '₹' . number_format($c['price'], 2) : 'Free Access' ?>
                                </span>
                                <a href="/admin/videos/?course_id=<?= $c['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-semibold text-[11px]">
                                    Videos &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2 Column Section: Recent Videos & Recent Students -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Recent Videos -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Recently Added Video Lessons</h2>
                        <p class="text-xs text-slate-500">Dailymotion private videos synced for playback</p>
                    </div>
                    <a href="/admin/videos/" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        View All <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 font-semibold uppercase tracking-wider text-[10px]">
                                <th class="pb-3">Lesson</th>
                                <th class="pb-3">Course</th>
                                <th class="pb-3">Duration</th>
                                <th class="pb-3">Type</th>
                                <th class="pb-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <?php if (empty($recentVideos)): ?>
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">No video lessons found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentVideos as $v): ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 pr-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-7 rounded bg-slate-100 flex items-center justify-center shrink-0 border border-slate-200 text-slate-500">
                                                    <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                                </div>
                                                <div class="truncate max-w-[200px]">
                                                    <p class="font-bold text-slate-800 truncate"><?= htmlspecialchars($v['title']) ?></p>
                                                    <span class="text-[10px] text-slate-400 font-mono">ID: <?= htmlspecialchars($v['dailymotion_id']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-600 truncate max-w-[140px]">
                                            <?= htmlspecialchars($v['course_title'] ?? 'General') ?>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px]">
                                                <?= htmlspecialchars($v['duration']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <?php if ($v['is_free_preview']): ?>
                                                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-semibold">Free</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-semibold">Locked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="/admin/videos/?edit=<?= $v['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right 1 Col: Recent Enrolled Students -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Enrolled Students</h2>
                        <p class="text-xs text-slate-500">App registrations</p>
                    </div>
                    <a href="/admin/users/" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        View All <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="space-y-4">
                    <?php if (empty($recentUsers)): ?>
                        <p class="text-xs text-slate-400 py-4 text-center">No registered students yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $u): ?>
                            <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition border border-transparent hover:border-slate-200">
                                <div class="w-9 h-9 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-xs uppercase shrink-0">
                                    <?= substr($u['name'] ?? 'U', 0, 1) ?>
                                </div>
                                <div class="truncate flex-1">
                                    <p class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($u['name']) ?></p>
                                    <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($u['email']) ?></p>
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 font-semibold border border-emerald-200">
                                    Active
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="/admin/users/" class="w-full py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center block transition border border-slate-200">
                        Manage All Students &rarr;
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
