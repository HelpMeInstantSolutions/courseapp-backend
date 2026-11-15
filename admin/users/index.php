<?php
// backend/admin/users/index.php - Students Management, OTP Verification, Password Reset, Device Lock & Batch Assignment
$pageTitle = 'Student Accounts';
$activePage = 'users';
$headerTitle = 'Students & Access Control';
$headerSubtitle = 'Manage passwords, device locks, OTP verification and batch/subject validity assignments';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Fetch users with device binding, passwords and active enrollments
$users = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at,
               u.plain_password, u.device_id, u.device_model, u.otp_code, u.is_verified,
               (SELECT COUNT(*) FROM user_enrollments e WHERE e.user_id = u.id AND e.status = 1 AND (e.validity_date IS NULL OR e.validity_date >= DATE('now'))) as active_enrollments_count
        FROM users u 
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll();

    $enrStmt = $pdo->prepare("
        SELECT e.id as enrollment_id, e.course_id, e.subject_id, e.validity_date,
               c.title as course_title, s.title as subject_title
        FROM user_enrollments e
        LEFT JOIN courses c ON e.course_id = c.id
        LEFT JOIN subjects s ON e.subject_id = s.id
        WHERE e.user_id = ? AND e.status = 1
        ORDER BY e.id DESC
    ");
    foreach ($users as &$u) {
        $enrStmt->execute([$u['id']]);
        $u['enrollments'] = $enrStmt->fetchAll();
    }
} catch (Exception $e) {}

// Fetch batches and subjects for assignment dropdowns
$courses = [];
try {
    $courses = $pdo->query("SELECT id, title FROM courses WHERE status = 1 ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

$subjects = [];
try {
    $subjects = $pdo->query("SELECT id, course_id, title FROM subjects WHERE status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="relative flex-1 max-w-md w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="userSearch" oninput="filterUsers()" 
                       placeholder="Search by student name, email, phone or device..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-xl">
                    Total Students: <strong class="text-slate-800"><?= count($users) ?></strong>
                </span>
            </div>
        </div>

        <!-- Students Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="usersTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">ID</th>
                            <th class="py-3.5 px-4">Student & Contact</th>
                            <th class="py-3.5 px-4">Password</th>
                            <th class="py-3.5 px-4 text-center">OTP / Verification</th>
                            <th class="py-3.5 px-4">Device Lock</th>
                            <th class="py-3.5 px-4">Assigned Batches & Validity</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">
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
                                    data-phone="<?= strtolower(htmlspecialchars($u['phone'] ?? '')) ?>"
                                    data-device="<?= strtolower(htmlspecialchars($u['device_model'] ?? '')) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        #<?= $u['id'] ?>
                                    </td>

                                    <!-- Student Details -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-brand-500 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm shrink-0">
                                                <?= substr($u['name'] ?? 'S', 0, 1) ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-900"><?= htmlspecialchars($u['name']) ?></p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[11px] text-slate-500 flex items-center gap-1 font-mono">
                                                        <i data-lucide="mail" class="w-3 h-3 text-slate-400"></i>
                                                        <?= htmlspecialchars($u['email']) ?>
                                                    </span>
                                                    <?php if (!empty($u['phone'])): ?>
                                                        <span class="text-[11px] text-indigo-600 font-semibold font-mono flex items-center gap-0.5">
                                                            <i data-lucide="phone" class="w-3 h-3 text-indigo-400"></i>
                                                            <?= htmlspecialchars($u['phone']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Password Display -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-1.5 font-mono text-[11px]">
                                            <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">
                                                <?= htmlspecialchars($u['plain_password'] ?: '••••••') ?>
                                            </span>
                                            <button onclick="openChangePasswordModal(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                                                    title="Change Password"
                                                    class="p-1 hover:bg-indigo-50 text-slate-400 hover:text-indigo-600 rounded transition">
                                                <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- OTP & Verification -->
                                    <td class="py-3.5 px-4 text-center">
                                        <?php if ((int)($u['is_verified'] ?? 1) === 1): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                                <i data-lucide="check-circle-2" class="w-3 h-3"></i> Verified
                                            </span>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                                    OTP: <?= htmlspecialchars($u['otp_code'] ?: 'None') ?>
                                                </span>
                                                <button onclick="verifyUserOtp(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                                                        class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold transition">
                                                    Verify OTP
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Device Lock Binding -->
                                    <td class="py-3.5 px-4">
                                        <?php if (!empty($u['device_id'])): ?>
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] font-semibold max-w-[150px] truncate" title="Device ID: <?= htmlspecialchars($u['device_id']) ?>">
                                                    <i data-lucide="smartphone" class="w-3 h-3 text-indigo-500 shrink-0"></i>
                                                    <span class="truncate"><?= htmlspecialchars($u['device_model'] ?: 'Android Device') ?></span>
                                                </span>
                                                <button onclick="unbindDevice(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                                                        title="Unbind / Reset Phone Lock"
                                                        class="p-1 hover:bg-rose-50 text-slate-400 hover:text-rose-600 rounded transition shrink-0">
                                                    <i data-lucide="unlock" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 italic">
                                                <i data-lucide="smartphone-nfc" class="w-3 h-3"></i> Not bound yet
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Assigned Batches & Validity -->
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1 max-w-[220px]">
                                            <?php if (empty($u['enrollments'])): ?>
                                                <span class="text-slate-400 text-[11px] italic">No batch assigned</span>
                                            <?php else: ?>
                                                <?php foreach ($u['enrollments'] as $enr): ?>
                                                    <div class="flex items-center justify-between gap-1 px-2 py-1 bg-slate-100 rounded-lg text-[10px]">
                                                        <div class="truncate">
                                                            <strong class="text-slate-800"><?= htmlspecialchars($enr['course_title'] ?? 'Batch') ?></strong>
                                                            <?php if (!empty($enr['subject_title'])): ?>
                                                                <span class="text-indigo-600 font-semibold">• <?= htmlspecialchars($enr['subject_title']) ?></span>
                                                            <?php else: ?>
                                                                <span class="text-emerald-600 font-semibold">• All Subjects</span>
                                                            <?php endif; ?>
                                                            <div class="text-[9px] text-slate-500">
                                                                Exp: <?= htmlspecialchars($enr['validity_date'] ?: 'Lifetime Access') ?>
                                                            </div>
                                                        </div>
                                                        <button onclick="revokeEnrollment(<?= $enr['enrollment_id'] ?>)" 
                                                                title="Revoke access"
                                                                class="text-rose-500 hover:text-rose-700 p-0.5">
                                                            <i data-lucide="x" class="w-3 h-3"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <button onclick='openAssignModal(<?= json_encode($u) ?>)'
                                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:text-indigo-700 mt-1">
                                                <i data-lucide="plus-circle" class="w-3 h-3"></i> Assign Batch / Subject
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Status Active / Deactive -->
                                    <td class="py-3.5 px-4 text-center">
                                        <button onclick="toggleUserStatus(<?= $u['id'] ?>, <?= $u['status'] == 1 ? 0 : 1 ?>)"
                                                title="Click to toggle Active / Deactive"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold transition <?= $u['status'] == 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $u['status'] == 1 ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                            <?= $u['status'] == 1 ? 'Active' : 'Deactivated' ?>
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button onclick="openChangePasswordModal(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                                                    title="Change Password"
                                                    class="p-1.5 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 rounded-lg transition">
                                                <i data-lucide="key-round" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick='openAssignModal(<?= json_encode($u) ?>)'
                                                    title="Assign Batch Access"
                                                    class="p-1.5 hover:bg-emerald-50 text-slate-500 hover:text-emerald-600 rounded-lg transition">
                                                <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteStudent(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')" 
                                                    title="Delete Student" 
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

<!-- CHANGE PASSWORD MODAL -->
<div id="passwordModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-sm font-extrabold text-slate-900">Change Student Password</h3>
            <button onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form onsubmit="handleChangePasswordSubmit(event)" class="p-6 space-y-4">
            <input type="hidden" id="pwdUserId" value="0">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Student</label>
                <p id="pwdStudentName" class="text-xs font-semibold text-indigo-700 bg-indigo-50 px-3 py-2 rounded-xl"></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">New Password *</label>
                <input type="text" id="newPasswordInput" required minlength="4"
                       placeholder="Enter new password (e.g. Pass1234)"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closePasswordModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSavePassword" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ASSIGN BATCH & SUBJECT WITH VALIDITY MODAL -->
<div id="assignModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-sm font-extrabold text-slate-900">Assign Batch / Subject Access</h3>
            <button onclick="closeAssignModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form onsubmit="handleAssignSubmit(event)" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" id="assignUserId" value="0">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Assigning Access To</label>
                <p id="assignStudentName" class="text-xs font-semibold text-indigo-700 bg-indigo-50 px-3 py-2 rounded-xl"></p>
            </div>

            <!-- Batch Dropdown -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Select Batch *</label>
                <select id="assignCourseId" required onchange="onAssignBatchChanged()" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="">-- Choose Batch --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Subject Dropdown -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Select Subject Scope</label>
                <select id="assignSubjectId" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="0">⭐ All Subjects in this Batch (Full Course Access)</option>
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Choose "All Subjects" or pick a specific subject (e.g. only Mathematics by Bhutesh Sir)</p>
            </div>

            <!-- Validity Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Access Validity</label>
                <div class="grid grid-cols-4 gap-2 mb-2">
                    <button type="button" onclick="setValidityPreset('1m')" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs font-medium hover:bg-indigo-50 hover:text-indigo-600 transition">1 Month</button>
                    <button type="button" onclick="setValidityPreset('3m')" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs font-medium hover:bg-indigo-50 hover:text-indigo-600 transition">3 Months</button>
                    <button type="button" onclick="setValidityPreset('1y')" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs font-medium hover:bg-indigo-50 hover:text-indigo-600 transition">1 Year</button>
                    <button type="button" onclick="setValidityPreset('life')" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs font-medium hover:bg-indigo-50 hover:text-indigo-600 transition">Lifetime</button>
                </div>
                <input type="date" id="assignValidityDate" 
                       class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                <span class="text-[10px] text-slate-400 block mt-1">Leave empty or select lifetime for unlimited access</span>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeAssignModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSaveAssign" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition">
                    Grant Access
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const allSubjectsList = <?= json_encode($subjects) ?>;

function filterUsers() {
    var query = document.getElementById('userSearch').value.toLowerCase();
    var rows = document.querySelectorAll('.user-row');
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        var email = row.getAttribute('data-email') || '';
        var phone = row.getAttribute('data-phone') || '';
        var device = row.getAttribute('data-device') || '';
        if (name.includes(query) || email.includes(query) || phone.includes(query) || device.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openChangePasswordModal(userId, name) {
    document.getElementById('pwdUserId').value = userId;
    document.getElementById('pwdStudentName').innerText = name + ' (ID #' + userId + ')';
    document.getElementById('newPasswordInput').value = '';
    document.getElementById('passwordModal').classList.remove('hidden');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.add('hidden');
}

async function handleChangePasswordSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSavePassword');
    btn.disabled = true;
    btn.innerText = 'Updating...';

    const userId = document.getElementById('pwdUserId').value;
    const newPassword = document.getElementById('newPasswordInput').value.trim();

    try {
        const res = await fetch('/api/admin_crud.php?action=change_user_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, new_password: newPassword })
        });
        const json = await res.json();
        if (json.status) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message || 'Failed to update password');
        }
    } catch (e) {
        alert('Communication error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'Update Password';
    }
}

async function verifyUserOtp(userId, name) {
    if (!confirm('Verify and activate account for student "' + name + '"?')) return;
    try {
        const res = await fetch('/api/admin_crud.php?action=verify_user_otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const json = await res.json();
        if (json.status) {
            window.location.reload();
        } else {
            alert(json.message || 'Error verifying OTP');
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

async function unbindDevice(userId, name) {
    if (!confirm('Unbind smartphone lock for "' + name + '"?\n\nThe student will be able to log in from their new smartphone.')) return;
    try {
        const res = await fetch('/api/admin_crud.php?action=unbind_user_device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const json = await res.json();
        if (json.status) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message || 'Error unbinding device');
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

async function toggleUserStatus(userId, newStatus) {
    try {
        const res = await fetch('/api/admin_crud.php?action=toggle_user_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, status: newStatus })
        });
        const json = await res.json();
        if (json.status) {
            window.location.reload();
        } else {
            alert(json.message || 'Error changing status');
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

function openAssignModal(u) {
    document.getElementById('assignUserId').value = u.id;
    document.getElementById('assignStudentName').innerText = u.name + ' (' + u.email + ')';
    document.getElementById('assignCourseId').value = '';
    document.getElementById('assignSubjectId').innerHTML = '<option value="0">⭐ All Subjects in this Batch (Full Course Access)</option>';
    document.getElementById('assignValidityDate').value = '';
    document.getElementById('assignModal').classList.remove('hidden');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

function onAssignBatchChanged() {
    const courseId = parseInt(document.getElementById('assignCourseId').value) || 0;
    const subSelect = document.getElementById('assignSubjectId');
    subSelect.innerHTML = '<option value="0">⭐ All Subjects in this Batch (Full Course Access)</option>';

    if (courseId > 0) {
        const filtered = allSubjectsList.filter(s => s.course_id == courseId);
        filtered.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = '📚 ' + s.title;
            subSelect.appendChild(opt);
        });
    }
}

function setValidityPreset(preset) {
    const dateInput = document.getElementById('assignValidityDate');
    const now = new Date();
    if (preset === '1m') {
        now.setMonth(now.getMonth() + 1);
        dateInput.value = now.toISOString().split('T')[0];
    } else if (preset === '3m') {
        now.setMonth(now.getMonth() + 3);
        dateInput.value = now.toISOString().split('T')[0];
    } else if (preset === '1y') {
        now.setFullYear(now.getFullYear() + 1);
        dateInput.value = now.toISOString().split('T')[0];
    } else if (preset === 'life') {
        dateInput.value = '';
    }
}

async function handleAssignSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveAssign');
    btn.disabled = true;
    btn.innerText = 'Granting...';

    const userId = document.getElementById('assignUserId').value;
    const courseId = document.getElementById('assignCourseId').value;
    const subjectId = document.getElementById('assignSubjectId').value;
    const validityDate = document.getElementById('assignValidityDate').value;

    try {
        const res = await fetch('/api/admin_crud.php?action=assign_user_enrollment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                course_id: courseId,
                subject_id: subjectId,
                validity_date: validityDate
            })
        });
        const json = await res.json();
        if (json.status) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message || 'Error assigning access');
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'Grant Access';
    }
}

async function revokeEnrollment(enrollmentId) {
    if (!confirm('Revoke this access from the student?')) return;
    try {
        const res = await fetch('/api/admin_crud.php?action=delete_user_enrollment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: enrollmentId })
        });
        const json = await res.json();
        if (json.status) {
            window.location.reload();
        } else {
            alert(json.message || 'Error revoking enrollment');
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

async function deleteStudent(id, name) {
    if (!confirm('Are you sure you want to completely remove student account "' + name + '"?')) return;

    try {
        var res = await fetch('/api/admin_crud.php?action=delete_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        var json = await res.json();
        if (json.status) {
            window.location.reload();
        } else {
            alert(json.message || 'Failed to delete student');
        }
    } catch (e) {
        alert('Network error while deleting student: ' + e.message);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>