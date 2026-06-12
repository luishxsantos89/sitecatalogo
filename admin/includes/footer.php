        </div> <!-- /admin-content -->
        
        <footer class="admin-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo sanitize(get_config('site_name', 'SiteCatalogo')); ?> - v1.0.0</p>
        </footer>
    </div> <!-- /admin-main -->
</div> <!-- /admin-wrapper -->

<script>
// Toggle sidebar
document.getElementById('menuToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});

document.getElementById('sidebarClose')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('open');
});

// User dropdown
document.getElementById('userMenu')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('userDropdown').classList.toggle('open');
});

document.addEventListener('click', function() {
    document.getElementById('userDropdown')?.classList.remove('open');
});

// Confirm delete
document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        if (!confirm('Tem certeza que deseja excluir este item? Esta acao nao pode ser desfeita.')) {
            e.preventDefault();
        }
    });
});

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(function() {
            alert.style.display = 'none';
        }, 300);
    });
}, 5000);
</script>
</body>
</html>
