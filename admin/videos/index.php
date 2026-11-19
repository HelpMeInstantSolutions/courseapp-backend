<?php
// backend/admin/videos/index.php - Video Lessons Management (Batch -> Subject -> Chapter -> Video)
$pageTitle = 'Video Lessons';
$activePage = 'videos';
$headerTitle = 'Video Lessons Management';
$headerSubtitle = 'Dailymotion private stream sync, PDF notes, demo preview and lesson sequencing';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter params
$selectedCourseId = (int)($_GET['course_id'] ?? 0);
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);
$selectedChapterId = (int)($_GET['chapter_id'] ?? 0);

// Fetch all batches for dropdown and filtering
$courses = [];
try {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch all subjects
$allSubjects = [];
try {
    $allSubjects = $pdo->query("SELECT id, course_id, title FROM subjects WHERE status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch all chapters
$allChapters = [];
try {
    $allChapters = $pdo->query("SELECT id, course_id, subject_id, parent_id, title FROM chapters WHERE status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch active Dailymotion accounts
$dmAccounts = [];
try {
    $dmAccounts = $pdo->query("SELECT id, name, channel_name, uid, is_default FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Query videos with Batch, Subject, Chapter & DM Account details
$videos = [];
try {
    $sql = "SELECT v.*, c.title AS course_title, s.title AS subject_title, ch.title AS chapter_title, 
                   a.name AS dm_account_name, a.channel_name AS dm_channel_name 
            FROM videos v 
            LEFT JOIN courses c ON v.course_id = c.id 
            LEFT JOIN subjects s ON v.subject_id = s.id
            LEFT JOIN chapters ch ON v.chapter_id = ch.id 
            LEFT JOIN dm_accounts a ON v.dm_account_id = a.id
            WHERE 1=1";
    $params = [];
    if ($selectedCourseId > 0) {
        $sql .= " AND v.course_id = ?";
        $params[] = $selectedCourseId;
    }
    if ($selectedSubjectId > 0) {
        $sql .= " AND v.subject_id = ?";
        $params[] = $selectedSubjectId;
    }
    if ($selectedChapterId > 0) {
        $sql .= " AND v.chapter_id = ?";
        $params[] = $selectedChapterId;
    }
    $sql .= " ORDER BY v.course_id DESC, v.subject_id ASC, v.chapter_id ASC, v.sort_order ASC, v.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Toolbar & Filter Bar -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3 flex-1 flex-wrap">
                <!-- Search -->
                <div class="relative flex-1 min-w-[180px]">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="videoSearch" oninput="filterVideos()" 
                           placeholder="Search by lesson title or DM ID..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Batch Filter Dropdown -->
                <select id="courseFilter" onchange="applyFilters()" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">All Batches (<?= count($videos) ?>)</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Subject Filter Dropdown -->
                <select id="subjectFilter" onchange="applyFilters()" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">All Subjects</option>
                    <?php foreach ($allSubjects as $s): ?>
                        <?php if ($selectedCourseId == 0 || $selectedCourseId == $s['course_id']): ?>
                            <option value="<?= $s['id'] ?>" <?= $selectedSubjectId == $s['id'] ? 'selected' : '' ?>>
                                📚 <?= htmlspecialchars($s['title']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <!-- Chapter Filter Dropdown -->
                <?php if ($selectedCourseId > 0 || $selectedSubjectId > 0): ?>
                    <select id="chapterFilter" onchange="applyFilters()" 
                            class="px-3.5 py-2 bg-indigo-50/60 border border-indigo-200 rounded-xl text-xs font-medium text-indigo-900 focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                        <option value="0">All Chapters</option>
                        <?php foreach ($allChapters as $ch): ?>
                            <?php 
                            if ($selectedCourseId > 0 && $ch['course_id'] != $selectedCourseId) continue;
                            if ($selectedSubjectId > 0 && $ch['subject_id'] > 0 && $ch['subject_id'] != $selectedSubjectId) continue;
                            ?>
                            <option value="<?= $ch['id'] ?>" <?= $selectedChapterId == $ch['id'] ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars($ch['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Action Buttons: DM Import & Add Lesson -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Drag to reorder hint badge -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 text-[11px] font-semibold rounded-xl border border-indigo-100">
                    <i data-lucide="move" class="w-3.5 h-3.5 text-indigo-500"></i>
                    <span>Drag rows to change sort order</span>
                </span>

                <button onclick="openDmVideoPickerModal()" 
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <i data-lucide="cloud-download" class="w-4 h-4"></i>
                    <span>Fetch from DM Account</span>
                </button>

                <button onclick="openVideoModal()" 
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <i data-lucide="video" class="w-4 h-4"></i>
                    <span>Add Video Lesson</span>
                </button>
            </div>
        </div>

        <!-- Videos Data Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="videosTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-20 text-center">Sort</th>
                            <th class="py-3.5 px-4">Lesson Details</th>
                            <th class="py-3.5 px-4">Batch & Subject</th>
                            <th class="py-3.5 px-4">Chapter</th>
                            <th class="py-3.5 px-4">Dailymotion ID</th>
                            <th class="py-3.5 px-4 text-center">PDF Notes</th>
                            <th class="py-3.5 px-4">Duration</th>
                            <th class="py-3.5 px-4 text-center">Access</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium" id="videosTableBody">
                        <?php if (empty($videos)): ?>
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
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
                                    data-id="<?= $v['id'] ?>"
                                    data-title="<?= strtolower(htmlspecialchars($v['title'])) ?>"
                                    data-dmid="<?= strtolower(htmlspecialchars($v['dailymotion_id'])) ?>">
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <div class="video-drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-indigo-600 p-1 rounded hover:bg-indigo-50 transition" title="Drag row up or down to reorder video">
                                                <i data-lucide="grip-vertical" class="w-4 h-4"></i>
                                            </div>
                                            <span class="video-sort-badge font-mono font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded text-[11px]">
                                                #<?= htmlspecialchars($v['sort_order']) ?>
                                            </span>
                                        </div>
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
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 block truncate max-w-[150px]">
                                                <i data-lucide="graduation-cap" class="w-2.5 h-2.5 text-slate-400"></i>
                                                <?= htmlspecialchars($v['course_title'] ?? 'General') ?>
                                            </span>
                                            <?php if (!empty($v['subject_title'])): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 block truncate max-w-[150px]">
                                                    <i data-lucide="book-open" class="w-2.5 h-2.5 text-emerald-500"></i>
                                                    <?= htmlspecialchars($v['subject_title']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
                                    <td class="py-3.5 px-4 text-center">
                                        <?php if (!empty($v['pdf_url'])): ?>
                                            <a href="<?= htmlspecialchars($v['pdf_url']) ?>" target="_blank" 
                                               title="View PDF Notes"
                                               class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition">
                                                <i data-lucide="file-text" class="w-3 h-3 text-rose-500"></i>
                                                <span>PDF</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                                            <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                            <?= htmlspecialchars($v['duration']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <?php if ($v['is_free_preview']): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                                <i data-lucide="unlock" class="w-3 h-3"></i> Free Demo
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                                <i data-lucide="lock" class="w-3 h-3"></i> Enrolled
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
                                            <button onclick='editVideo(<?= json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
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

<!-- ADD / EDIT VIDEO MODAL -->
<div id="videoModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 id="videoModalTitle" class="text-base font-extrabold text-slate-900">Add Video Lesson</h3>
            <button onclick="closeVideoModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="videoForm" onsubmit="handleSaveVideo(event)" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" id="video_id" value="0">

            <!-- Batch Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Assign To Batch *</label>
                <select id="video_course_id" onchange="onBatchChangedInModal()" required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Batch --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject Selection -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Assign To Subject *</label>
                    <a href="/admin/subjects/" target="_blank" class="text-[10px] text-emerald-600 font-semibold hover:underline flex items-center gap-1">
                        <i data-lucide="book-open" class="w-3 h-3"></i> Manage Subjects
                    </a>
                </div>
                <select id="video_subject_id" onchange="onSubjectChangedInModal()" required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Subject --</option>
                </select>
            </div>

            <!-- Chapter Selection -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Assign To Chapter / Folder</label>
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
                            class="px-3.5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0" title="Auto-detect title and duration">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                        <span>Auto Fetch</span>
                    </button>
                    <button type="button" onclick="openDmVideoPickerModal(true)" 
                            class="px-3 py-2.5 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0" title="Browse and pick uploaded video directly from DM account">
                        <i data-lucide="cloud-download" class="w-3.5 h-3.5"></i>
                        <span>Pick Video</span>
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

            <!-- PDF Notes / Class Assignment URL -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Class PDF Notes / Assignment URL
                    <span class="text-[10px] text-slate-400 font-normal lowercase">(Optional)</span>
                </label>
                <input type="url" id="video_pdf_url" 
                       placeholder="https://example.com/notes/lesson-1-assignment.pdf" 
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

<!-- DAILYMOTION VIDEO BROWSER & BULK IMPORT MODAL -->
<div id="dmVideoPickerModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-3 md:p-6">
    <div class="bg-white rounded-3xl max-w-4xl w-full border border-slate-200 shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-sm shrink-0">
                    <i data-lucide="cloud-download" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span>Fetch & Import from Dailymotion</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Direct Account Sync</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Browse uploaded lessons sorted descending by date. Select videos with checkboxes to import them directly into your batch.</p>
                </div>
            </div>
            <button onclick="closeDmVideoPickerModal()" class="text-slate-400 hover:text-slate-600 p-2 rounded-xl hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Target Destination Bar (Batch -> Subject -> Chapter) -->
        <div class="px-6 py-3.5 bg-gradient-to-r from-indigo-50/90 via-sky-50/70 to-indigo-50/90 border-b border-indigo-100 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 flex-wrap flex-1">
                <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px] flex items-center gap-1 shrink-0">
                    <i data-lucide="folder-output" class="w-3.5 h-3.5 text-indigo-600"></i> Import Into:
                </span>
                
                <!-- Target Batch Dropdown -->
                <select id="pickerCourseId" onchange="onPickerCourseChanged()" 
                        class="px-3 py-1.5 bg-white border border-indigo-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:border-indigo-500 shadow-sm max-w-[200px]">
                    <option value="">-- Select Target Batch * --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Target Subject Dropdown -->
                <select id="pickerSubjectId" onchange="onPickerSubjectChanged()" 
                        class="px-3 py-1.5 bg-white border border-indigo-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:border-indigo-500 shadow-sm max-w-[180px]">
                    <option value="">-- Select Subject * --</option>
                </select>

                <!-- Target Chapter Dropdown -->
                <select id="pickerChapterId" 
                        class="px-3 py-1.5 bg-white border border-indigo-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:border-indigo-500 shadow-sm max-w-[220px]">
                    <option value="">-- Select Chapter * --</option>
                </select>

                <!-- Free Demo Checkbox -->
                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 cursor-pointer ml-1">
                    <input type="checkbox" id="pickerIsPreview" class="w-3.5 h-3.5 text-indigo-600 rounded border-slate-300 focus:ring-0">
                    <span>Free Demo</span>
                </label>
            </div>

            <!-- Import Action Button -->
            <button id="btnBulkImport" onclick="executeBulkImport()" disabled
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center justify-center gap-2 shrink-0">
                <i data-lucide="download-cloud" class="w-4 h-4"></i>
                <span id="bulkImportBtnText">Import Selected (0)</span>
            </button>
        </div>

        <!-- Filter & Search Sub-bar -->
        <div class="px-6 py-2.5 bg-white border-b border-slate-200/80 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 flex-wrap flex-1">
                <!-- Account Selector -->
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 font-semibold text-[11px]">Account:</span>
                    <select id="pickerAccountId" onchange="fetchPickerVideos(1)" 
                            class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-indigo-700 focus:outline-none focus:border-indigo-500 transition">
                        <?php foreach ($dmAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>">
                                <?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['channel_name'] ?: $acc['uid']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="pickerSearch" oninput="debouncePickerSearch()" 
                           placeholder="Search account video by title..." 
                           class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Filter Mode Dropdown -->
                <select id="pickerFilterMode" onchange="applyPickerLocalFilters()" 
                        class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 transition">
                    <option value="all">All Videos</option>
                    <option value="unimported" selected>Not Yet Added</option>
                    <option value="imported">Already Added</option>
                </select>

                <!-- Refresh Button -->
                <button onclick="fetchPickerVideos(1)" title="Refresh videos from DM" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 rounded-xl transition">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Select All Checkbox & Counter -->
            <div class="flex items-center gap-3 shrink-0">
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
                    <input type="checkbox" id="pickerSelectAll" onchange="togglePickerSelectAll(this)" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-0">
                    <span>Select All (<span id="pickerVisibleCount">0</span>)</span>
                </label>
                <span id="pickerSelectedBadge" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200/60 rounded-xl text-[11px] font-bold">
                    0 selected
                </span>
            </div>
        </div>

        <!-- Video Cards List Container -->
        <div class="flex-1 overflow-y-auto p-6 bg-slate-50/50" id="pickerVideosContainer">
            <div id="pickerLoading" class="py-16 text-center text-slate-400">
                <div class="inline-block animate-spin text-indigo-600 mb-2">
                    <i data-lucide="loader-2" class="w-8 h-8"></i>
                </div>
                <p class="text-xs font-semibold text-slate-600">Fetching uploaded videos from Dailymotion account...</p>
            </div>
            
            <div id="pickerVideosList" class="space-y-2.5 hidden">
                <!-- Video rows will be injected here via JavaScript -->
            </div>

            <div id="pickerEmpty" class="py-16 text-center text-slate-400 hidden">
                <i data-lucide="film" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-700 text-sm">No videos found</p>
                <p class="text-xs text-slate-400 mt-0.5">No uploaded videos match the current search or filter.</p>
            </div>
        </div>

        <!-- Modal Footer with Pagination / Status -->
        <div class="px-6 py-3 border-t border-slate-100 bg-white flex items-center justify-between text-xs text-slate-500">
            <div id="pickerTotalStatus" class="font-medium">
                Showing videos descending by upload date
            </div>
            <div class="flex items-center gap-2">
                <button id="pickerLoadMoreBtn" onclick="fetchNextPickerPage()" class="hidden px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                    Load Next 50 Videos
                </button>
                <button onclick="closeDmVideoPickerModal()" class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition">
                    Cancel / Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const allSubjectsData = <?= json_encode($allSubjects) ?>;
const allChaptersData = <?= json_encode($allChapters) ?>;

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

function applyFilters() {
    var cId = document.getElementById('courseFilter').value;
    var sId = document.getElementById('subjectFilter').value;
    var chEl = document.getElementById('chapterFilter');
    var chId = chEl ? chEl.value : 0;

    var url = '/admin/videos/?';
    if (cId > 0) url += 'course_id=' + cId + '&';
    if (sId > 0) url += 'subject_id=' + sId + '&';
    if (chId > 0) url += 'chapter_id=' + chId;
    window.location.href = url.replace(/&$/, '');
}

function populateModalSubjects(batchId, selectedSubId = 0) {
    var subSelect = document.getElementById('video_subject_id');
    subSelect.innerHTML = '<option value="">-- Choose Subject --</option>';
    
    var filtered = allSubjectsData.filter(s => batchId == 0 || s.course_id == batchId);
    filtered.forEach(s => {
        var opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.title;
        if (s.id == selectedSubId) opt.selected = true;
        subSelect.appendChild(opt);
    });
}

function populateModalChapters(batchId, subjectId = 0, selectedChId = 0) {
    var chSelect = document.getElementById('video_chapter_id');
    chSelect.innerHTML = '<option value="0">-- General (No Specific Chapter) --</option>';

    var filtered = allChaptersData.filter(ch => {
        if (batchId > 0 && ch.course_id != batchId) return false;
        if (subjectId > 0 && ch.subject_id > 0 && ch.subject_id != subjectId) return false;
        return true;
    });

    filtered.forEach(ch => {
        var opt = document.createElement('option');
        opt.value = ch.id;
        var prefix = (ch.parent_id > 0) ? '  ↳ 📁 ' : '📁 ';
        opt.textContent = prefix + ch.title;
        if (ch.id == selectedChId) opt.selected = true;
        chSelect.appendChild(opt);
    });
}

function onBatchChangedInModal() {
    var bId = parseInt(document.getElementById('video_course_id').value) || 0;
    populateModalSubjects(bId, 0);
    populateModalChapters(bId, 0, 0);
}

function onSubjectChangedInModal() {
    var bId = parseInt(document.getElementById('video_course_id').value) || 0;
    var sId = parseInt(document.getElementById('video_subject_id').value) || 0;
    populateModalChapters(bId, sId, 0);
}

function openVideoModal() {
    document.getElementById('video_id').value = '0';
    document.getElementById('video_title').value = '';
    document.getElementById('video_dm_id').value = '';
    document.getElementById('video_pdf_url').value = '';
    document.getElementById('video_dm_account_id').value = '0';
    document.getElementById('video_duration').value = '15:00';
    document.getElementById('video_sort_order').value = '1';
    document.getElementById('video_is_preview').checked = false;
    document.getElementById('videoModalTitle').innerText = 'Add Video Lesson';
    document.getElementById('thumbPreviewBox').classList.add('hidden');
    document.getElementById('dmFetchStatus').classList.add('hidden');

    var currentBatch = parseInt(document.getElementById('courseFilter').value) || 0;
    if (currentBatch > 0) {
        document.getElementById('video_course_id').value = currentBatch;
    }
    var currentSub = parseInt(document.getElementById('subjectFilter').value) || 0;
    populateModalSubjects(currentBatch, currentSub);
    populateModalChapters(currentBatch, currentSub, 0);

    document.getElementById('videoModal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function editVideo(v) {
    document.getElementById('video_id').value = v.id;
    document.getElementById('video_course_id').value = v.course_id;
    populateModalSubjects(v.course_id, v.subject_id || 0);
    populateModalChapters(v.course_id, v.subject_id || 0, v.chapter_id || 0);

    document.getElementById('video_dm_account_id').value = v.dm_account_id || 0;
    document.getElementById('video_title').value = v.title || '';
    document.getElementById('video_dm_id').value = v.dailymotion_id || '';
    document.getElementById('video_pdf_url').value = v.pdf_url || '';
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

function autoFetchOnBlur() {
    var dmId = document.getElementById('video_dm_id').value.trim();
    var currentTitle = document.getElementById('video_title').value.trim();
    if (dmId && !currentTitle) {
        fetchDailymotionDetails();
    }
}

async function fetchDailymotionDetails() {
    var rawInput = document.getElementById('video_dm_id').value.trim();
    if (!rawInput) {
        if (typeof showToast === 'function') showToast('Please enter a Dailymotion Video ID or URL first', 'error');
        else alert('Please enter a Dailymotion Video ID or URL first');
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
            document.getElementById('video_dm_id').value = d.id;
            if (d.account_id) {
                document.getElementById('video_dm_account_id').value = d.account_id;
            }
            if (d.title) {
                document.getElementById('video_title').value = d.title;
            }
            if (d.duration) {
                document.getElementById('video_duration').value = d.duration;
            }
            if (d.thumbnail) {
                document.getElementById('thumbImg').src = d.thumbnail;
                document.getElementById('thumbTextTitle').innerText = d.title || d.id;
                document.getElementById('thumbPreviewBox').classList.remove('hidden');
            }

            statusEl.className = 'text-[11px] mt-1.5 text-emerald-600 font-semibold block';
            statusEl.innerText = '✓ Found in ' + (d.account_name || 'Dailymotion') + ' • Duration ' + d.duration + ' (' + (d.private ? 'Private Video' : 'Public') + ')';
        } else {
            statusEl.className = 'text-[11px] mt-1.5 text-rose-600 font-semibold block';
            statusEl.innerText = '✕ ' + (json.message || 'Could not fetch details');
        }
    } catch (err) {
        statusEl.className = 'text-[11px] mt-1.5 text-rose-600 font-semibold block';
        statusEl.innerText = '✕ Network error while fetching Dailymotion info';
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
        subject_id: document.getElementById('video_subject_id').value,
        chapter_id: document.getElementById('video_chapter_id').value,
        dm_account_id: document.getElementById('video_dm_account_id').value,
        title: document.getElementById('video_title').value,
        dailymotion_id: document.getElementById('video_dm_id').value,
        duration: document.getElementById('video_duration').value,
        pdf_url: document.getElementById('video_pdf_url').value,
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
            window.location.reload();
        } else {
            alert(json.message || 'Failed to save video');
            btn.disabled = false;
            btn.innerText = 'Save Video';
        }
    } catch (err) {
        alert('Network error while saving video: ' + err.message);
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
            window.location.reload();
        } else {
            alert(json.message || 'Failed to delete video');
        }
    } catch (err) {
        alert('Network error deleting video: ' + err.message);
    }
}

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
    const urlSubId = urlParams.get('subject_id');
    const urlChId = urlParams.get('chapter_id');
    openVideoModal();
    if (urlCourseId) {
        document.getElementById('video_course_id').value = urlCourseId;
        populateModalSubjects(urlCourseId, urlSubId || 0);
        populateModalChapters(urlCourseId, urlSubId || 0, urlChId || 0);
    }
}

// -------------------------------------------------------------
// TOAST NOTIFICATIONS HELPER
// -------------------------------------------------------------
function showSortToast(msg, type = 'info') {
    let toast = document.getElementById('sortOrderToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'sortOrderToast';
        toast.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-2.5 rounded-2xl shadow-xl text-xs font-bold transition-all duration-300';
        document.body.appendChild(toast);
    }
    if (type === 'success') {
        toast.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-2.5 rounded-2xl shadow-xl text-xs font-bold bg-emerald-600 text-white transition-all duration-300 translate-y-0 opacity-100';
    } else if (type === 'error') {
        toast.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-2.5 rounded-2xl shadow-xl text-xs font-bold bg-rose-600 text-white transition-all duration-300 translate-y-0 opacity-100';
    } else {
        toast.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-2.5 rounded-2xl shadow-xl text-xs font-bold bg-slate-900 text-white transition-all duration-300 translate-y-0 opacity-100';
    }
    toast.innerHTML = msg;
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4');
    }, 2800);
}

// -------------------------------------------------------------
// SORTABLE JS DRAG & DROP REORDERING FOR VIDEOS
// -------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('videosTableBody');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.video-drag-handle',
            animation: 180,
            ghostClass: 'bg-indigo-50/90',
            chosenClass: 'bg-indigo-100/60',
            onEnd: async function() {
                const rows = Array.from(tbody.querySelectorAll('tr.video-row'));
                const order = [];
                rows.forEach((row, idx) => {
                    const newSort = idx + 1;
                    const vId = parseInt(row.getAttribute('data-id')) || 0;
                    const badge = row.querySelector('.video-sort-badge');
                    if (badge) badge.textContent = '#' + newSort;
                    if (vId > 0) {
                        order.push({ id: vId, sort_order: newSort });
                    }
                });

                showSortToast('⏳ Saving video sequence...', 'info');
                try {
                    const res = await fetch('/api/admin_crud.php?action=reorder_videos', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order: order })
                    });
                    const json = await res.json();
                    if (json.status) {
                        showSortToast('✅ Video sequence saved!', 'success');
                    } else {
                        showSortToast('❌ ' + (json.message || 'Error updating order'), 'error');
                    }
                } catch (e) {
                    showSortToast('❌ Network error: ' + e.message, 'error');
                }
            }
        });
    }
});

// -------------------------------------------------------------
// DAILYMOTION VIDEO BROWSER & BULK IMPORT LOGIC
// -------------------------------------------------------------
let pickerVideosData = [];
let pickerCurrentPage = 1;
let pickerSearchTimer = null;
window._pickerSingleFillMode = false;

function openDmVideoPickerModal(forSingleFill = false) {
    window._pickerSingleFillMode = forSingleFill;
    const modal = document.getElementById('dmVideoPickerModal');
    modal.classList.remove('hidden');

    // Auto-select current filtered batch, subject & chapter if available
    const curCourse = document.getElementById('courseFilter') ? parseInt(document.getElementById('courseFilter').value) : 0;
    const curSub = document.getElementById('subjectFilter') ? parseInt(document.getElementById('subjectFilter').value) : 0;
    const curCh = document.getElementById('chapterFilter') ? parseInt(document.getElementById('chapterFilter').value) : 0;

    const pickerCourse = document.getElementById('pickerCourseId');
    if (curCourse > 0) {
        pickerCourse.value = curCourse;
    }
    populatePickerSubjects(pickerCourse.value, curSub);
    populatePickerChapters(pickerCourse.value, curSub, curCh);

    // Fetch videos from selected DM account
    fetchPickerVideos(1);
}

function closeDmVideoPickerModal() {
    document.getElementById('dmVideoPickerModal').classList.add('hidden');
    window._pickerSingleFillMode = false;
}

function onPickerCourseChanged() {
    const cId = document.getElementById('pickerCourseId').value;
    populatePickerSubjects(cId, 0);
    populatePickerChapters(cId, 0, 0);
}

function onPickerSubjectChanged() {
    const cId = document.getElementById('pickerCourseId').value;
    const sId = document.getElementById('pickerSubjectId').value;
    populatePickerChapters(cId, sId, 0);
}

function populatePickerSubjects(courseId, selectedSubId = 0) {
    const sel = document.getElementById('pickerSubjectId');
    sel.innerHTML = '<option value="">-- Select Subject * --</option>';
    const filtered = allSubjectsData.filter(s => courseId == 0 || s.course_id == courseId);
    filtered.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.title;
        if (s.id == selectedSubId) opt.selected = true;
        sel.appendChild(opt);
    });
}

function populatePickerChapters(courseId, subjectId = 0, selectedChId = 0) {
    const sel = document.getElementById('pickerChapterId');
    sel.innerHTML = '<option value="">-- Select Chapter * --</option>';
    const filtered = allChaptersData.filter(ch => {
        if (courseId > 0 && ch.course_id != courseId) return false;
        if (subjectId > 0 && ch.subject_id > 0 && ch.subject_id != subjectId) return false;
        return true;
    });
    filtered.forEach(ch => {
        const opt = document.createElement('option');
        opt.value = ch.id;
        const prefix = (ch.parent_id > 0) ? '  ↳ 📁 ' : '📁 ';
        opt.textContent = prefix + ch.title;
        if (ch.id == selectedChId) opt.selected = true;
        sel.appendChild(opt);
    });
}

function debouncePickerSearch() {
    clearTimeout(pickerSearchTimer);
    pickerSearchTimer = setTimeout(() => {
        fetchPickerVideos(1);
    }, 350);
}

async function fetchPickerVideos(page = 1) {
    pickerCurrentPage = page;
    const accountId = document.getElementById('pickerAccountId').value;
    const search = document.getElementById('pickerSearch').value.trim();

    const loadingEl = document.getElementById('pickerLoading');
    const listEl = document.getElementById('pickerVideosList');
    const emptyEl = document.getElementById('pickerEmpty');
    const loadMoreBtn = document.getElementById('pickerLoadMoreBtn');

    if (page === 1) {
        loadingEl.classList.remove('hidden');
        listEl.classList.add('hidden');
        emptyEl.classList.add('hidden');
    }

    try {
        const url = '/api/admin_crud.php?action=fetch_dm_account_videos&account_id=' + accountId + 
                    '&page=' + page + '&limit=50&search=' + encodeURIComponent(search);
        const res = await fetch(url);
        const json = await res.json();

        loadingEl.classList.add('hidden');

        if (!json.status) {
            emptyEl.classList.remove('hidden');
            emptyEl.querySelector('p').textContent = json.message || 'Error fetching videos';
            return;
        }

        const data = json.data;

        // If backend initiated direct client browser sync (bypasses server network limitations)
        if (data.direct_browser && data.token) {
            try {
                const dmRes = await fetch(data.api_url, {
                    headers: { 'Authorization': 'Bearer ' + data.token }
                });
                const dmJson = await dmRes.json();
                if (dmJson.error) {
                    emptyEl.classList.remove('hidden');
                    emptyEl.querySelector('p').textContent = 'Dailymotion error: ' + (dmJson.error.message || 'Error');
                    return;
                }

                const rawList = dmJson.list || [];
                const exMap = data.existing_map || {};
                const formatted = rawList.map(v => {
                    const secs = parseInt(v.duration) || 0;
                    const hrs = Math.floor(secs / 3600);
                    const mins = Math.floor((secs % 3600) / 60);
                    const rem = secs % 60;
                    const durStr = (hrs > 0) 
                        ? String(hrs).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ':' + String(rem).padStart(2, '0')
                        : String(mins).padStart(2, '0') + ':' + String(rem).padStart(2, '0');
                    
                    const ctime = (v.created_time || 0) * 1000;
                    const dateStr = ctime ? new Date(ctime).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                    const isImported = !!exMap[v.id];

                    return {
                        id: v.id,
                        title: v.title || 'Untitled Lesson',
                        duration: durStr,
                        duration_seconds: secs,
                        thumbnail_url: v.thumbnail_240_url || ('https://www.dailymotion.com/thumbnail/video/' + v.id),
                        created_time: v.created_time || 0,
                        created_formatted: dateStr,
                        private: !!v.private,
                        account_id: data.account_id,
                        account_name: data.account_name,
                        is_imported: isImported,
                        imported_info: isImported ? exMap[v.id] : null
                    };
                });

                if (page === 1) {
                    pickerVideosData = formatted;
                } else {
                    pickerVideosData = pickerVideosData.concat(formatted);
                }

                document.getElementById('pickerTotalStatus').textContent = 
                    'Total ' + (dmJson.total || pickerVideosData.length) + ' videos on account (sorted newest first)';

                if (dmJson.has_more) {
                    loadMoreBtn.classList.remove('hidden');
                } else {
                    loadMoreBtn.classList.add('hidden');
                }

                renderPickerVideos();
                return;
            } catch (dmErr) {
                emptyEl.classList.remove('hidden');
                emptyEl.querySelector('p').textContent = 'Direct sync error: ' + dmErr.message;
                return;
            }
        }

        if (page === 1) {
            pickerVideosData = data.videos || [];
        } else {
            pickerVideosData = pickerVideosData.concat(data.videos || []);
        }

        document.getElementById('pickerTotalStatus').textContent = 
            'Total ' + data.total + ' videos on account (sorted newest first)';

        if (data.has_more) {
            loadMoreBtn.classList.remove('hidden');
        } else {
            loadMoreBtn.classList.add('hidden');
        }

        renderPickerVideos();

    } catch (err) {
        loadingEl.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        emptyEl.querySelector('p').textContent = 'Network error: ' + err.message;
    }
}

function fetchNextPickerPage() {
    fetchPickerVideos(pickerCurrentPage + 1);
}

function applyPickerLocalFilters() {
    renderPickerVideos();
}

function renderPickerVideos() {
    const listEl = document.getElementById('pickerVideosList');
    const emptyEl = document.getElementById('pickerEmpty');
    const filterMode = document.getElementById('pickerFilterMode').value;
    const query = document.getElementById('pickerSearch').value.trim().toLowerCase();

    listEl.innerHTML = '';

    const filtered = pickerVideosData.filter(v => {
        if (filterMode === 'unimported' && v.is_imported) return false;
        if (filterMode === 'imported' && !v.is_imported) return false;
        if (query && !v.title.toLowerCase().includes(query) && !v.id.toLowerCase().includes(query)) return false;
        return true;
    });

    document.getElementById('pickerVisibleCount').textContent = filtered.length;

    if (filtered.length === 0) {
        listEl.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        updatePickerSelectionBadge();
        return;
    }

    emptyEl.classList.add('hidden');
    listEl.classList.remove('hidden');

    filtered.forEach((v, idx) => {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'picker-video-item flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-white rounded-2xl border transition duration-150 ' + 
                            (v.is_imported ? 'border-slate-200/70 bg-slate-50/40' : 'border-slate-200/90 hover:border-indigo-300 hover:shadow-sm');
        itemDiv.setAttribute('data-dmid', v.id);

        const thumbUrl = v.thumbnail_url || 'https://www.dailymotion.com/thumbnail/video/' + v.id;

        itemDiv.innerHTML = `
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <!-- Checkbox -->
                <input type="checkbox" class="picker-item-cb w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-0 shrink-0 cursor-pointer"
                       value="${v.id}"
                       data-index="${idx}"
                       onchange="onPickerItemCheckChanged()">

                <!-- Thumbnail with duration pill -->
                <div class="relative w-24 h-14 rounded-xl overflow-hidden bg-slate-900 shrink-0 shadow-sm">
                    <img src="${thumbUrl}" alt="" class="w-full h-full object-cover">
                    <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded bg-black/80 text-white font-mono text-[9px] font-semibold">
                        ${v.duration}
                    </span>
                </div>

                <!-- Details -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-xs text-slate-900 truncate" title="${v.title}">
                            ${v.title}
                        </span>
                        ${v.private ? '<span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold text-[9px] border border-slate-200">🔒 Private</span>' : ''}
                    </div>

                    <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 flex-wrap">
                        <span class="font-mono text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded font-semibold text-[10px]">
                            ${v.id}
                        </span>
                        <span>•</span>
                        <span class="text-slate-400 text-[10px]">
                            📅 ${v.created_formatted || 'Recently uploaded'}
                        </span>
                        ${v.is_imported ? `
                            <span>•</span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                <i data-lucide="check" class="w-3 h-3"></i> Already in: ${v.imported_info.course_title || 'Course'} / ${v.imported_info.chapter_title || 'Chapter'}
                            </span>
                        ` : ''}
                    </div>
                </div>
            </div>

            <!-- Single Select / Fill Button -->
            <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                <button type="button" onclick="usePickerVideoInSingleForm(${JSON.stringify(v).replace(/"/g, '&quot;')})" 
                        class="px-3 py-1.5 text-[11px] font-bold rounded-xl text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition flex items-center gap-1">
                    <i data-lucide="arrow-right-circle" class="w-3.5 h-3.5"></i>
                    <span>Fill in Form</span>
                </button>
            </div>
        `;

        listEl.appendChild(itemDiv);
    });

    if (window.lucide) {
        lucide.createIcons();
    }

    updatePickerSelectionBadge();
}

function togglePickerSelectAll(masterCb) {
    const cbs = document.querySelectorAll('.picker-item-cb');
    cbs.forEach(cb => cb.checked = masterCb.checked);
    updatePickerSelectionBadge();
}

function onPickerItemCheckChanged() {
    updatePickerSelectionBadge();
}

function updatePickerSelectionBadge() {
    const checked = document.querySelectorAll('.picker-item-cb:checked');
    const count = checked.length;
    document.getElementById('pickerSelectedBadge').textContent = count + ' selected';
    document.getElementById('bulkImportBtnText').textContent = 'Import Selected (' + count + ')';
    
    const importBtn = document.getElementById('btnBulkImport');
    importBtn.disabled = (count === 0);
}

function usePickerVideoInSingleForm(video) {
    // Populate the single Add Video form fields
    document.getElementById('video_dm_id').value = video.id;
    document.getElementById('video_title').value = video.title;
    document.getElementById('video_duration').value = video.duration;
    if (video.account_id) {
        document.getElementById('video_dm_account_id').value = video.account_id;
    }

    closeDmVideoPickerModal();

    // If add modal not open, open it
    openVideoModal();
    const statusEl = document.getElementById('dmFetchStatus');
    statusEl.className = 'text-[11px] mt-1.5 text-emerald-600 font-semibold block';
    statusEl.textContent = '✅ Selected from DM: "' + video.title + '" (' + video.duration + ')';
}

async function executeBulkImport() {
    const courseId = parseInt(document.getElementById('pickerCourseId').value) || 0;
    const subjectId = parseInt(document.getElementById('pickerSubjectId').value) || 0;
    const chapterId = parseInt(document.getElementById('pickerChapterId').value) || 0;
    const isPreview = document.getElementById('pickerIsPreview').checked ? 1 : 0;
    const accountId = parseInt(document.getElementById('pickerAccountId').value) || 0;

    if (courseId <= 0) {
        alert('Please select a Target Batch for importing.');
        document.getElementById('pickerCourseId').focus();
        return;
    }
    if (chapterId <= 0) {
        alert('Please select a Target Chapter where the selected videos should be placed.');
        document.getElementById('pickerChapterId').focus();
        return;
    }

    const checkedBoxes = Array.from(document.querySelectorAll('.picker-item-cb:checked'));
    if (checkedBoxes.length === 0) {
        alert('Please select at least one video to import.');
        return;
    }

    const selectedIds = new Set(checkedBoxes.map(cb => cb.value));
    const selectedVideos = pickerVideosData.filter(v => selectedIds.has(v.id));

    const btn = document.getElementById('btnBulkImport');
    btn.disabled = true;
    document.getElementById('bulkImportBtnText').textContent = 'Importing ' + selectedVideos.length + ' videos...';

    try {
        const res = await fetch('/api/admin_crud.php?action=import_dm_videos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                course_id: courseId,
                subject_id: subjectId,
                chapter_id: chapterId,
                account_id: accountId,
                is_free_preview: isPreview,
                videos: selectedVideos
            })
        });

        const json = await res.json();
        if (json.status) {
            alert(json.message);
            // Redirect to the newly populated chapter so admin can view and reorder immediately!
            window.location.href = '/admin/videos/?course_id=' + courseId + '&subject_id=' + subjectId + '&chapter_id=' + chapterId;
        } else {
            alert(json.message || 'Error during import');
            btn.disabled = false;
            document.getElementById('bulkImportBtnText').textContent = 'Import Selected (' + selectedVideos.length + ')';
        }
    } catch (e) {
        alert('Network error while importing videos: ' + e.message);
        btn.disabled = false;
        document.getElementById('bulkImportBtnText').textContent = 'Import Selected (' + selectedVideos.length + ')';
    }
}
</script>

<!-- SortableJS library for smooth drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>