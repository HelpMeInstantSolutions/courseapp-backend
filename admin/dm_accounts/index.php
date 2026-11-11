<?php
// backend/admin/dm_accounts/index.php - Dailymotion Multi-Account Manager
$pageTitle = 'Dailymotion Accounts';
$activePage = 'dm_accounts';
$headerTitle = 'Dailymotion Multi-Account Manager';
$headerSubtitle = 'Connect multiple Dailymotion partner accounts to bypass upload limits and stream private videos seamlessly';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Fetch all connected accounts with linked video counts
$dmAccounts = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, 
               (SELECT COUNT(*) FROM videos v WHERE v.dm_account_id = a.id) as video_count 
        FROM dm_accounts a 
        ORDER BY a.is_default DESC, a.id ASC
    ");
    $dmAccounts = $stmt->fetchAll();
} catch (Exception $e) {}

$totalVideosLinked = 0;
foreach ($dmAccounts as $a) {
    $totalVideosLinked += (int)$a['video_count'];
}
?>

<!-- MAIN CONTENT WRAPPER (INDEPENDENT SCROLL) -->
<div class="flex-1 h-full overflow-y-auto flex flex-col min-w-0 bg-slate-50 relative">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="p-6 md:p-8 space-y-6 max-w-7xl">
        <!-- Top Banner / Highlights -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Connected Accounts</p>
                    <p class="text-2xl font-extrabold text-slate-800 mt-1"><?= count($dmAccounts) ?></p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                    <i data-lucide="cloud-cog" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Videos Managed</p>
                    <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= $totalVideosLinked ?></p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                    <i data-lucide="video" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Default Channel</p>
                    <p class="text-sm font-extrabold text-slate-800 mt-1 truncate max-w-[160px]">
                        <?= htmlspecialchars($dmAccounts[0]['channel_name'] ?? $dmAccounts[0]['name'] ?? 'None') ?>
                    </p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                    <i data-lucide="star" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Stream Security</p>
                    <p class="text-xs font-extrabold text-indigo-700 mt-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Auto Token Refresh
                    </p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <!-- Top Action Bar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div>
                <h2 class="text-base font-bold text-slate-900">Connected Dailymotion Partner Accounts</h2>
                <p class="text-xs text-slate-500">Each account can host private videos without interfering with other accounts.</p>
            </div>
            <button onclick="openAddAccountModal()" 
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm shrink-0">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Connect New Account</span>
            </button>
        </div>

        <!-- Connected Accounts Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                            <th class="py-3.5 px-4">Account Label</th>
                            <th class="py-3.5 px-4">Channel / Screenname</th>
                            <th class="py-3.5 px-4">Account UID / Email</th>
                            <th class="py-3.5 px-4">API Key (Client ID)</th>
                            <th class="py-3.5 px-4">Videos Linked</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($dmAccounts)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i data-lucide="cloud-off" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                    <p class="text-sm font-semibold">No Dailymotion accounts connected yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Click "Connect New Account" above to link your first account.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dmAccounts as $acc): ?>
                                <tr class="hover:bg-slate-50/60 transition group">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-bold flex items-center justify-center shrink-0">
                                                <i data-lucide="user-check" class="w-4 h-4"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-900 flex items-center gap-1.5">
                                                    <?= htmlspecialchars($acc['name']) ?>
                                                    <?php if ($acc['is_default']): ?>
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                            <i data-lucide="star" class="w-3 h-3 fill-current text-amber-500"></i> Default
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <span class="text-[10px] text-slate-400">Connected: <?= date('d M Y', strtotime($acc['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-semibold text-slate-800 bg-slate-100 px-2 py-1 rounded-md text-[11px] inline-flex items-center gap-1">
                                            <i data-lucide="tv" class="w-3 h-3 text-slate-500"></i>
                                            <?= htmlspecialchars($acc['channel_name'] ?: 'Channel') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-mono text-[11px] text-indigo-700 font-medium"><?= htmlspecialchars($acc['uid'] ?: 'N/A') ?></span>
                                            <?php if (!empty($acc['email'])): ?>
                                                <span class="text-[10px] text-slate-400 truncate max-w-[180px]"><?= htmlspecialchars($acc['email']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-mono text-[11px] text-slate-600 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded">
                                            <?= htmlspecialchars(substr($acc['client_id'], 0, 8)) ?>...
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <a href="/admin/videos/?account_id=<?= $acc['id'] ?>" class="inline-flex items-center gap-1 font-semibold text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full hover:bg-emerald-100 transition">
                                            <i data-lucide="film" class="w-3 h-3"></i>
                                            <?= (int)$acc['video_count'] ?> Videos
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button onclick="editAccount(<?= htmlspecialchars(json_encode($acc), ENT_QUOTES, 'UTF-8') ?>)" 
                                                    title="Edit Account" 
                                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteAccount(<?= $acc['id'] ?>, '<?= addslashes(htmlspecialchars($acc['name'])) ?>')" 
                                                    title="Disconnect Account" 
                                                    class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
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

        <!-- Built-in Setup Guide Card -->
        <div class="bg-indigo-50/50 border border-indigo-100 rounded-2xl p-6 space-y-3">
            <div class="flex items-center gap-2 text-indigo-900 font-bold text-sm">
                <i data-lucide="help-circle" class="w-4 h-4 text-indigo-600"></i>
                <span>How to connect another Dailymotion account (2-Minute Setup):</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600">
                <div class="bg-white p-4 rounded-xl border border-indigo-100/60 shadow-xs space-y-1">
                    <p class="font-bold text-indigo-900 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-extrabold">1</span>
                        Partner Portal
                    </p>
                    <p class="text-slate-500">Log in to your 2nd or 3rd Dailymotion account at <a href="https://www.dailymotion.com/partner" target="_blank" class="text-indigo-600 font-semibold underline">dailymotion.com/partner</a>.</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-indigo-100/60 shadow-xs space-y-1">
                    <p class="font-bold text-indigo-900 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-extrabold">2</span>
                        Create API Key
                    </p>
                    <p class="text-slate-500">Go to <strong>Settings ➔ API Keys</strong>, click <strong>Create New API Key</strong> to obtain your <strong>API Key (Client ID)</strong> & <strong>API Secret</strong>.</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-indigo-100/60 shadow-xs space-y-1">
                    <p class="font-bold text-indigo-900 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-extrabold">3</span>
                        Save in App
                    </p>
                    <p class="text-slate-500">Paste your credentials and Refresh Token into this form. The system will automatically verify and connect it!</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ADD / EDIT ACCOUNT MODAL -->
<div id="accountModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                    <i data-lucide="cloud-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-base font-bold text-slate-900">Connect Dailymotion Account</h3>
                    <p class="text-xs text-slate-400">Link another Dailymotion channel for private video streaming</p>
                </div>
            </div>
            <button onclick="closeAccountModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-50">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="accountForm" onsubmit="handleSaveAccount(event)" class="p-6 space-y-4">
            <input type="hidden" id="account_id" value="0">

            <!-- Account Label / Name -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Account Label / Purpose *</label>
                <input type="text" id="acc_name" required 
                       placeholder="e.g. Account 2 (Reasoning & GK Videos)" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- API Key (Client ID) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Dailymotion API Key (Client ID) *</label>
                <input type="text" id="acc_client_id" required 
                       placeholder="e.g. 2f565bdf5ec09e7464db" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- API Secret (Client Secret) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">API Secret (Client Secret) *</label>
                <input type="password" id="acc_client_secret" required 
                       placeholder="e.g. 6494629f246182ce9acd3c066a7ab5fae5598a06" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Refresh Token -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Refresh Token *</label>
                <input type="text" id="acc_refresh_token" required 
                       placeholder="e.g. 66e1c1b82878cc7db7c3b3689800eb7b96091b8a" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                <p class="text-[11px] text-slate-400 mt-1">This token allows our system to auto-renew access tokens 24/7 without logging in again.</p>
            </div>

            <!-- Set as Default Account -->
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="acc_is_default" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                <label for="acc_is_default" class="text-xs font-semibold text-slate-700">Set as Primary / Default Account for new uploads</label>
            </div>

            <!-- Test Connection Result Status -->
            <p id="accountVerifyStatus" class="text-xs hidden"></p>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeAccountModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" id="btnSaveAccount" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Verify & Save Account</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddAccountModal() {
        document.getElementById('account_id').value = '0';
        document.getElementById('acc_name').value = '';
        document.getElementById('acc_client_id').value = '';
        document.getElementById('acc_client_secret').value = '';
        document.getElementById('acc_refresh_token').value = '';
        document.getElementById('acc_is_default').checked = false;
        document.getElementById('modalTitle').innerText = 'Connect Dailymotion Account';
        document.getElementById('accountVerifyStatus').classList.add('hidden');
        document.getElementById('accountModal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function editAccount(acc) {
        document.getElementById('account_id').value = acc.id;
        document.getElementById('acc_name').value = acc.name || '';
        document.getElementById('acc_client_id').value = acc.client_id || '';
        document.getElementById('acc_client_secret').value = acc.client_secret || '';
        document.getElementById('acc_refresh_token').value = acc.refresh_token || '';
        document.getElementById('acc_is_default').checked = acc.is_default == 1;
        document.getElementById('modalTitle').innerText = 'Edit Dailymotion Account';
        document.getElementById('accountVerifyStatus').classList.add('hidden');
        document.getElementById('accountModal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeAccountModal() {
        document.getElementById('accountModal').classList.add('hidden');
    }

    async function handleSaveAccount(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSaveAccount');
        var statusEl = document.getElementById('accountVerifyStatus');
        btn.disabled = true;
        btn.innerText = 'Verifying with Dailymotion...';

        statusEl.className = 'text-xs text-indigo-600 font-semibold block';
        statusEl.innerText = 'Testing credentials with Dailymotion OAuth API...';

        var payload = {
            id: document.getElementById('account_id').value,
            name: document.getElementById('acc_name').value.trim(),
            client_id: document.getElementById('acc_client_id').value.trim(),
            client_secret: document.getElementById('acc_client_secret').value.trim(),
            refresh_token: document.getElementById('acc_refresh_token').value.trim(),
            is_default: document.getElementById('acc_is_default').checked ? 1 : 0
        };

        try {
            var res = await fetch('/api/admin_crud.php?action=save_dm_account', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var json = await res.json();
            if (json.status) {
                statusEl.className = 'text-xs text-emerald-600 font-semibold block';
                statusEl.innerText = '✓ Success! Connected Channel: ' + (json.data ? json.data.channel_name : '');
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 900);
            } else {
                statusEl.className = 'text-xs text-rose-600 font-semibold block';
                statusEl.innerText = '✕ ' + (json.message || 'Verification failed');
                showToast(json.message || 'Verification failed', 'error');
            }
        } catch (err) {
            statusEl.className = 'text-xs text-rose-600 font-semibold block';
            statusEl.innerText = '✕ Network error verifying credentials';
            showToast('Network error verifying credentials', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Verify & Save Account';
        }
    }

    async function deleteAccount(id, name) {
        if (!confirm('Are you sure you want to disconnect "' + name + '"?\nAny videos linked to this account will still remain but might need another account to stream.')) {
            return;
        }

        try {
            var res = await fetch('/api/admin_crud.php?action=delete_dm_account', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            var json = await res.json();
            if (json.status) {
                showToast(json.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(json.message || 'Failed to remove account', 'error');
            }
        } catch (err) {
            showToast('Network error removing account', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
