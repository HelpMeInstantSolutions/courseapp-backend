<?php
// backend/admin/queries/index.php - Student Help & Doubt Queries Desk
$pageTitle = 'Student Queries';
$activePage = 'queries';
$headerTitle = 'Student Queries & Doubts';
$headerSubtitle = 'Review student questions, doubt submissions, and send official replies';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$filterStatus = $_GET['status'] ?? 'all';

$queries = [];
try {
    $sql = "SELECT q.*, u.device_model 
            FROM user_queries q 
            LEFT JOIN users u ON q.user_id = u.id 
            WHERE 1=1";
    $params = [];
    if ($filterStatus && $filterStatus !== 'all') {
        $sql .= " AND q.status = ?";
        $params[] = $filterStatus;
    }
    $sql .= " ORDER BY q.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $queries = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- MAIN CONTENT WRAPPER -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Filter Tabs & Stats -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-2">
                <a href="/admin/queries/?status=all" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= $filterStatus === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    All Queries
                </a>
                <a href="/admin/queries/?status=pending" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= $filterStatus === 'pending' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' ?>">
                    Pending (<?= $serverStats['queries'] ?? 0 ?>)
                </a>
                <a href="/admin/queries/?status=resolved" 
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= $filterStatus === 'resolved' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                    Resolved
                </a>
            </div>

            <div class="relative flex-1 max-w-xs">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="querySearch" oninput="filterQueries()" 
                       placeholder="Search by student, topic, message..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>
        </div>

        <!-- Queries Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="queriesTable">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4">Student</th>
                            <th class="py-3.5 px-4">Subject / Topic</th>
                            <th class="py-3.5 px-4">Question / Message</th>
                            <th class="py-3.5 px-4">Admin Reply</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4">Date</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($queries)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="help-circle" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No student queries found</p>
                                    <p class="text-xs text-slate-400 mt-1">Queries submitted via the mobile app Help Desk will appear here.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queries as $q): ?>
                                <tr class="hover:bg-slate-50/70 transition query-row"
                                    data-search="<?= strtolower(htmlspecialchars($q['name'] . ' ' . ($q['phone'] ?? '') . ' ' . ($q['email'] ?? '') . ' ' . $q['subject'] . ' ' . $q['message'])) ?>">
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-400">
                                        #<?= $q['id'] ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div>
                                            <p class="font-bold text-slate-900"><?= htmlspecialchars($q['name']) ?></p>
                                            <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-slate-500 font-mono">
                                                <?php if (!empty($q['phone'])): ?>
                                                    <span><?= htmlspecialchars($q['phone']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($q['email'])): ?>
                                                    <span>• <?= htmlspecialchars($q['email']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-semibold text-[11px] border border-indigo-100">
                                            <?= htmlspecialchars($q['subject'] ?: 'Doubt') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 max-w-xs">
                                        <p class="text-slate-800 line-clamp-2"><?= htmlspecialchars($q['message']) ?></p>
                                    </td>
                                    <td class="py-3.5 px-4 max-w-xs">
                                        <?php if (!empty($q['admin_reply'])): ?>
                                            <p class="text-emerald-700 font-medium line-clamp-2">💬 <?= htmlspecialchars($q['admin_reply']) ?></p>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic text-[11px]">No reply yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <?php if ($q['status'] === 'resolved'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                                <i data-lucide="check" class="w-3 h-3"></i> Resolved
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                                <i data-lucide="clock" class="w-3 h-3"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                        <?= htmlspecialchars(substr($q['created_at'] ?? '', 0, 16)) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button onclick='openReplyModal(<?= json_encode($q, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                    title="Reply to Query"
                                                    class="p-1.5 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 rounded-lg transition">
                                                <i data-lucide="message-square" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteQuery(<?= $q['id'] ?>)" 
                                                    title="Delete Query"
                                                    class="p-1.5 hover:bg-rose-50 text-slate-400 hover:text-rose-600 rounded-lg transition">
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

<!-- REPLY MODAL -->
<div id="replyModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-sm font-extrabold text-slate-900">Reply to Student Query</h3>
            <button onclick="closeReplyModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form onsubmit="handleReplySubmit(event)" class="p-6 space-y-4">
            <input type="hidden" id="replyQueryId" value="0">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Student</label>
                <p id="replyStudentInfo" class="text-xs font-semibold text-slate-800 bg-slate-50 px-3 py-2 rounded-xl border border-slate-200"></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Question / Doubt</label>
                <div id="replyOriginalMessage" class="text-xs text-slate-700 bg-indigo-50/60 p-3 rounded-xl border border-indigo-100"></div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Admin / Faculty Reply *</label>
                <textarea id="replyText" required rows="4"
                          placeholder="Type your official answer or clarification for the student..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                <select id="replyStatus" 
                        class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                    <option value="resolved">✓ Mark as Resolved</option>
                    <option value="pending">⏳ Keep as Pending</option>
                </select>
            </div>
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeReplyModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitReply" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition">
                    Send Reply
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function filterQueries() {
    var query = document.getElementById('querySearch').value.toLowerCase();
    var rows = document.querySelectorAll('.query-row');
    rows.forEach(function(row) {
        var text = row.getAttribute('data-search') || '';
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openReplyModal(q) {
    document.getElementById('replyQueryId').value = q.id;
    document.getElementById('replyStudentInfo').innerText = q.name + ' • ' + (q.phone || q.email || 'App Student');
    document.getElementById('replyOriginalMessage').innerText = q.message;
    document.getElementById('replyText').value = q.admin_reply || '';
    document.getElementById('replyStatus').value = q.status || 'resolved';
    document.getElementById('replyModal').classList.remove('hidden');
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.add('hidden');
}

async function handleReplySubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitReply');
    btn.disabled = true;
    btn.innerText = 'Sending...';

    const id = document.getElementById('replyQueryId').value;
    const reply = document.getElementById('replyText').value.trim();
    const status = document.getElementById('replyStatus').value;

    try {
        const res = await fetch('/api/queries.php?action=reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                admin_reply: reply,
                status: status
            })
        });
        const json = await res.json();
        if (json.status) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message || 'Error replying to query');
        }
    } catch (e) {
        alert('Communication error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'Send Reply';
    }
}

async function deleteQuery(id) {
    if (!confirm('Are you sure you want to delete this query?')) return;
    try {
        const res = await fetch('/api/queries.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const json = await res.json();
        if (json.status) {
            window.location.reload();
        } else {
            alert(json.message || 'Error deleting query');
        }
    } catch (e) {
        alert('Communication error: ' + e.message);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>