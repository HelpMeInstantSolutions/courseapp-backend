<?php
// backend/admin/videos/index.php
$pageTitle = 'Video Lessons';
$activePage = 'videos';
$headerTitle = 'Video Lessons Management';
$headerSubtitle = 'Dailymotion private stream sync, auto duration calculation and lesson sequencing';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter by course if selected
$selectedCourseId = (int)($_GET['course_id'] ?? 0);
$selectedChapterId = (int)($_GET['chapter_id'] ?? 0);

// Fetch all courses for dropdown and filtering
$courses = [];
try {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch active Dailymotion accounts
$dmAccounts = [];
try {
    $dmAccounts = $pdo->query("SELECT id, name, channel_name, uid, is_default FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch chapters for selected course (if any) for filter dropdown
$filterChapters = [];
if ($selectedCourseId > 0) {
    try {
        $chStmt = $pdo->prepare("SELECT id, title FROM chapters WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
        $chStmt->execute([$selectedCourseId]);
        $filterChapters = $chStmt->fetchAll();
    } catch (Exception $e) {}
}

// Query videos with Chapter & DM Account details
$videos = [];
try {
    $sql = "SELECT v.*, c.title AS course_title, ch.title AS chapter_title, a.name AS dm_account_name, a.channel_name AS dm_channel_name 
            FROM videos v 
            LEFT JOIN courses c ON v.course_id = c.id 
            LEFT JOIN chapters ch ON v.chapter_id = ch.id 
            LEFT JOIN dm_accounts a ON v.dm_account_id = a.id
            WHERE 1=1";
    $params = [];
    if ($selectedCourseId > 0) {
        $sql .= " AND v.course_id = ?";
        $params[] = $selectedCourseId;
    }
    if ($selectedChapterId > 0) {
        $sql .= " AND v.chapter_id = ?";
        $params[] = $selectedChapterId;
    }
    $sql .= " ORDER BY v.sort_order ASC, v.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER (INDEPENDENT SCROLL) -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Toolbar & Filter Bar -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3 flex-1 flex-wrap">
                <!-- Search -->
                <div class="relative flex-1 min-w-[200px]">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="videoSearch" oninput="filterVideos()" 
                           placeholder="Search by lesson title or DM ID..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Course Filter Dropdown -->
                <select id="courseFilter" onchange="applyCourseFilter(this.value)" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Chapter Filter Dropdown (visible when course is selected) -->
                <?php if ($selectedCourseId > 0 && !empty($filterChapters)): ?>
                    <select id="chapterFilter" onchange="applyChapterFilter(this.value)" 
                            class="px-3.5 py-2 bg-indigo-50/60 border border-indigo-200 rounded-xl text-xs font-medium text-indigo-900 focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                        <option value="0">All Chapters (<?= count($videos) ?>)</option>
                        <?php foreach ($filterChapters as $ch): ?>
                            <option value="<?= $ch['id'] ?>" <?= $selectedChapterId == $ch['id'] ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars($ch['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Add Video Button -->
            <button onclick="openVideoModal()" 
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i data-lucide="video" class="w-4 h-4"></i>
                <span>Add Video Lesson</span>
            </button>
        </div>

        <!-- Videos Data Table (Light Theme) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="videosTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4">Lesson Details</th>
                            <th class="py-3.5 px-4">Course</th>
                            <th class="py-3.5 px-4">Chapter</th>
                            <th class="py-3.5 px-4">Dailymotion ID</th>
                            <th class="py-3.5 px-4">Duration</th>
                            <th class="py-3.5 px-4">Access Type</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($videos)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="video-off" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No videos found</p>
                                    <p class="text-xs text-slate-400 mt-1">Add your first Dailymotion private lesson using the button above.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($videos as $v): ?>
                                <tr class="hover:bg-slate-50/70 transition video-row" 
                                    data-title="<?= strtolower(htmlspecialchars($v['title'])) ?>"
                                    data-dmid="<?= strtolower(htmlspecialchars($v['dailymotion_id'])) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        <?= htmlspecialchars($v['sort_order']) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-7 rounded bg-indigo-50 border border-indigo-200/70 text-indigo-600 flex items-center justify-center shrink-0">
                                                <i data-lucide="play" class="w-4 h-4 fill-current"></i>
                                            </div>
                                            <div class="truncate max-w-xs">
                                                <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($v['title']) ?></p>
                                                <span class="text-[10px] text-slate-400">ID: #<?= $v['id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 truncate max-w-[150px]">
                                        <span class="px-2 py-1 rounded-md bg-slate-100 text-slate-700 font-medium text-[11px]">
                                            <?= htmlspecialchars($v['course_title'] ?? 'General') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 truncate max-w-[140px]">
                                        <?php if (!empty($v['chapter_title'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-semibold text-[11px] border border-indigo-100/60">
                                                <i data-lucide="layers" class="w-3 h-3 text-indigo-500"></i>
                                                <?= htmlspecialchars($v['chapter_title']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[11px] italic">No chapter</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="font-mono text-[11px] font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                                <?= htmlspecialchars($v['dailymotion_id']) ?>
                                            </span>
                                            <?php if (!empty($v['dm_channel_name']) || !empty($v['dm_account_name'])): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-100" title="Connected DM Account">
                                                    <i data-lucide="cloud" class="w-2.5 h-2.5 text-sky-500"></i>
                                                    <?= htmlspecialchars($v['dm_channel_name'] ?: $v['dm_account_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                                            <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                            <?= htmlspecialchars($v['duration']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if ($v['is_free_preview']): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                                <i data-lucide="unlock" class="w-3 h-3"></i> Free Demo
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                                <i data-lucide="lock" class="w-3 h-3"></i> Enrolled Only
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button onclick="previewVideo('<?= htmlspecialchars($v['dailymotion_id']) ?>', '<?= addslashes(htmlspecialchars($v['title'])) ?>')" 
                                                    title="Test Play Stream" 
                                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick='editVideo(<?= json_encode($v) ?>)' 
                                                    title="Edit Lesson" 
                                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteVideo(<?= $v['id'] ?>, '<?= addslashes(htmlspecialchars($v['title'])) ?>')" 
                                                    title="Delete Lesson" 
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- ADD / EDIT VIDEO MODAL WITH AUTO DURATION CALCULATION -->
<div id="videoModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 id="videoModalTitle" class="text-base font-extrabold text-slate-900">Add Video Lesson</h3>
            <button onclick="closeVideoModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="videoForm" onsubmit="handleSaveVideo(event)" class="p-6 space-y-4">
            <input type="hidden" id="video_id" value="0">

            <!-- Course Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Assign To Course *</label>
                <select id="video_course_id" onchange="onCourseChanged(this.value)" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Chapter Selection (Course -> Chapter -> Video) -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Assign To Chapter</label>
                    <a href="/admin/chapters/" target="_blank" class="text-[10px] text-indigo-600 font-semibold hover:underline flex items-center gap-1">
                        <i data-lucide="layers" class="w-3 h-3"></i> Manage Chapters
                    </a>
                </div>
                <select id="video_chapter_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">-- General (No Specific Chapter) --</option>
                </select>
            </div>

            <!-- Dailymotion Account Selection (Multi-Account Support) -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Dailymotion Account</label>
                    <a href="/admin/dm_accounts/" target="_blank" class="text-[10px] text-indigo-600 font-semibold hover:underline flex items-center gap-1">
                        <i data-lucide="cloud-cog" class="w-3 h-3"></i> Manage Accounts
                    </a>
                </div>
                <select id="video_dm_account_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">⚡ Auto-Detect Account (Checks all connected accounts)</option>
                    <?php foreach ($dmAccounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>">
                            <?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['channel_name'] ?: $acc['uid']) ?>)<?= $acc['is_default'] ? ' ★ Default' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dailymotion ID with Auto Fetch Button -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Dailymotion Video ID / URL *</label>
                    <span class="text-[10px] text-indigo-600 font-semibold">e.g. x992mce</span>
                </div>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input type="text" id="video_dm_id" required 
                               placeholder="x992mce or full Dailymotion link" 
                               onblur="autoFetchOnBlur()"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    </div>
                    <button type="button" id="btnAutoFetch" onclick="fetchDailymotionDetails()" 
                            class="px-3.5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                        <span>Auto Fetch</span>
                    </button>
                </div>
                <p id="dmFetchStatus" class="text-[11px] mt-1.5 hidden"></p>
            </div>

            <!-- Video Title -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Lesson Title *</label>
                <input type="text" id="video_title" required 
                       placeholder="Lesson 1: Introduction to Formulas" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Duration (Auto calculated HH:MM:SS) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Duration (HH:MM:SS) *
                    </label>
                    <input type="text" id="video_duration" required value="15:00" 
                           placeholder="00:15:30" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Lesson Sequence #</label>
                    <input type="number" id="video_sort_order" value="1" min="1" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>
            </div>

            <!-- Free Preview Toggle -->
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <input type="checkbox" id="video_is_preview" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                <label for="video_is_preview" class="text-xs font-semibold text-slate-700 cursor-pointer select-none">
                    Allow Free Demo Preview (Playable without buying course)
                </label>
            </div>

            <!-- Thumbnail Preview Area (Appears on auto-fetch) -->
            <div id="thumbPreviewBox" class="hidden p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center gap-3">
                <img id="thumbImg" src="" alt="Thumbnail" class="w-16 h-10 object-cover rounded-lg border border-slate-200">
                <div class="text-xs truncate">
                    <p class="font-bold text-slate-800" id="thumbTextTitle"></p>
                    <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                        <i data-lucide="check-circle" class="w-3 h-3"></i> Private video stream verified
                    </span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeVideoModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitVideo" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition">
                    Save Video
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PLAYBACK PREVIEW MODAL -->
<div id="previewModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 id="previewTitle" class="text-sm font-bold text-slate-900 truncate">Video Preview</h3>
            <button onclick="closePreviewModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="aspect-video bg-black" id="previewContainer">
            <!-- Iframe injected via JS -->
        </div>
    </div>
</div>

<script>
    function filterVideos() {
        var query = document.getElementById('videoSearch').value.toLowerCase();
        var rows = document.querySelectorAll('.video-row');
        rows.forEach(function(row) {
            var title = row.getAttribute('data-title') || '';
            var dmid = row.getAttribute('data-dmid') || '';
            if (title.includes(query) || dmid.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function applyCourseFilter(courseId) {
        if (courseId > 0) {
            window.location.href = '/admin/videos/?course_id=' + courseId;
        } else {
            window.location.href = '/admin/videos/';
        }
    }

    function applyChapterFilter(chapterId) {
        var courseId = document.getElementById('courseFilter').value;
        var url = '/admin/videos/?';
        if (courseId > 0) url += 'course_id=' + courseId;
        if (chapterId > 0) url += (courseId > 0 ? '&' : '') + 'chapter_id=' + chapterId;
        window.location.href = url;
    }

    async function loadChaptersForCourse(courseId, selectedChapterId = 0) {
        var select = document.getElementById('video_chapter_id');
        select.innerHTML = '<option value="0">-- General (No Specific Chapter) --</option>';
        if (!courseId || courseId <= 0) return;
        try {
            var res = await fetch('/api/admin_crud.php?action=list_chapters&course_id=' + courseId);
            var json = await res.json();
            if (json.status && json.data) {
                json.data.forEach(function(ch) {
                    var opt = document.createElement('option');
                    opt.value = ch.id;
                    opt.textContent = ch.title;
                    if (ch.id == selectedChapterId) opt.selected = true;
                    select.appendChild(opt);
                });
            }
        } catch (e) {}
    }

    function onCourseChanged(courseId) {
        loadChaptersForCourse(courseId, 0);
    }

    function openVideoModal() {
        document.getElementById('video_id').value = '0';
        document.getElementById('video_title').value = '';
        document.getElementById('video_dm_id').value = '';
        document.getElementById('video_dm_account_id').value = '0';
        document.getElementById('video_duration').value = '15:00';
        document.getElementById('video_sort_order').value = '1';
        document.getElementById('video_is_preview').checked = false;
        document.getElementById('videoModalTitle').innerText = 'Add Video Lesson';
        document.getElementById('thumbPreviewBox').classList.add('hidden');
        document.getElementById('dmFetchStatus').classList.add('hidden');

        var currentCourse = document.getElementById('video_course_id').value;
        loadChaptersForCourse(currentCourse, <?= $selectedChapterId ?>);

        document.getElementById('videoModal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function editVideo(v) {
        document.getElementById('video_id').value = v.id;
        document.getElementById('video_course_id').value = v.course_id;
        document.getElementById('video_dm_account_id').value = v.dm_account_id || 0;
        loadChaptersForCourse(v.course_id, v.chapter_id || 0);
        document.getElementById('video_title').value = v.title || '';
        document.getElementById('video_dm_id').value = v.dailymotion_id || '';
        document.getElementById('video_duration').value = v.duration || '15:00';
        document.getElementById('video_sort_order').value = v.sort_order || 1;
        document.getElementById('video_is_preview').checked = v.is_free_preview == 1;
        document.getElementById('videoModalTitle').innerText = 'Edit Video Lesson';
        document.getElementById('thumbPreviewBox').classList.add('hidden');
        document.getElementById('dmFetchStatus').classList.add('hidden');
        document.getElementById('videoModal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeVideoModal() {
        document.getElementById('videoModal').classList.add('hidden');
    }

    // Auto-fetch trigger on blur if field has value and title is still empty
    function autoFetchOnBlur() {
        var dmId = document.getElementById('video_dm_id').value.trim();
        var currentTitle = document.getElementById('video_title').value.trim();
        if (dmId && !currentTitle) {
            fetchDailymotionDetails();
        }
    }

    // AUTO-FETCH DURATION & TITLE FROM DAILYMOTION API
    async function fetchDailymotionDetails() {
        var rawInput = document.getElementById('video_dm_id').value.trim();
        if (!rawInput) {
            showToast('Please enter a Dailymotion Video ID or URL first', 'error');
            return;
        }

        var btn = document.getElementById('btnAutoFetch');
        var statusEl = document.getElementById('dmFetchStatus');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> <span>Fetching...</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        statusEl.className = 'text-[11px] mt-1.5 text-indigo-600 font-medium block';
        statusEl.innerText = 'Connecting to Dailymotion API with owner credentials...';

        try {
            var accId = document.getElementById('video_dm_account_id').value;
            var res = await fetch('/api/admin_crud.php?action=fetch_dm_info&dailymotion_id=' + encodeURIComponent(rawInput) + '&account_id=' + encodeURIComponent(accId));
            var json = await res.json();

            if (json.status && json.data) {
                var d = json.data;
                // Auto-fill Clean ID
                document.getElementById('video_dm_id').value = d.id;
                // Auto-set Account if detected
                if (d.account_id) {
                    document.getElementById('video_dm_account_id').value = d.account_id;
                }
                // Auto-fill Title
                if (d.title) {
                    document.getElementById('video_title').value = d.title;
                }
                // Auto-fill Duration (Formatted HH:MM:SS)
                if (d.duration) {
                    document.getElementById('video_duration').value = d.duration;
                }
                // Show thumbnail & verification box
                if (d.thumbnail) {
                    document.getElementById('thumbImg').src = d.thumbnail;
                    document.getElementById('thumbTextTitle').innerText = d.title || d.id;
                    document.getElementById('thumbPreviewBox').classList.remove('hidden');
                }

                statusEl.className = 'text-[11px] mt-1.5 text-emerald-600 font-semibold block';
                statusEl.innerText = '✓ Found in ' + (d.account_name || 'Dailymotion') + ' • Duration ' + d.duration + ' (' + (d.private ? 'Private Video' : 'Public') + ')';
                showToast('Video title & duration (' + d.duration + ') auto-calculated from ' + (d.account_name || 'Dailymotion'), 'success');
            } else {
                // Direct client-side fallback if server proxy had network/firewall issue
                var cleanId = rawInput.trim();
                var m = cleanId.match(/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/);
                if (m) cleanId = m[1];
                var token = (json && json.data && json.data._token) ? json.data._token : '';

                statusEl.innerText = 'Server proxy busy, trying direct browser connection...';
                try {
                    var dHeaders = { 'Accept': 'application/json' };
                    if (token) dHeaders['Authorization'] = 'Bearer ' + token;
                    var dRes = await fetch('https://api.dailymotion.com/video/' + cleanId + '?fields=id,title,duration,thumbnail_url,private', {
                        headers: dHeaders
                    });
                    var dJson = await dRes.json();
                    if (dJson && dJson.id) {
                        var secs = parseInt(dJson.duration || 0);
                        var hrs = Math.floor(secs / 3600);
                        var mins = Math.floor((secs % 3600) / 60);
                        var s = secs % 60;
                        var fmt = (hrs > 0 ? String(hrs).padStart(2,'0') + ':' : '') + String(mins).padStart(2,'0') + ':' + String(s).padStart(2,'0');

                        document.getElementById('video_dm_id').value = dJson.id;
                        if (dJson.title) document.getElementById('video_title').value = dJson.title;
                        document.getElementById('video_duration').value = fmt;
                        if (dJson.thumbnail_url) {
                            document.getElementById('thumbImg').src = dJson.thumbnail_url;
                            document.getElementById('thumbTextTitle').innerText = dJson.title || dJson.id;
                            document.getElementById('thumbPreviewBox').classList.remove('hidden');
                        }
                        statusEl.className = 'text-[11px] mt-1.5 text-emerald-600 font-semibold block';
                        statusEl.innerText = '✓ Success! Duration ' + fmt + ' auto-calculated (' + (dJson.private ? 'Private' : 'Public') + ')';
                        showToast('Video title & duration (' + fmt + ') auto-calculated!', 'success');
                        return;
                    }
                } catch (e) {
                    console.log('Direct browser fetch error:', e);
                }

                statusEl.className = 'text-[11px] mt-1.5 text-rose-600 font-semibold block';
                statusEl.innerText = '✕ ' + (json.message || 'Could not fetch details');
                showToast(json.message || 'Could not fetch video info', 'error');
            }
        } catch (err) {
            statusEl.className = 'text-[11px] mt-1.5 text-rose-600 font-semibold block';
            statusEl.innerText = '✕ Network error while fetching Dailymotion info';
            showToast('Network error connecting to Dailymotion', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="sparkles" class="w-3.5 h-3.5"></i> <span>Auto Fetch</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    async function handleSaveVideo(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSubmitVideo');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        var payload = {
            id: document.getElementById('video_id').value,
            course_id: document.getElementById('video_course_id').value,
            chapter_id: document.getElementById('video_chapter_id').value,
            dm_account_id: document.getElementById('video_dm_account_id').value,
            title: document.getElementById('video_title').value,
            dailymotion_id: document.getElementById('video_dm_id').value,
            duration: document.getElementById('video_duration').value,
            sort_order: document.getElementById('video_sort_order').value,
            is_free_preview: document.getElementById('video_is_preview').checked ? 1 : 0
        };

        try {
            var res = await fetch('/api/admin_crud.php?action=save_video', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.message || 'Failed to save video', 'error');
            }
        } catch (err) {
            showToast('Network error while saving video', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Save Video';
        }
    }

    async function deleteVideo(id, title) {
        if (!confirm('Are you sure you want to delete lesson: "' + title + '"?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_video', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.message || 'Failed to delete video', 'error');
            }
        } catch (err) {
            showToast('Network error deleting video', 'error');
        }
    }

    // Playback Preview
    async function previewVideo(dmId, title) {
        document.getElementById('previewTitle').innerText = title + ' (' + dmId + ')';
        var container = document.getElementById('previewContainer');
        container.innerHTML = '<div class="w-full h-full flex items-center justify-center text-white text-xs">Loading secure stream...</div>';
        document.getElementById('previewModal').classList.remove('hidden');

        try {
            var res = await fetch('/api/video.php?id=' + encodeURIComponent(dmId));
            var json = await res.json();
            if (json.status && json.data && json.data.embed_url) {
                container.innerHTML = '<iframe src="' + json.data.embed_url + '" class="w-full h-full" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
            } else {
                container.innerHTML = '<div class="w-full h-full flex items-center justify-center text-rose-400 text-xs p-4 text-center">' + (json.message || 'Could not load private stream') + '</div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="w-full h-full flex items-center justify-center text-rose-400 text-xs">Failed to connect to playback endpoint</div>';
        }
    }

    function closePreviewModal() {
        document.getElementById('previewContainer').innerHTML = '';
        document.getElementById('previewModal').classList.add('hidden');
    }

    // Auto open modal if ?new=1
    if (window.location.search.includes('new=1')) {
        const urlParams = new URLSearchParams(window.location.search);
        const urlCourseId = urlParams.get('course_id');
        const urlChId = urlParams.get('chapter_id');
        if (urlCourseId) {
            document.getElementById('video_course_id').value = urlCourseId;
        }
        openVideoModal();
        if (urlCourseId && urlChId) {
            setTimeout(() => {
                loadChaptersForCourse(urlCourseId, urlChId);
            }, 150);
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
