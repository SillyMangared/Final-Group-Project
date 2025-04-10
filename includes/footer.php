</main>
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            <?php if(is_logged_in()): ?>
                <div class="theme-toggle">
                    <form action="index.php" method="post">
                        <input type="hidden" name="toggle_dark_mode" value="<?= $_SESSION['dark_mode'] ? 0 : 1 ?>">
                        <button type="submit" class="btn-theme">
                            <i class="fas fa-<?= $_SESSION['dark_mode'] ? 'sun' : 'moon' ?>"></i>
                            <?= $_SESSION['dark_mode'] ? 'Light Mode' : 'Dark Mode' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </footer>
    <script src="assets/js/main.js"></script>
</body>
</html>