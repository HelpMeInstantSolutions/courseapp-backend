<?php
// backend/admin/subjects/index.php - Subject Management (Batch -> Subject -> Chapter -> Video)
$pageTitle = 'Course Subjects';
$activePage = 'subjects';
$headerTitle = 'Subjects & Faculty';
$headerSubtitle = 'Organize batches into subjects with assigned faculty instructors';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Filter by batch/course
$selectedCourseId = (int)($_GET['course_id'] ?? 0);

// Fetch all batches for dropdown
$courses = [];
try {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

// Query subjects
$subjects = [];
try {
    if ($selectedCourseId > 0) {
        $stmt = $pdo->prepare("SELECT s.*, c.title AS course_title,
                               (SELECT COUNT(*) FROM chapters ch WHERE ch.subject_id = s.id AND (ch.parent_id = 0 OR ch.parent_id IS NULL)) as chapter_count,
                               (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) as video_count 
                               FROM subjects s 
                               LEFT JOIN courses c ON s.course_id = c.id 
                               WHERE s.course_id = ? 
                               ORDER BY s.sort_order ASC, s.id ASC");
        $stmt->execute([$selectedCourseId]);
    } else {
        $stmt = $pdo->query("SELECT s.*, c.title AS course_title,
                             (SELECT COUNT(*) FROM chapters ch WHERE ch.subject_id = s.id AND (ch.parent_id = 0 OR ch.parent_id IS NULL)) as chapter_count,
                             (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) as video_count 
                             FROM subjects s 
                             LEFT JOIN courses c ON s.course_id = c.id 
                             ORDER BY s.course_id DESC, s.sort_order ASC");
    }
    $subjects = $stmt->fetchAll();
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
                <div class="relative flex-1 min-w-[200px]">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="subjectSearch" oninput="filterSubjects()" 
                           placeholder="Search subjects by title or faculty..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>

                <!-- Batch Filter Dropdown -->
                <select id="courseFilter" onchange="applyCourseFilter(this.value)" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">All Batches (<?= count($subjects) ?> Subjects)</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Add Subject Button -->
            <button onclick="openSubjectModal()" 
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>Add New Subject</span>
            </button>
        </div>

        <!-- Subjects Data Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="subjectsTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4">Subject & Faculty</th>
                            <th class="py-3.5 px-4">Batch</th>
                            <th class="py-3.5 px-4 text-center">Chapters</th>
                            <th class="py-3.5 px-4 text-center">Videos</th>
                            <th class="py-3.5 px-4 text-center">Sort</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="book-open" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No subjects found</p>
                                    <p class="text-xs text-slate-400 mt-1">Create subjects like "Maths by Bhutesh Sir" under your batches.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $idx => $s): ?>
                                <tr class="hover:bg-slate-50/80 transition subject-row" data-search="<?= strtolower(htmlspecialchars($s['title'] . ' ' . ($s['faculty_name'] ?? '') . ' ' . ($s['course_title'] ?? ''))) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        <?= $idx + 1 ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center font-bold shrink-0 overflow-hidden">
                                                <?php if (!empty($s['thumbnail'])): ?>
                                                    <img src="<?= htmlspecialchars($s['thumbnail']) ?>" alt="" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <i data-lucide="book-open" class="w-5 h-5"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($s['title']) ?></p>
                                                <?php if (!empty($s['faculty_name'])): ?>
                                                    <p class="text-[11px] text-indigo-600 font-semibold flex items-center gap-1 mt-0.5">
                                                        <i data-lucide="user-check" class="w-3 h-3"></i>
                                                        <?= htmlspecialchars($s['faculty_name']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($s['description'])): ?>
                                                    <p class="text-[10px] text-slate-400 line-clamp-1"><?= htmlspecialchars($s['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 text-slate-700">
                                            <i data-lucide="graduation-cap" class="w-3 h-3 text-slate-400"></i>
                                            <?= htmlspecialchars($s['course_title'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <a href="/admin/chapters/?course_id=<?= $s['course_id'] ?>&subject_id=<?= $s['id'] ?>" 
                                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                                            <i data-lucide="layers" class="w-3 h-3 text-indigo-500"></i>
                                            <span><?= (int)$s['chapter_count'] ?></span>
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <a href="/admin/videos/?course_id=<?= $s['course_id'] ?>&subject_id=<?= $s['id'] ?>" 
                                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 hover:bg-violet-100 transition">
                                            <i data-lucide="video" class="w-3 h-3 text-violet-500"></i>
                                            <span><?= (int)$s['video_count'] ?></span>
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4 text-center text-slate-500 font-bold">
                                        #<?= (int)$s['sort_order'] ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="/admin/chapters/?course_id=<?= $s['course_id'] ?>&subject_id=<?= $s['id'] ?>" 
                                               title="View Chapters"
                                               class="p-1.5 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 rounded-lg transition">
                                                <i data-lucide="layers" class="w-4 h-4"></i>
                                            </a>
                                            <button onclick='editSubject(<?= json_encode($s) ?>)' 
                                                    title="Edit Subject"
                                                    class="p-1.5 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 rounded-lg transition">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="confirmDeleteSubject(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['title'])) ?>')" 
                                                    title="Delete Subject"
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

<!-- ADD / EDIT SUBJECT MODAL -->
<div id="subjectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm hidden select-none">
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="font-bold text-slate-800 text-sm">Add New Subject</h3>
                    <p class="text-[11px] text-slate-400">Subjects group chapters under a batch</p>
                </div>
            </div>
            <button onclick="closeSubjectModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form id="subjectForm" onsubmit="handleSubjectSubmit(event)" class="p-6 space-y-4">
            <input type="hidden" id="subjectId" name="id" value="0">

            <!-- Batch Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Batch <span class="text-red-500">*</span></label>
                <select id="formCourseId" name="course_id" required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Batch --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject Title -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Subject Title <span class="text-red-500">*</span></label>
                <input type="text" id="subjectTitleInput" name="title" required 
                       placeholder="e.g. Mathematics by Bhutesh Sir" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Faculty Name -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Faculty / Instructor Name</label>
                <input type="text" id="subjectFacultyInput" name="faculty_name" 
                       placeholder="e.g. Bhutesh Sir" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Thumbnail URL -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Thumbnail URL (Optional)</label>
                <input type="url" id="subjectThumbnailInput" name="thumbnail" 
                       placeholder="https://.../subject-thumb.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Description (Optional)</label>
                <textarea id="subjectDescInput" name="description" rows="2" 
                          placeholder="Complete syllabus coverage from basic to advanced..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition"></textarea>
            </div>

            <!-- Sort Order -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Sort Sequence #</label>
                <input type="number" id="subjectSortOrder" name="sort_order" value="1" min="1" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-slate-100">
                <button type="button" onclick="closeSubjectModal()" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSaveSubject" 
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    Save Subject
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function applyCourseFilter(cId) {
    window.location.href = '/admin/subjects/' + (cId > 0 ? '?course_id=' + cId : '');
}

function filterSubjects() {
    const q = document.getElementById('subjectSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.subject-row');
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = text.includes(q) ? '' : 'none';
    });
}

function openSubjectModal(preselectedCourseId = 0) {
    document.getElementById('subjectId').value = 0;
    document.getElementById('subjectForm').reset();
    document.getElementById('modalTitle').innerText = 'Add New Subject';
    document.getElementById('btnSaveSubject').innerText = 'Create Subject';
    
    if (preselectedCourseId > 0) {
        document.getElementById('formCourseId').value = preselectedCourseId;
    } else {
        const currentFilter = document.getElementById('courseFilter').value;
        if (currentFilter > 0) {
            document.getElementById('formCourseId').value = currentFilter;
        }
    }
    
    document.getElementById('subjectModal').classList.remove('hidden');
}

function closeSubjectModal() {
    document.getElementById('subjectModal').classList.add('hidden');
}

function editSubject(s) {
    document.getElementById('subjectId').value = s.id;
    document.getElementById('formCourseId').value = s.course_id;
    document.getElementById('subjectTitleInput').value = s.title;
    document.getElementById('subjectFacultyInput').value = s.faculty_name || '';
    document.getElementById('subjectThumbnailInput').value = s.thumbnail || '';
    document.getElementById('subjectDescInput').value = s.description || '';
    document.getElementById('subjectSortOrder').value = s.sort_order || 1;
    
    document.getElementById('modalTitle').innerText = 'Edit Subject';
    document.getElementById('btnSaveSubject').innerText = 'Update Subject';
    document.getElementById('subjectModal').classList.remove('hidden');
}

async function handleSubjectSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveSubject');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    const payload = {
        action: 'save_subject',
        id: document.getElementById('subjectId').value,
        course_id: document.getElementById('formCourseId').value,
        title: document.getElementById('subjectTitleInput').value,
        faculty_name: document.getElementById('subjectFacultyInput').value,
        thumbnail: document.getElementById('subjectThumbnailInput').value,
        description: document.getElementById('subjectDescInput').value,
        sort_order: document.getElementById('subjectSortOrder').value
    };

    try {
        const res = await fetch('/api/admin_crud.php?action=save_subject', {
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
            btn.innerText = 'Save Subject';
            return;
        }

        if (data.status) {
            window.location.reload();
        } else {
            alert(data.message || 'Error saving subject');
            btn.disabled = false;
            btn.innerText = 'Save Subject';
        }
    } catch (err) {
        alert('Communication error: ' + err.message);
        btn.disabled = false;
        btn.innerText = 'Save Subject';
    }
}

async function confirmDeleteSubject(id, title) {
    if (!confirm('Are you sure you want to delete subject "' + title + '"?\n\nChapters and videos in this subject will not be deleted, but will become unassigned.')) {
        return;
    }

    try {
        const res = await fetch('/api/admin_crud.php?action=delete_subject', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.status) {
            window.location.reload();
        } else {
            alert(data.message || 'Error deleting subject');
        }
    } catch (err) {
        alert('Server communication error: ' + err.message);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>