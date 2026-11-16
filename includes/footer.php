        </main>
        <!-- Persistent Institutional Footer -->
        <footer style="padding: 18px 28px; border-top: 1px solid var(--border-card); background: var(--bg-surface); display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted); flex-wrap: wrap; gap: 10px;">
            <div>Islamia College of Technology • Matta Swat, Khyber Pakhtunkhwa</div>
            <div style="color: var(--text-dim);">College Management System • Secured with RBAC & CSRF</div>
        </footer>
    </div>
</div>

<!-- Global Toast Container -->
<div id="toast-container" class="toast-container"></div>

<!-- Core Application Script -->
<script src="<?= $baseUri ?>/assets/js/app.js"></script>

<?php
$flash = getFlashMessage();
if ($flash):
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif; ?>
</body>
</html>
