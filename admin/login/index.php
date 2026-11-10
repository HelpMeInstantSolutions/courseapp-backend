<?php
// backend/admin/login/index.php
session_start();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | CourseApp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-6 bg-slate-50">

    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200/90 shadow-xl overflow-hidden p-8 sm:p-10">
        <!-- Logo & Brand Header -->
        <div class="text-center">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center mx-auto shadow-lg shadow-indigo-500/25 mb-4">
                <i data-lucide="shield-check" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Admin Portal</h1>
            <p class="text-xs text-slate-500 mt-1">Insight CourseApp Management &bull; courseapp.mybhiwani.in</p>
        </div>

        <!-- Login Form -->
        <form onsubmit="handleAdminLogin(event)" class="mt-8 space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Username</label>
                <div class="relative">
                    <i data-lucide="user" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="username" required value="admin" 
                           placeholder="admin" 
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="password" id="password" required value="admin" 
                           placeholder="••••••••" 
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                </div>
                <p class="text-[11px] text-slate-400 mt-2">
                    Default credentials: <span class="font-mono font-semibold text-slate-600">admin</span> / <span class="font-mono font-semibold text-slate-600">admin</span>
                </p>
            </div>

            <button type="submit" id="btnLogin" 
                    class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/25 transition duration-150 flex items-center justify-center gap-2">
                <span>Sign In To Admin</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Secure Dailymotion OAuth Ready
            </span>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // If already logged in, redirect to dashboard
        if (localStorage.getItem('admin_logged') === 'true') {
            window.location.href = '/admin/';
        }

        async function handleAdminLogin(e) {
            e.preventDefault();
            var btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerHTML = 'Signing in...';

            var u = document.getElementById('username').value.trim();
            var p = document.getElementById('password').value.trim();

            try {
                var res = await fetch('/api/admin_crud.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: u, password: p })
                });
                var json = await res.json();

                if (json.status) {
                    localStorage.setItem('admin_logged', 'true');
                    localStorage.setItem('admin_user', json.data ? json.data.name : 'Admin');
                    Toastify({
                        text: "Welcome back! Redirecting...",
                        duration: 1500,
                        gravity: "top",
                        position: "right",
                        style: { background: "#10b981", borderRadius: "10px", fontSize: "13px" }
                    }).showToast();
                    setTimeout(function() {
                        window.location.href = '/admin/';
                    }, 500);
                } else {
                    Toastify({
                        text: json.message || "Invalid credentials",
                        duration: 3500,
                        gravity: "top",
                        position: "right",
                        style: { background: "#ef4444", borderRadius: "10px", fontSize: "13px" }
                    }).showToast();
                    btn.disabled = false;
                    btn.innerHTML = '<span>Sign In To Admin</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
                    lucide.createIcons();
                }
            } catch (err) {
                Toastify({
                    text: "Network error during login",
                    duration: 3500,
                    gravity: "top",
                    position: "right",
                    style: { background: "#ef4444", borderRadius: "10px", fontSize: "13px" }
                }).showToast();
                btn.disabled = false;
                btn.innerHTML = '<span>Sign In To Admin</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
