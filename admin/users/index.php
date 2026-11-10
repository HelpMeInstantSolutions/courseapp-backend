<?php
// backend/admin/users/index.php
$pageTitle = 'Enrolled Students';
$activePage = 'users';
$headerTitle = 'Enrolled Students Roster';
$headerSubtitle = 'Mobile app registered accounts and student activity management';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Fetch users
$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email, phone, status, created_at FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 flex flex-col min-w-0 bg-slate-50 min-h-screen">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="relative flex-1 max-w-md w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="userSearch" oninput="filterUsers()" 
                       placeholder="Search by student name, email, or mobile..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-xl">
                    Total Students: <strong class="text-slate-800"><?= count($users) ?></strong>
                </span>
            </div>
        </div>

        <!-- Students Table (Light Theme) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="usersTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">ID</th>
                            <th class="py-3.5 px-4">Student</th>
                            <th class="py-3.5 px-4">Email Address</th>
                            <th class="py-3.5 px-4">Phone Number</th>
                            <th class="py-3.5 px-4">Joined Date</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No registered students found</p>
                                    <p class="text-xs text-slate-400 mt-1">Users will appear here once they register via the mobile app.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50/70 transition user-row" 
                                    data-name="<?= strtolower(htmlspecialchars($u['name'] ?? '')) ?>"
                                    data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>"
                                    data-phone="<?= strtolower(htmlspecialchars($u['phone'] ?? '')) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        #<?= $u['id'] ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-brand-500 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                                                <?= substr($u['name'] ?? 'S', 0, 1) ?>
                                            </div>
                                            <div class="truncate">
                                                <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($u['name']) ?></p>
                                                <span class="text-[10px] text-slate-400">App Student</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700">
                                        <span class="font-mono text-slate-600"><?= htmlspecialchars($u['email']) ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700">
                                        <span class="font-mono text-slate-600"><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                        <?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10)) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <button onclick="deleteStudent(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')" 
                                                title="Delete Student" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
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

<script>
    function filterUsers() {
        var query = document.getElementById('userSearch').value.toLowerCase();
        var rows = document.querySelectorAll('.user-row');
        rows.forEach(function(row) {
            var name = row.getAttribute('data-name') || '';
            var email = row.getAttribute('data-email') || '';
            var phone = row.getAttribute('data-phone') || '';
            if (name.includes(query) || email.includes(query) || phone.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    async function deleteStudent(id, name) {
        if (!confirm('Are you sure you want to remove student account "' + name + '"?')) return;

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_user', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.message || 'Failed to delete student', 'error');
            }
        } catch (e) {
            showToast('Network error while deleting student', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
