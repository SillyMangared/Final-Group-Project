<?php
require_once 'includes/header.php';

// Handle dark mode toggle
if(isset($_POST['toggle_dark_mode']) && is_logged_in()) {
    toggle_dark_mode($_SESSION['user_id'], $_POST['toggle_dark_mode']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get recent items
$items = get_items(6);
?>

<section class="hero">
    <div class="hero-content">
        <h2>Welcome to <?= SITE_NAME ?></h2>
        <p>Share your music and discover new artists</p>
        <?php if(!is_logged_in()): ?>
            <a href="login.php" class="btn btn-primary">Get Started</a>
        <?php else: ?>
            <a href="profile.php" class="btn btn-primary">Upload Music</a>
        <?php endif; ?>
    </div>
</section>

<section class="recent-items">
    <h2>Recent Uploads</h2>
    <div class="item-grid">
        <?php foreach($items as $item): ?>
            <div class="item-card">
                <a href="item.php?id=<?= $item['id'] ?>">
                    <div class="item-thumbnail">
                        <img src="assets/uploads/thumbnails/<?= $item['thumbnail'] ?>" alt="<?= $item['title'] ?>">
                        <span class="item-type"><?= ucfirst($item['type']) ?></span>
                    </div>
                    <div class="item-info">
                        <h3><?= $item['title'] ?></h3>
                        <p>By <?= $item['username'] ?></p>
                        <div class="item-meta">
                            <span><i class="fas fa-star"></i> <?= number_format($item['avg_rating'], 1) ?></span>
                            <span><i class="fas fa-comment"></i> <?= $item['review_count'] ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="view-all">
        <a href="browse.php" class="btn btn-secondary">View All</a>
    </div>
</section>

<section class="features">
    <h2>Features</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <i class="fas fa-upload"></i>
            <h3>Share Your Music</h3>
            <p>Upload your songs and showcase your talent</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-headphones"></i>
            <h3>Discover New Music</h3>
            <p>Browse through a collection of songs from various artists</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-star"></i>
            <h3>Rate & Review</h3>
            <p>Leave feedback and ratings on your favorite tracks</p>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>