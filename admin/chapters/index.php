<?php
// backend/admin/chapters/index.php - Course Chapters Management (Course -> Chapter -> Video)
$pageTitle = 'Course Chapters';
$activePage = 'chapters';
$headerTitle = 'Chapters Management';
$headerSubtitle = 'Organize courses into structured chapters, modules, and topics';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter by course
$selectedCourseId = (int)($_GET['course_id'] ?? 0);

// Fetch all courses for dropdown
$courses = [];
try {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Query chapters
$chapters = [];
try {
    if ($selectedCourseId > 0) {
        $stmt = $pdo->prepare("SELECT ch.*, c.title AS course_title,
                               (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id) as video_count 
                               FROM chapters ch 
                               LEFT JOIN courses c ON ch.course_id = c.id 
                               WHERE ch.course_id = ? 
                               ORDER BY ch.sort_order ASC, ch.id ASC");
        $stmt->execute([$selectedCourseId]);
    } else {
        $stmt = $pdo->query("SELECT ch.*, c.title AS course_title,
                             (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id) as video_count 
                             FROM chapters ch 
                             LEFT JOIN courses c ON ch.course_id = c.id 
                             ORDER BY ch.course_id DESC, ch.sort_order ASC");
    }
    $chapters = $stmt->fetchAll();
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
                    <input type="text" id="chapterSearch" oninput="filterChapters()" 
                           placeholder="Search chapters by title or description..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Course Filter Dropdown -->
                <select id="courseFilter" onchange="applyCourseFilter(this.value)" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">All Courses (<?= count($chapters) ?> Chapters)</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Add Chapter Button -->
            <button onclick="openChapterModal()" 
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i data-lucide="layers" class="w-4 h-4"></i>
                <span>Add New Chapter</span>
            </button>
        </div>

        <!-- Chapters Data Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="chaptersTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4">Chapter Details</th>
                            <th class="py-3.5 px-4">Course</th>
                            <th class="py-3.5 px-4 text-center">Videos</th>
                            <th class="py-3.5 px-4 text-center">Sort Order</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($chapters)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="layers" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No chapters found</p>
                                    <p class="text-xs text-slate-400 mt-1">Create your first chapter to structure lessons under courses.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($chapters as $idx => $ch): ?>
                                <tr class="hover:bg-slate-50/80 transition chapter-row" data-search="<?= strtolower(htmlspecialchars($ch['title'] . ' ' . ($ch['description'] ?? '') . ' ' . ($ch['course_title'] ?? ''))) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        <?= $idx + 1 ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                                                <i data-lucide="folder" class="w-4 h-4"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($ch['title']) ?></p>
                                                <?php if (!empty($ch['description'])): ?>
                                                    <p class="text-[11px] text-slate-400 line-clamp-1 mt-0.5"><?= htmlspecialchars($ch['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100/60">
                                            <i data-lucide="book-open" class="w-3 h-3 text-indigo-500"></i>
                                            <?= htmlspecialchars($ch['course_title'] ?? 'Unassigned Course') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <a href="/admin/videos/?course_id=<?= $ch['course_id'] ?>&chapter_id=<?= $ch['id'] ?>" 
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold <?= (int)$ch['video_count'] > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500' ?> transition">
                                            <i data-lucide="video" class="w-3.5 h-3.5"></i>
                                            <span><?= (int)$ch['video_count'] ?> Videos</span>
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md font-bold text-[11px]">
                                            #<?= (int)$ch['sort_order'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="/admin/videos/?course_id=<?= $ch['course_id'] ?>&chapter_id=<?= $ch['id'] ?>&new=1" 
                                               title="Add Video to this Chapter"
                                               class="p-1.5 hover:bg-indigo-50 text-slate-400 hover:text-indigo-600 rounded-lg transition">
                                                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                            </a>
                                            <button onclick='editChapter(<?= json_encode($ch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                                                    title="Edit Chapter"
                                                    class="p-1.5 hover:bg-slate-100 text-slate-400 hover:text-slate-700 rounded-lg transition">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="confirmDeleteChapter(<?= $ch['id'] ?>, '<?= addslashes(htmlspecialchars($ch['title'])) ?>')" 
                                                    title="Delete Chapter"
                                                    class="p-1.5 hover:bg-red-50 text-slate-400 hover:text-red-600 rounded-lg transition">
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

<!-- ADD / EDIT CHAPTER MODAL -->
<div id="chapterModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm hidden select-none">
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="font-bold text-slate-800 text-sm">Add New Chapter</h3>
                    <p class="text-[11px] text-slate-400">Chapters group lectures systematically</p>
                </div>
            </div>
            <button onclick="closeChapterModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form id="chapterForm" onsubmit="handleChapterSubmit(event)" class="p-6 space-y-4">
            <input type="hidden" id="chapterId" name="id" value="0">

            <!-- Course Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Course <span class="text-red-500">*</span></label>
                <select id="formCourseId" name="course_id" required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Chapter Title -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Chapter Title <span class="text-red-500">*</span></label>
                <input type="text" id="chapterTitleInput" name="title" required 
                       placeholder="e.g. Chapter 01: Number System & Divisibility" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Chapter Description -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Description (Optional)</label>
                <textarea id="chapterDescInput" name="description" rows="2" 
                          placeholder="Brief summary of concepts covered in this chapter..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition"></textarea>
            </div>

            <!-- Sort Order -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Sort Sequence #</label>
                <input type="number" id="chapterSortOrder" name="sort_order" value="1" min="1" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-slate-100">
                <button type="button" onclick="closeChapterModal()" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSaveChapter" 
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    Save Chapter
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function applyCourseFilter(cId) {
    window.location.href = '/admin/chapters/' + (cId > 0 ? '?course_id=' + cId : '');
}

function filterChapters() {
    const q = document.getElementById('chapterSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.chapter-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = text.includes(q) ? '' : 'none';
    });
}

function openChapterModal(preselectedCourseId = 0) {
    document.getElementById('chapterId').value = 0;
    document.getElementById('chapterForm').reset();
    document.getElementById('modalTitle').innerText = 'Add New Chapter';
    document.getElementById('btnSaveChapter').innerText = 'Create Chapter';
    
    if (preselectedCourseId > 0) {
        document.getElementById('formCourseId').value = preselectedCourseId;
    } else {
        const currentFilter = document.getElementById('courseFilter').value;
        if (currentFilter > 0) {
            document.getElementById('formCourseId').value = currentFilter;
        }
    }
    
    document.getElementById('chapterModal').classList.remove('hidden');
}

function closeChapterModal() {
    document.getElementById('chapterModal').classList.add('hidden');
}

function editChapter(ch) {
    document.getElementById('chapterId').value = ch.id;
    document.getElementById('formCourseId').value = ch.course_id;
    document.getElementById('chapterTitleInput').value = ch.title;
    document.getElementById('chapterDescInput').value = ch.description || '';
    document.getElementById('chapterSortOrder').value = ch.sort_order || 1;
    
    document.getElementById('modalTitle').innerText = 'Edit Chapter';
    document.getElementById('btnSaveChapter').innerText = 'Update Chapter';
    document.getElementById('chapterModal').classList.remove('hidden');
}

async function handleChapterSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveChapter');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    const payload = {
        action: 'save_chapter',
        id: document.getElementById('chapterId').value,
        course_id: document.getElementById('formCourseId').value,
        title: document.getElementById('chapterTitleInput').value,
        description: document.getElementById('chapterDescInput').value,
        sort_order: document.getElementById('chapterSortOrder').value
    };

    try {
        const res = await fetch('/api/admin_crud.php?action=save_chapter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(pe) {
            alert('Server error: ' + (text || res.statusText));
            btn.disabled = false;
            btn.innerText = 'Save Chapter';
            return;
        }

        if (data.status) {
            window.location.reload();
        } else {
            alert(data.message || 'Error saving chapter');
            btn.disabled = false;
            btn.innerText = 'Save Chapter';
        }
    } catch (err) {
        alert('Communication error: ' + err.message);
        btn.disabled = false;
        btn.innerText = 'Save Chapter';
    }
}

async function confirmDeleteChapter(id, title) {
    if (!confirm('Are you sure you want to delete chapter "' + title + '"?\n\nVideos attached to this chapter will NOT be deleted, but will become unassigned.')) {
        return;
    }

    try {
        const res = await fetch('/api/admin_crud.php?action=delete_chapter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.status) {
            window.location.reload();
        } else {
            alert(data.message || 'Error deleting chapter');
        }
    } catch (err) {
        alert('Server communication error: ' + err.message);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
