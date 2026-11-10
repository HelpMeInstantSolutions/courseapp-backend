<?php
// backend/admin/courses/index.php
$pageTitle = 'Manage Courses';
$activePage = 'courses';
$headerTitle = 'Courses & Batches';
$headerSubtitle = 'Create, organize and manage video courses for the Android app';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Fetch all courses with video counts
$courses = [];
try {
    $stmt = $pdo->query("SELECT c.*, 
                         (SELECT COUNT(*) FROM videos v WHERE v.course_id = c.id) AS video_count,
                         cat.name AS category_name
                         FROM courses c 
                         LEFT JOIN categories cat ON c.category_id = cat.id 
                         ORDER BY c.id DESC");
    $courses = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch categories for dropdown
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT -->
<div class="flex-1 flex flex-col min-w-0 bg-slate-50 min-h-screen">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Toolbar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="relative flex-1 max-w-md w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="courseSearch" oninput="filterCourses()" 
                       placeholder="Search courses by title or category..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>
            <button onclick="openCourseModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add New Course</span>
            </button>
        </div>

        <!-- Course Cards Grid -->
        <div id="courseGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($courses)): ?>
                <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="book-open" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">No courses available</h3>
                    <p class="text-xs text-slate-500 mt-1">Get started by adding your first course batch</p>
                    <button onclick="openCourseModal()" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">
                        Create Course Now
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $c): ?>
                    <div class="course-card bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col"
                         data-title="<?= strtolower(htmlspecialchars($c['title'])) ?>"
                         data-category="<?= strtolower(htmlspecialchars($c['category_name'] ?? '')) ?>">
                        <!-- Course Thumbnail -->
                        <div class="relative h-44 bg-slate-100 overflow-hidden group">
                            <?php if (!empty($c['thumbnail'])): ?>
                                <img src="<?= htmlspecialchars($c['thumbnail']) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-tr from-indigo-100 to-indigo-50 text-indigo-400">
                                    <i data-lucide="image" class="w-12 h-12 stroke-[1.5]"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-md px-2.5 py-1 rounded-full text-[11px] font-bold text-slate-800 shadow-sm">
                                <?= htmlspecialchars($c['category_name'] ?? 'General') ?>
                            </div>
                            <div class="absolute top-3 right-3 bg-slate-900/80 backdrop-blur-md text-white px-2.5 py-1 rounded-full text-[11px] font-bold">
                                <?= ($c['price'] ?? 0) > 0 ? '₹' . number_format($c['price'], 2) : 'FREE' ?>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 line-clamp-1"><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="text-xs text-slate-500 mt-1.5 line-clamp-2"><?= htmlspecialchars($c['description'] ?: 'Comprehensive video course batch.') ?></p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-lg">
                                    <i data-lucide="video" class="w-3.5 h-3.5 text-indigo-500"></i>
                                    <span><?= $c['video_count'] ?> Video Lessons</span>
                                </span>

                                <div class="flex items-center gap-2">
                                    <a href="/admin/videos/?course_id=<?= $c['id'] ?>" 
                                       title="Manage Lessons" 
                                       class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                        <i data-lucide="list-video" class="w-4 h-4"></i>
                                    </a>
                                    <button onclick='editCourse(<?= json_encode($c) ?>)' 
                                            title="Edit Course" 
                                            class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="deleteCourse(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['title'])) ?>')" 
                                            title="Delete Course" 
                                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ADD/EDIT COURSE MODAL (LIGHT THEME) -->
<div id="courseModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 id="modalTitle" class="text-base font-extrabold text-slate-900">Add New Course</h3>
            <button onclick="closeCourseModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="courseForm" onsubmit="handleSaveCourse(event)" class="p-6 space-y-4">
            <input type="hidden" id="course_id" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Course Title *</label>
                <input type="text" id="course_title" required 
                       placeholder="e.g. Complete Mathematics 2026 Batch" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category</label>
                    <select id="course_category" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <option value="1">General</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Price (₹ INR)</label>
                    <input type="number" step="0.01" id="course_price" value="0.00" 
                           placeholder="0.00 for Free" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Cover Image / Thumbnail URL</label>
                <input type="url" id="course_thumbnail" 
                       placeholder="https://example.com/banner.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Description</label>
                <textarea id="course_description" rows="3" 
                          placeholder="Short summary of course topics..." 
                          class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeCourseModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitCourse" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition">
                    Save Course
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterCourses() {
        var query = document.getElementById('courseSearch').value.toLowerCase();
        var cards = document.querySelectorAll('.course-card');
        cards.forEach(function(card) {
            var title = card.getAttribute('data-title') || '';
            var category = card.getAttribute('data-category') || '';
            if (title.includes(query) || category.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function openCourseModal() {
        document.getElementById('course_id').value = '0';
        document.getElementById('course_title').value = '';
        document.getElementById('course_price').value = '0.00';
        document.getElementById('course_thumbnail').value = '';
        document.getElementById('course_description').value = '';
        document.getElementById('modalTitle').innerText = 'Add New Course';
        document.getElementById('courseModal').classList.remove('hidden');
    }

    function editCourse(c) {
        document.getElementById('course_id').value = c.id;
        document.getElementById('course_title').value = c.title || '';
        document.getElementById('course_category').value = c.category_id || 1;
        document.getElementById('course_price').value = c.price || '0.00';
        document.getElementById('course_thumbnail').value = c.thumbnail || '';
        document.getElementById('course_description').value = c.description || '';
        document.getElementById('modalTitle').innerText = 'Edit Course';
        document.getElementById('courseModal').classList.remove('hidden');
    }

    function closeCourseModal() {
        document.getElementById('courseModal').classList.add('hidden');
    }

    async function handleSaveCourse(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSubmitCourse');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        var payload = {
            id: document.getElementById('course_id').value,
            title: document.getElementById('course_title').value,
            category_id: document.getElementById('course_category').value,
            price: document.getElementById('course_price').value,
            thumbnail: document.getElementById('course_thumbnail').value,
            description: document.getElementById('course_description').value
        };

        try {
            var res = await fetch('/api/admin_crud.php?action=save_course', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.message || 'Failed to save course', 'error');
            }
        } catch (err) {
            showToast('Network error while saving course', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Save Course';
        }
    }

    async function deleteCourse(id, title) {
        if (!confirm('Are you sure you want to delete course "' + title + '" and all its lessons?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_course', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.message || 'Failed to delete course', 'error');
            }
        } catch (err) {
            showToast('Error deleting course', 'error');
        }
    }

    // Auto open modal if ?new=1 in URL
    if (window.location.search.includes('new=1')) {
        openCourseModal();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
