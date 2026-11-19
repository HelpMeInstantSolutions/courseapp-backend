<?php
// backend/admin/chapters/index.php - Chapters Management (Batch -> Subject -> Chapter [with Sub-chapters] -> Video)
$pageTitle = 'Course Chapters';
$activePage = 'chapters';
$headerTitle = 'Chapters & Modules';
$headerSubtitle = 'Organize subjects into structured chapters, sub-chapters/folders, and attach PDF notes';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter by batch & subject
$selectedCourseId = (int)($_GET['course_id'] ?? 0);
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);

// Fetch all batches for dropdown
$courses = [];
try {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch all subjects for dropdowns
$allSubjects = [];
try {
    $allSubjects = $pdo->query("SELECT id, course_id, title FROM subjects WHERE status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Query chapters with hierarchy details
$chapters = [];
try {
    $sql = "SELECT ch.*, c.title AS course_title, s.title AS subject_title,
                   p.title AS parent_chapter_title,
                   (SELECT COUNT(*) FROM chapters sub WHERE sub.parent_id = ch.id) as sub_chapter_count,
                   (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id) as video_count 
            FROM chapters ch 
            LEFT JOIN courses c ON ch.course_id = c.id 
            LEFT JOIN subjects s ON ch.subject_id = s.id 
            LEFT JOIN chapters p ON ch.parent_id = p.id
            WHERE 1=1";
    $params = [];
    if ($selectedCourseId > 0) {
        $sql .= " AND ch.course_id = ?";
        $params[] = $selectedCourseId;
    }
    if ($selectedSubjectId > 0) {
        $sql .= " AND ch.subject_id = ?";
        $params[] = $selectedSubjectId;
    }
    $sql .= " ORDER BY ch.course_id DESC, ch.subject_id ASC, ch.sort_order ASC, ch.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $chapters = $stmt->fetchAll();
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
                    <input type="text" id="chapterSearch" oninput="filterChapters()" 
                           placeholder="Search chapters by title or description..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Batch Filter Dropdown -->
                <select id="courseFilter" onchange="applyFilters()" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">All Batches (<?= count($chapters) ?> Chapters)</option>
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

                <!-- Drag to reorder hint badge -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 text-[11px] font-semibold rounded-xl border border-indigo-100">
                    <i data-lucide="move" class="w-3.5 h-3.5 text-indigo-500"></i>
                    <span>Drag rows to change sort order</span>
                </span>
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
                            <th class="py-3.5 px-4 w-20 text-center">Sort</th>
                            <th class="py-3.5 px-4">Chapter Details</th>
                            <th class="py-3.5 px-4">Batch / Subject</th>
                            <th class="py-3.5 px-4 text-center">Notes / PDF</th>
                            <th class="py-3.5 px-4 text-center">Videos</th>
                            <th class="py-3.5 px-4 text-center">Sequence #</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium" id="chaptersTableBody">
                        <?php if (empty($chapters)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="layers" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No chapters found</p>
                                    <p class="text-xs text-slate-400 mt-1">Create your first chapter to structure lessons under subjects.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($chapters as $idx => $ch): ?>
                                <?php $isSubChapter = !empty($ch['parent_id']) && $ch['parent_id'] > 0; ?>
                                <tr class="hover:bg-slate-50/80 transition chapter-row <?= $isSubChapter ? 'bg-slate-50/40' : '' ?>" 
                                    data-id="<?= $ch['id'] ?>"
                                    data-search="<?= strtolower(htmlspecialchars($ch['title'] . ' ' . ($ch['description'] ?? '') . ' ' . ($ch['course_title'] ?? '') . ' ' . ($ch['subject_title'] ?? ''))) ?>">
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <div class="chapter-drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-indigo-600 p-1 rounded hover:bg-indigo-50 transition" title="Drag row up or down to reorder chapter">
                                                <i data-lucide="grip-vertical" class="w-4 h-4"></i>
                                            </div>
                                            <span class="chapter-sort-badge font-mono font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded text-[11px]">
                                                #<?= (int)$ch['sort_order'] ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3 <?= $isSubChapter ? 'pl-6' : '' ?>">
                                            <div class="w-9 h-9 rounded-xl <?= $isSubChapter ? 'bg-amber-50 text-amber-600 border border-amber-100' : 'bg-indigo-50 text-indigo-600 border border-indigo-100' ?> flex items-center justify-center font-bold shrink-0">
                                                <i data-lucide="<?= $isSubChapter ? 'folder-symlink' : 'folder' ?>" class="w-4 h-4"></i>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($ch['title']) ?></p>
                                                    <?php if ($isSubChapter): ?>
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">
                                                            ↳ Sub-folder
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($ch['description'])): ?>
                                                    <p class="text-[11px] text-slate-400 line-clamp-1 mt-0.5"><?= htmlspecialchars($ch['description']) ?></p>
                                                <?php endif; ?>
                                                <?php if ($isSubChapter && !empty($ch['parent_chapter_title'])): ?>
                                                    <p class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <span>Parent:</span>
                                                        <span class="font-medium text-slate-600"><?= htmlspecialchars($ch['parent_chapter_title']) ?></span>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100/60 block truncate max-w-[200px]">
                                                <i data-lucide="graduation-cap" class="w-3 h-3 text-indigo-500 shrink-0"></i>
                                                <span class="truncate"><?= htmlspecialchars($ch['course_title'] ?? 'Unassigned') ?></span>
                                            </span>
                                            <?php if (!empty($ch['subject_title'])): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100/60 block truncate max-w-[200px]">
                                                    <i data-lucide="book-open" class="w-2.5 h-2.5 text-emerald-500 shrink-0"></i>
                                                    <span class="truncate"><?= htmlspecialchars($ch['subject_title']) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <?php if (!empty($ch['pdf_url'])): ?>
                                            <a href="<?= htmlspecialchars($ch['pdf_url']) ?>" target="_blank" 
                                               title="View Attached PDF Notes"
                                               class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition">
                                                <i data-lucide="file-text" class="w-3 h-3 text-rose-500"></i>
                                                <span>PDF Notes</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <a href="/admin/videos/?course_id=<?= $ch['course_id'] ?>&chapter_id=<?= $ch['id'] ?>" 
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold <?= (int)$ch['video_count'] > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500' ?> transition">
                                            <i data-lucide="video" class="w-3.5 h-3.5"></i>
                                            <span><?= (int)$ch['video_count'] ?></span>
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-center text-slate-500 font-bold chapter-sort-col">
                                        #<?= (int)$ch['sort_order'] ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="/admin/videos/?course_id=<?= $ch['course_id'] ?>&subject_id=<?= $ch['subject_id'] ?>&chapter_id=<?= $ch['id'] ?>&new=1" 
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

        <form id="chapterForm" onsubmit="handleChapterSubmit(event)" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" id="chapterId" name="id" value="0">

            <!-- Batch Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Batch <span class="text-red-500">*</span></label>
                <select id="formCourseId" name="course_id" required onchange="onBatchChanged()"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Batch --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Subject <span class="text-red-500">*</span></label>
                <select id="formSubjectId" name="subject_id" required onchange="onSubjectChanged()"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Subject --</option>
                </select>
            </div>

            <!-- Parent Chapter (Folder / Hierarchy) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Parent Folder / Chapter 
                    <span class="text-[10px] text-slate-400 font-normal">(Leave as Root for main chapters)</span>
                </label>
                <select id="formParentId" name="parent_id" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">📂 None - Main Chapter (Root Level)</option>
                </select>
            </div>

            <!-- Chapter Title -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Chapter Title <span class="text-red-500">*</span></label>
                <input type="text" id="chapterTitleInput" name="title" required 
                       placeholder="e.g. Chapter 01: Percentage & Applications" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- PDF Notes / Class Assignment URL -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Chapter PDF Notes / Assignment URL 
                    <span class="text-[10px] text-slate-400 font-normal">(Optional)</span>
                </label>
                <input type="url" id="chapterPdfInput" name="pdf_url" 
                       placeholder="https://example.com/notes/percentage-notes.pdf" 
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
const allSubjectsData = <?= json_encode($allSubjects) ?>;
const allChaptersData = <?= json_encode(array_map(function($c) {
    return [
        'id' => (int)$c['id'],
        'course_id' => (int)$c['course_id'],
        'subject_id' => (int)($c['subject_id'] ?? 0),
        'parent_id' => (int)($c['parent_id'] ?? 0),
        'title' => $c['title']
    ];
}, $chapters)) ?>;

function applyFilters() {
    const cId = document.getElementById('courseFilter').value;
    const sId = document.getElementById('subjectFilter').value;
    let url = '/admin/chapters/?';
    if (cId > 0) url += 'course_id=' + cId + '&';
    if (sId > 0) url += 'subject_id=' + sId;
    window.location.href = url.replace(/&$/, '');
}

function filterChapters() {
    const q = document.getElementById('chapterSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.chapter-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = text.includes(q) ? '' : 'none';
    });
}

function populateSubjectDropdown(selectedBatchId, selectedSubjectId = 0) {
    const subSelect = document.getElementById('formSubjectId');
    subSelect.innerHTML = '<option value="">-- Choose Subject --</option>';
    
    const filtered = allSubjectsData.filter(s => selectedBatchId == 0 || s.course_id == selectedBatchId);
    filtered.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.title;
        if (s.id == selectedSubjectId) opt.selected = true;
        subSelect.appendChild(opt);
    });
}

function populateParentDropdown(selectedBatchId, selectedSubjectId = 0, excludeId = 0, selectedParentId = 0) {
    const parentSelect = document.getElementById('formParentId');
    parentSelect.innerHTML = '<option value="0">📂 None - Main Chapter (Root Level)</option>';

    const filtered = allChaptersData.filter(ch => {
        if (ch.id == excludeId) return false; // cannot be parent of itself
        if (ch.parent_id > 0) return false; // only main chapters can be parents (avoid infinite nesting)
        if (selectedBatchId > 0 && ch.course_id != selectedBatchId) return false;
        if (selectedSubjectId > 0 && ch.subject_id > 0 && ch.subject_id != selectedSubjectId) return false;
        return true;
    });

    filtered.forEach(ch => {
        const opt = document.createElement('option');
        opt.value = ch.id;
        opt.textContent = '📁 ' + ch.title;
        if (ch.id == selectedParentId) opt.selected = true;
        parentSelect.appendChild(opt);
    });
}

function onBatchChanged() {
    const bId = parseInt(document.getElementById('formCourseId').value) || 0;
    populateSubjectDropdown(bId);
    populateParentDropdown(bId, 0, parseInt(document.getElementById('chapterId').value) || 0);
}

function onSubjectChanged() {
    const bId = parseInt(document.getElementById('formCourseId').value) || 0;
    const sId = parseInt(document.getElementById('formSubjectId').value) || 0;
    populateParentDropdown(bId, sId, parseInt(document.getElementById('chapterId').value) || 0);
}

function openChapterModal(preselectedCourseId = 0) {
    document.getElementById('chapterId').value = 0;
    document.getElementById('chapterForm').reset();
    document.getElementById('modalTitle').innerText = 'Add New Chapter';
    document.getElementById('btnSaveChapter').innerText = 'Create Chapter';
    
    let currentBatch = preselectedCourseId;
    if (!currentBatch) {
        currentBatch = parseInt(document.getElementById('courseFilter').value) || 0;
    }
    if (currentBatch > 0) {
        document.getElementById('formCourseId').value = currentBatch;
    }

    const currentSub = parseInt(document.getElementById('subjectFilter').value) || 0;
    populateSubjectDropdown(currentBatch, currentSub);
    populateParentDropdown(currentBatch, currentSub, 0, 0);
    
    document.getElementById('chapterModal').classList.remove('hidden');
}

function closeChapterModal() {
    document.getElementById('chapterModal').classList.add('hidden');
}

function editChapter(ch) {
    document.getElementById('chapterId').value = ch.id;
    document.getElementById('formCourseId').value = ch.course_id;
    
    populateSubjectDropdown(ch.course_id, ch.subject_id || 0);
    populateParentDropdown(ch.course_id, ch.subject_id || 0, ch.id, ch.parent_id || 0);

    document.getElementById('chapterTitleInput').value = ch.title;
    document.getElementById('chapterPdfInput').value = ch.pdf_url || '';
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
        subject_id: document.getElementById('formSubjectId').value,
        parent_id: document.getElementById('formParentId').value,
        title: document.getElementById('chapterTitleInput').value,
        pdf_url: document.getElementById('chapterPdfInput').value,
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

// Toast notification helper for instant drag & drop feedback
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

// Initialize SortableJS drag & drop for chapters
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('chaptersTableBody');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.chapter-drag-handle',
            animation: 180,
            ghostClass: 'bg-indigo-50/90',
            chosenClass: 'bg-indigo-100/60',
            onEnd: async function() {
                const rows = Array.from(tbody.querySelectorAll('tr.chapter-row'));
                const order = [];
                rows.forEach((row, idx) => {
                    const newSort = idx + 1;
                    const chId = parseInt(row.getAttribute('data-id')) || 0;
                    const badge = row.querySelector('.chapter-sort-badge');
                    const colSort = row.querySelector('.chapter-sort-col');
                    if (badge) badge.textContent = '#' + newSort;
                    if (colSort) colSort.textContent = '#' + newSort;
                    if (chId > 0) {
                        order.push({ id: chId, sort_order: newSort });
                    }
                });

                showSortToast('⏳ Saving new sequence...', 'info');
                try {
                    const res = await fetch('/api/admin_crud.php?action=reorder_chapters', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order: order })
                    });
                    const json = await res.json();
                    if (json.status) {
                        showSortToast('✅ Chapter sequence saved!', 'success');
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
</script>

<!-- SortableJS library for smooth drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>