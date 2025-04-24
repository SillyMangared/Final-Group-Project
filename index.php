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

// Get recent playlists
$playlists = get_playlists(6);

// Get recent albums
$albums = get_albums(6);
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

<section class="recent-playlists">
    <h2>Recent Playlists</h2>
    <div class="item-grid">
        <?php foreach($playlists as $playlist): ?>
            <div class="playlist-card" data-id="<?= $playlist['id'] ?>">
                <div class="playlist-thumbnail">
                    <img src="assets/uploads/thumbnails/<?= $playlist['thumbnail'] ?>" alt="<?= $playlist['title'] ?>">
                    <span class="playlist-type">Playlist</span>
                </div>
                <div class="playlist-info">
                    <h3><?= $playlist['title'] ?></h3>
                    <p>By <?= $playlist['username'] ?></p>
                    <div class="playlist-meta">
                        <span><i class="fas fa-music"></i> <?= $playlist['song_count'] ?> songs</span>
                    </div>
                </div>
            </div>
            <!-- Playlist Modal -->
            <div class="modal" id="playlist-modal-<?= $playlist['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?= htmlspecialchars($playlist['title']) ?></h2>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="song-list">
                        <?php
                        $songs = get_playlist_songs($playlist['id']);
                        if (empty($songs)):
                        ?>
                            <p>No songs in this playlist.</p>
                        <?php else: ?>
                            <?php foreach($songs as $song): ?>
                                <div class="song-item">
                                    <img src="assets/uploads/thumbnails/<?= htmlspecialchars($song['thumbnail']) ?>" alt="<?= htmlspecialchars($song['title']) ?>">
                                    <div class="song-info">
                                        <h4><a href="item.php?id=<?= $song['id'] ?>"><?= htmlspecialchars($song['title']) ?></a></h4>
                                        <p>By <?= htmlspecialchars($song['username']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="view-all">
        <a href="browse.php?type=playlist" class="btn btn-secondary">View All Playlists</a>
    </div>
</section>

<section class="recent-albums">
    <h2>Recent Albums</h2>
    <div class="item-grid">
        <?php foreach($albums as $album): ?>
            <div class="album-card" data-id="<?= $album['id'] ?>">
                <div class="album-thumbnail">
                    <img src="assets/uploads/thumbnails/<?= $album['thumbnail'] ?>" alt="<?= $album['title'] ?>">
                    <span class="album-type">Album</span>
                </div>
                <div class="album-info">
                    <h3><?= $album['title'] ?></h3>
                    <p>By <?= $album['username'] ?></p>
                    <div class="album-meta">
                        <span><i class="fas fa-music"></i> <?= $album['song_count'] ?> songs</span>
                    </div>
                </div>
            </div>
            <!-- Album Modal -->
            <div class="modal" id="album-modal-<?= $album['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?= htmlspecialchars($album['title']) ?></h2>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="song-list">
                        <?php
                        $songs = get_album_songs($album['id']);
                        if (empty($songs)):
                        ?>
                            <p>No songs in this album.</p>
                        <?php else: ?>
                            <?php foreach($songs as $song): ?>
                                <div class="song-item">
                                    <img src="assets/uploads/thumbnails/<?= htmlspecialchars($song['thumbnail']) ?>" alt="<?= htmlspecialchars($song['title']) ?>">
                                    <div class="song-info">
                                        <h4><a href="item.php?id=<?= $song['id'] ?>"><?= htmlspecialchars($song['title']) ?></a></h4>
                                        <p>By <?= htmlspecialchars($song['username']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="view-all">
        <a href="browse.php?type=album" class="btn btn-secondary">View All Albums</a>
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