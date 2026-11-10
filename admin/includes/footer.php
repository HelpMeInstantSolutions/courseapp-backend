<?php
// backend/admin/includes/footer.php
?>
    <!-- Common Admin Scripts & Notification Handlers -->
    <script>
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Global Toast Notification Helper
        function showToast(message, type = 'success') {
            var bg = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#6366f1');
            Toastify({
                text: message,
                duration: 3500,
                close: true,
                gravity: "top",
                position: "right",
                style: {
                    background: bg,
                    borderRadius: "12px",
                    fontWeight: "600",
                    fontSize: "13px",
                    boxShadow: "0 10px 15px -3px rgba(0, 0, 0, 0.1)"
                }
            }).showToast();
        }

        // Global Logout Handler
        async function handleLogout() {
            if (!confirm('Are you sure you want to sign out?')) return;
            try {
                await fetch('/api/admin_crud.php?action=logout');
            } catch (e) {}
            localStorage.removeItem('admin_logged');
            localStorage.removeItem('admin_user');
            window.location.href = '/admin/login/';
        }
    </script>
</body>
</html>
