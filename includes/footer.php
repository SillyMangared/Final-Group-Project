</main>
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            <?php if(is_logged_in()): ?>
                <div class="theme-toggle">
    <button type="button" class="btn-theme" data-current-mode="<?= $_SESSION['dark_mode'] ? 1 : 0 ?>">
        <i class="fas fa-<?= $_SESSION['dark_mode'] ? 'sun' : 'moon' ?>"></i>
        <?= $_SESSION['dark_mode'] ? 'Light Mode' : 'Dark Mode' ?>
    </button>
</div>
            <?php endif; ?>
        </div>
    </footer>
    <script src="assets/js/main.js"></script>
</body>
</html>