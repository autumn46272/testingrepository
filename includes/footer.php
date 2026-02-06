</div> <!-- End .content-container -->
</div> <!-- End .main-content -->
</div> <!-- End .wrapper -->

<div id="toast-container"></div>

<script src="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>assets/js/script.js?v=<?php echo time(); ?>"></script>
<script>
    // Global Toast Notification
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        // Icon
        let icon = 'fa-check-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        if (type === 'error') icon = 'fa-exclamation-circle';

        toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;

        container.appendChild(toast);

        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);

        // Remove after 3s
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Global Modal Click Outside
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>
</body>

</html>