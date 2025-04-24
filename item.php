<?php
require_once 'includes/header.php';

// Debug POST data 
if(isset($_POST) && !empty($_POST)) {
    file_put_contents('debug_post.txt', date('Y-m-d H:i:s') . ' - ' . print_r($_POST, true) . "\n", FILE_APPEND);
}


// Check if item ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$item_id = (int)$_GET['id'];
$item = get_item($item_id);

// Check if item exists
if (!$item) {
    header('Location: index.php');
    exit;
}

// Get related items (same user, same type, or from the same album)
$album_songs = [];
$album = null;
if ($item['type'] == 'audio') {
    $database = new Database();
    $db = $database->connect();
    $stmt = $db->prepare('SELECT a.id, a.title FROM album_items ai JOIN albums a ON ai.album_id = a.id WHERE ai.item_id = ?');
    $stmt->execute([$item_id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($album) {
        $album_songs = get_album_songs($album['id']);
    }
}

$related_items = $album_songs ?: get_items(10, 0, $item['user_id'], $item['type']);

// Handle adding to playlist
$playlistError = '';
$playlistSuccess = '';

if (isset($_POST['add_to_playlist']) && is_logged_in()) {
    $playlist_id = (int)$_POST['playlist_id'];
    $song_id = (int)$_POST['song_id'];
    
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM playlist_items WHERE playlist_id = ? AND item_id = ?');
    $stmt->execute([$playlist_id, $song_id]);
    
    if ($stmt->rowCount() > 0) {
        $playlistError = 'Song is already in this playlist';
    } else {
        $stmt = $db->prepare('SELECT MAX(position) as max_position FROM playlist_items WHERE playlist_id = ?');
        $stmt->execute([$playlist_id]);
        $max_position = $stmt->fetch(PDO::FETCH_ASSOC)['max_position'];
        $position = $max_position ? $max_position + 1 : 1;
        
        if (add_song_to_playlist($playlist_id, $song_id, $position)) {
            $playlistSuccess = 'Song added to playlist successfully';
        } else {
            $playlistError = 'Failed to add song to playlist';
        }
    }
}

// Initialize variables
$deletePlaylistError = '';
$deletePlaylistSuccess = '';

// Check for success/error messages from redirects
if (isset($_GET['playlist'])) {
    if ($_GET['playlist'] == 'removed') {
        $deletePlaylistSuccess = 'Song removed from playlist successfully';
    } else if ($_GET['playlist'] == 'error' && isset($_SESSION['playlist_error'])) {
        $deletePlaylistError = $_SESSION['playlist_error'];
        unset($_SESSION['playlist_error']); // Clear the error message
    }
}

// Handle removing from playlist
if (isset($_POST['remove_from_playlist']) && is_logged_in()) {
    $playlist_id = (int)$_POST['playlist_id'];
    $song_id = (int)$_POST['song_id'];
    
    // Check if the playlist belongs to the user
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlist_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        // User owns the playlist, proceed with deletion
        if (remove_song_from_playlist($playlist_id, $song_id)) {
            // Set success message
            $deletePlaylistSuccess = 'Song removed from playlist successfully';
            
            // Redirect back to the same page with success message
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $_GET['id'] . '&playlist=removed');
            exit;
        } else {
            // Failed to delete
            $deletePlaylistError = 'Failed to remove song from playlist';
            $_SESSION['playlist_error'] = $deletePlaylistError;
            
            // Redirect with error parameter
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $_GET['id'] . '&playlist=error');
            exit;
        }
    } else {
        // User doesn't own this playlist
        $deletePlaylistError = 'You do not have permission to modify this playlist';
        $_SESSION['playlist_error'] = $deletePlaylistError;
        
        // Redirect with error parameter
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $_GET['id'] . '&playlist=error');
        exit;
    }
}

// Handle review submission, update, and deletion
$reviewError = '';
$reviewSuccess = '';
$userReview = null;

if (isset($_POST['delete_review']) && is_logged_in()) {
    $review_id = (int)$_POST['review_id'];

    if (delete_review($review_id, $_SESSION['user_id'])) {
        $reviewSuccess = 'Review deleted successfully';
        header('Location: item.php?id=' . $item_id . '&review=deleted');
        exit;
    } else {
        $reviewError = 'Failed to delete review';
    }
}

if (isset($_POST['update_review']) && is_logged_in()) {
    if (!isset($_POST['rating']) || !is_numeric($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > 5) {
        $reviewError = 'Please select a valid rating (1-5 stars)';
    } else {
        $review_id = (int)$_POST['review_id'];
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        
        if (update_review($review_id, $_SESSION['user_id'], $rating, $comment)) {
            $reviewSuccess = 'Review updated successfully';
            header('Location: item.php?id=' . $item_id . '&review=updated');
            exit;
        } else {
            $reviewError = 'Failed to update review';
        }
    }
}

if (isset($_POST['submit_review']) && is_logged_in()) {
    if (!isset($_POST['rating']) || !is_numeric($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > 5) {
        $reviewError = 'Please select a valid rating (1-5 stars)';
    } else {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        
        if (create_review($item_id, $_SESSION['user_id'], $rating, $comment)) {
            $reviewSuccess = 'Review submitted successfully';
            header('Location: item.php?id=' . $item_id . '&review=success');
            exit;
        } else {
            $reviewError = 'Failed to submit review. You may have already reviewed this item.';
        }
    }
}

$reviews = get_reviews($item_id);
$hasReviewed = false;
if (is_logged_in()) {
    foreach ($reviews as $review) {
        if ($review['user_id'] == $_SESSION['user_id']) {
            $hasReviewed = true;
            $userReview = $review;
            break;
        }
    }
}

$user_playlists = is_logged_in() ? get_playlists(10, 0, $_SESSION['user_id']) : [];
$recent_playlists = get_playlists(4, 0);
?>

<style>

    .item-container {
        width: 100%;
        max-width: 100%;
        padding: 0 20px; /* Add padding to prevent content from touching the edges */
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        overflow: hidden;
        margin-bottom: 20px;
    }

    .item-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 30px 20px;
        text-align: center;
        color: white;
    }

    .item-header h2 {
        margin: 0;
        font-size: 2rem;
    }

    .item-header p {
        margin: 5px 0;
        color: #b3b3b3;
    }

    .item-header a {
        color: #fff;
        text-decoration: none;
    }

    .item-header a:hover {
        text-decoration: underline;
    }

    .item-main {
        display: flex;
        flex-wrap: wrap;
    }

    .item-media {
        flex: 2;
        min-width: 300px;
        padding: 20px;
        border-right: 1px solid var(--border-color);
    }

    .item-sidebar {
        flex: 1;
        min-width: 300px;
        padding: 20px;
    }

    .audio-player {
        text-align: center;
    }

    .audio-thumbnail {
        width: 100%;
        max-width: 300px;
        height: auto;
        border-radius: 8px;
        margin: 0 auto 15px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    }

    .video-player video {
        width: 100%;
        border-radius: 8px;
    }

    .url-course img {
        width: 100%;
        max-width: 300px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .youtube-embed iframe {
        width: 100%;
        height: 400px;
        border-radius: 8px;
    }

    .song-info h2 {
        margin: 0 0 5px;
        font-size: 20px;
    }

    .song-info p {
        margin: 0;
        color: #b3b3b3;
        font-size: 16px;
    }

    .controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin: 20px 0;
    }

    .control-btn {
        background:rgb(73, 169, 107);
        border: none;
        color:  rgba(57, 149, 230, 0.85);
        font-size: 24px;
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color var(--transition-speed);
    }

    .control-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .play-btn {
        background-color: var(--success-color);
        width: 50px;
        height: 50px;
        font-size: 20px;
    }

    .play-btn:hover {
        background-color: #1ed760;
        transform: scale(1.05);
    }

    .progress-container {
        padding: 0 20px;
        margin-top: 10px;
    }

    .progress-bar {
        height: 4px;
        border-radius: 2px;
        background-color: #535353;
        margin-bottom: 5px;
        position: relative;
        cursor: pointer;
    }

    .progress {
        background-color: var(--success-color);
        height: 100%;
        border-radius: 2px;
        width: 0%;
        transition: width 0.1s linear;
    }

    .time-stamps {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #b3b3b3;
    }

    .playlist {
        max-height: 300px;
        overflow-y: auto;
        padding: 0;
        margin: 0;
        list-style-type: none;
    }

    .playlist-item {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        border-bottom: 1px solid #282828;
        cursor: pointer;
        transition: background-color var(--transition-speed);
    }

    .playlist-item:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .playlist-item.active {
        background-color: rgba(29, 185, 84, 0.2);
        color: var(--success-color);
    }

    .playlist-item-number {
        margin-right: 15px;
        color: #b3b3b3;
        font-size: 14px;
        width: 20px;
    }

    .playlist-item-info {
        flex-grow: 1;
    }

    .playlist-item-title {
        margin: 0;
        font-size: 16px;
    }

    .playlist-item-artist {
        margin: 5px 0 0;
        font-size: 14px;
        color: #b3b3b3;
    }

    .playlist-item-duration {
        color: #b3b3b3;
        font-size: 14px;
        margin-right: 15px;
    }

    .add-playlist-btn, .delete-btn {
        background: none;
        border: none;
        color: #b3b3b3;
        font-size: 18px;
        cursor: pointer;
        opacity: 0.7;
        transition: all var(--transition-speed);
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        visibility: hidden;
    }

    .playlist-item:hover .add-playlist-btn, .playlist-item:hover .delete-btn {
        visibility: visible;
    }

    .add-playlist-btn:hover {
        opacity: 1;
        color: var(--success-color);
        background-color: rgba(29, 185, 84, 0.1);
    }

    .delete-btn:hover {
        opacity: 1;
        color: var(--error-color);
        background-color: rgba(255, 82, 82, 0.1);
    }

    .description-section h3 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }

    .rating-section {
        margin-bottom: 30px;
    }

    .rating-large {
        font-size: 3rem;
        font-weight: bold;
    }

    .rating-stars {
        margin: 10px 0;
        font-size: 1.5rem;
    }

    .rating-stars i.filled {
        color: var(--star-color);
    }

    .rating-count {
        color: #b3b3b3;
        font-size: 0.9rem;
    }

    .recent-playlists {
        width: 100%;
        max-width: 1200px;
        margin-top: 20px;
    }

    .recent-playlists h3 {
        font-size: 24px;
        margin-bottom: 20px;
    }

    .playlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .playlist-card {
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        transition: transform var(--transition-speed);
    }

    .playlist-card:hover {
        transform: scale(1.05);
    }

    .playlist-thumbnail img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .playlist-type {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: rgba(0, 0, 0, 0.7);
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        color: #fff;
    }

    .playlist-info {
        padding: 10px;
    }

    .playlist-info h3 {
        margin: 0;
        font-size: 16px;
    }

    .playlist-info p {
        margin: 5px 0 0;
        color:rgb(66, 65, 65);
        font-size: 14px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal-content {
        background-color: #181818;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        overflow-y: auto;
        padding: 20px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #282828;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 20px;
    }

    .close-modal {
        background: none;
        border: none;
        color: #b3b3b3;
        font-size: 24px;
        cursor: pointer;
    }

    .close-modal:hover {
        color: #fff;
    }

    .reviews {
        width: 100%;
        max-width: 1200px;
        margin-top: 20px;
    }

    .review-form, .user-review-actions {
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .review-item {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .review-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .review-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .review-meta {
        flex-grow: 1;
    }

    .review-author {
        font-weight: bold;
    }

    .review-date {
        color: #b3b3b3;
        font-size: 14px;
    }

    .review-rating {
        margin-top: 5px;
    }

    .review-rating i.filled {
        color: var(--star-color);
    }

    .review-content p {
        margin: 0;
    }

    .rating-selector {
        display: flex;
        gap: 5px;
        margin-bottom: 10px;
    }

    .rating-selector input {
        display: none;
    }

    .rating-selector label i {
        color: #535353;
        cursor: pointer;
        font-size: 1.5rem;
    }

    .rating-selector label i.filled {
        color: var(--star-color);
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group textarea, .form-group select {
        width: 100%;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #282828;
        color: #080707;
    }

    .btn-primary {
        background-color: var(--success-color);
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #1ed760;
    }

    .btn-secondary {
        background-color:rgb(92, 144, 216);
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #b3b3b3;
    }

    .btn-danger {
        background-color: var(--error-color);
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        color: #fff;
    }

    .btn-danger:hover {
        background-color: #ff7070;
    }

    .alert {
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .alert-error {
        background-color: #ff5252;
        color: #fff;
        opacity: 1;
        transition: opacity 0.5s ease-in-out;
    }

    .alert-success {
        background-color: var(--success-color);
        color: #fff;
        opacity: 1;
        transition: opacity 0.5s ease-in-out;
    }

    .alert-hidden {
    opacity: 0; /* Faded out state */
}

    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #181818;
    }

    ::-webkit-scrollbar-thumb {
        background-color: #535353;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background-color: #b3b3b3;
    }

    audio {
        display: none;
    }

    @media (max-width: 768px) {
        .item-main {
            flex-direction: column;
        }

        .item-media {
            border-right: none;
            border-bottom: 1px solid var(--border-color);
        }

        .audio-thumbnail {
            max-width: 250px;
        }

        .playlist-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
    }

    @media (max-width: 576px) {
        .audio-thumbnail {
            max-width: 200px;
        }

        .song-info h2 {
            font-size: 18px;
        }

        .song-info p {
            font-size: 14px;
        }

        .controls {
            gap: 10px;
        }

        .control-btn {
            width: 35px;
            height: 35px;
            font-size: 20px;
        }

        .play-btn {
            width: 45px;
            height: 45px;
        }
    }
</style>

<div class="item-container">
    <div class="item-header">
        <h2><?= htmlspecialchars($album ? $album['title'] : $item['title']) ?></h2>
        <p>By <a href="profile.php?id=<?= $item['user_id'] ?>"><?= htmlspecialchars($item['username']) ?></a></p>
        <p>Uploaded on <?= date('F j, Y', strtotime($item['created_at'])) ?></p>
    </div>

    <div class="item-main">
        <div class="item-media">
            <?php if ($item['type'] === 'audio'): ?>
                <div class="audio-player">
                    <img id="cover-art" src="assets/uploads/thumbnails/<?= htmlspecialchars($item['thumbnail']) ?>" 
                         alt="<?= htmlspecialchars($item['title']) ?>" 
                         class="audio-thumbnail">
                    <div class="song-info">
                        <h2 id="song-title"><?= htmlspecialchars($item['title']) ?></h2>
                        <p id="song-artist"><?= htmlspecialchars($item['username']) ?></p>
                    </div>
                    <div class="controls">
                        <button class="control-btn" id="prev-btn"><i class="fas fa-step-backward"></i></button>
                        <button class="control-btn play-btn" id="play-btn"><i class="fas fa-play"></i></button>
                        <button class="control-btn" id="next-btn"><i class="fas fa-step-forward"></i></button>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" id="progress-bar">
                            <div class="progress" id="progress"></div>
                        </div>
                        <div class="time-stamps">
                            <span id="current-time">0:00</span>
                            <span id="duration">0:00</span>
                        </div>
                    </div>
                </div>
                <ul class="playlist" id="playlist">
                    <!-- Will be populated by JavaScript -->
                </ul>
            <?php endif; ?>
        </div>

        <div class="item-sidebar">
            <div class="description-section">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
            </div>
            <div class="rating-section">
                <h3>Rating</h3>
                <div class="rating-average">
                    <span class="rating-large"><?= number_format($item['avg_rating'], 1) ?></span>
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= round($item['avg_rating']) ? 'filled' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-count"><?= $item['review_count'] ?> reviews</span>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="recent-playlists">
    <h3>Recent Playlists</h3>
    <?php if (empty($recent_playlists)): ?>
        <p>No playlists available.</p>
    <?php else: ?>
        <div class="playlist-grid">
            <?php foreach ($recent_playlists as $playlist): ?>
                <div class="playlist-card" data-id="<?= $playlist['id'] ?>">
                    <div class="playlist-thumbnail">
                        <img src="assets/uploads/thumbnails/<?= htmlspecialchars($playlist['thumbnail']) ?>" alt="<?= htmlspecialchars($playlist['title']) ?>">
                        <span class="playlist-type">Playlist</span>
                    </div>
                    <div class="playlist-info">
                        <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                        <p>By <?= htmlspecialchars($playlist['username']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="reviews">
    <h3>Reviews</h3>
    
    <?php if ($reviewError): ?>
        <div class="alert alert-error"><?= $reviewError ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['review'])): ?>
        <?php if ($_GET['review'] == 'success'): ?>
            <div class="alert alert-success">Review submitted successfully</div>
        <?php elseif ($_GET['review'] == 'updated'): ?>
            <div class="alert alert-success">Review updated successfully</div>
        <?php elseif ($_GET['review'] == 'deleted'): ?>
            <div class="alert alert-success">Review deleted successfully</div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (is_logged_in() && !$hasReviewed): ?>
        <div class="review-form">
            <h4>Leave a Review</h4>
            <form action="" method="post" id="review-form">
                <div class="form-group">
                    <label for="rating">Rating</label>
                    <div class="rating-selector" data-selected="0">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                            <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>" data-index="<?= $i ?>">
                                <i class="fas fa-star" data-value="<?= $i ?>"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="comment">Comment</label>
                    <textarea id="comment" name="comment" rows="4"></textarea>
                </div>
                <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
            </form>
        </div>
    <?php elseif (is_logged_in() && $hasReviewed): ?>
        <div class="user-review-actions">
            <h4>Your Review</h4>
            <div class="review-item user-review">
                <div class="review-header">
                    <img src="assets/uploads/thumbnails/<?= htmlspecialchars($userReview['profile_pic']) ?>" alt="<?= htmlspecialchars($userReview['username']) ?>" class="review-avatar">
                    <div class="review-meta">
                        <span class="review-author"><?= htmlspecialchars($userReview['username']) ?></span>
                        <span class="review-date"><?= date('F j, Y', strtotime($userReview['created_at'])) ?></span>
                        <div class="review-rating" data-user-rating="<?= $userReview['rating'] ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $userReview['rating'] ? 'filled' : '' ?>"
                                   data-index="<?= $i ?>" 
                                   data-rating="<?= $userReview['rating'] ?>"
                                   data-should-fill="<?= $i <= $userReview['rating'] ? 'yes' : 'no' ?>">
                                </i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="review-content">
                    <p><?= nl2br(htmlspecialchars($userReview['comment'])) ?></p>
                </div>
                <div class="review-actions">
                    <button id="edit-review-btn" class="btn btn-secondary">Edit Review</button>
                    <form action="" method="post" style="display: inline-block;">
                        <input type="hidden" name="review_id" value="<?= $userReview['id'] ?>">
                        <button type="submit" name="delete_review" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete your review?')">Delete Review</button>
                    </form>
                </div>
            </div>
            <div id="edit-review-form" style="display: none;" class="review-form">
                <h4>Edit Your Review</h4>
                <form action="" method="post" id="update-review-form">
                    <input type="hidden" name="review_id" value="<?= $userReview['id'] ?>">
                    <div class="form-group">
                        <label for="edit-rating">Rating</label>
                        <div class="rating-selector" data-current-rating="<?= $userReview['rating'] ?>">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="edit-star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i == $userReview['rating'] ? 'checked' : '' ?> required>
                                <label for="edit-star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>" data-index="<?= $i ?>" data-selected="<?= $i == $userReview['rating'] ? 'yes' : 'no' ?>">
                                    <i class="fas fa-star" data-value="<?= $i ?>"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-comment">Comment</label>
                        <textarea id="edit-comment" name="comment" rows="4"><?= htmlspecialchars($userReview['comment']) ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_review" class="btn btn-primary">Update Review</button>
                        <button type="button" id="cancel-edit" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="login-to-review">
            <p><a href="login.php">Login</a> to leave a review</p>
        </div>
    <?php endif; ?>
    
    <div class="review-list">
        <?php if (empty($reviews)): ?>
            <div class="no-reviews">
                <p>No reviews yet. Be the first to review this item!</p>
            </div>
        <?php else: ?>
            <h4>All Reviews</h4>
            <?php foreach ($reviews as $review): ?>
                <?php if (!is_logged_in() || $review['user_id'] != $_SESSION['user_id']): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <img src="assets/uploads/thumbnails/<?= htmlspecialchars($review['profile_pic']) ?>" alt="<?= htmlspecialchars($review['username']) ?>" class="review-avatar">
                            <div class="review-meta">
                                <span class="review-author"><?= htmlspecialchars($review['username']) ?></span>
                                <span class="review-date"><?= date('F j, Y', strtotime($review['created_at'])) ?></span>
                                <div class="review-rating" data-review-rating="<?= $review['rating'] ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'filled' : '' ?>"
                                           data-index="<?= $i ?>" 
                                           data-rating="<?= $review['rating'] ?>"
                                           data-should-fill="<?= $i <= $review['rating'] ? 'yes' : 'no' ?>">
                                        </i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="review-content">
                            <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (is_logged_in()): ?>
    <div class="modal" id="add-playlist-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add to Playlist</h2>
                <button class="close-modal" id="close-add-playlist-modal">×</button>
            </div>
            <?php
            // Check for session error or URL parameter
            if (isset($_SESSION['playlist_error'])) {
                echo '<div class="alert alert-error">' . $_SESSION['playlist_error'] . '</div>';
                unset($_SESSION['playlist_error']);
            } elseif (isset($_GET['playlist']) && $_GET['playlist'] == 'error') {
                echo '<div class="alert alert-error">Failed to remove song from playlist</div>';
            }
            ?>
            <?php if ($playlistError): ?>
                <div class="alert alert-error"><?= $playlistError ?></div>
            <?php endif; ?>
            <?php if ($playlistSuccess || (isset($_GET['playlist']) && $_GET['playlist'] == 'success')): ?>
                <div class="alert alert-success"><?= $playlistSuccess ?: 'Song removed from playlist successfully' ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <div class="form-group">
                    <label for="playlist_id">Select Playlist</label>
                    <select id="playlist_id" name="playlist_id" required>
                        <?php foreach ($user_playlists as $playlist): ?>
                            <option value="<?= $playlist['id'] ?>"><?= htmlspecialchars($playlist['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="song_id" id="song_id">
                <button type="submit" name="add_to_playlist" class="btn btn-primary">Add to Playlist</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($recent_playlists as $playlist): ?>
    <div class="modal playlist-modal" id="playlist-modal-<?= $playlist['id'] ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= htmlspecialchars($playlist['title']) ?></h2>
                <button class="close-modal">×</button>
            </div>
            <?php if ($deletePlaylistError): ?>
                <div class="alert alert-error"><?= $deletePlaylistError ?></div>
            <?php endif; ?>
            <?php if ($deletePlaylistSuccess): ?>
                <div class="alert alert-success"><?= $deletePlaylistSuccess ?></div>
            <?php endif; ?>
            <div class="now-playing">
                <img id="modal-cover-art-<?= $playlist['id'] ?>" src="assets/uploads/thumbnails/default.jpg" alt="Album cover">
                <div class="song-info">
                    <h2 id="modal-song-title-<?= $playlist['id'] ?>">Select a Song</h2>
                    <p id="modal-song-artist-<?= $playlist['id'] ?>">Artist</p>
                </div>
                <div class="controls">
                    <button class="control-btn" onclick="playModalPrev(<?= $playlist['id'] ?>)"><i class="fas fa-step-backward"></i></button>
                    <button class="control-btn play-btn" id="modal-play-btn-<?= $playlist['id'] ?>"><i class="fas fa-play"></i></button>
                    <button class="control-btn" onclick="playModalNext(<?= $playlist['id'] ?>)"><i class="fas fa-step-forward"></i></button>
                </div>
                <div class="progress-container">
                    <div class="progress-bar" id="modal-progress-bar-<?= $playlist['id'] ?>">
                        <div class="progress" id="modal-progress-<?= $playlist['id'] ?>"></div>
                    </div>
                    <div class="time-stamps">
                        <span id="modal-current-time-<?= $playlist['id'] ?>">0:00</span>
                        <span id="modal-duration-<?= $playlist['id'] ?>">0:00</span>
                    </div>
                </div>
            </div>
            <ul class="playlist" id="modal-playlist-<?= $playlist['id'] ?>">
                <!-- Will be populated by JavaScript -->
            </ul>
        </div>
    </div>
<?php endforeach; ?>

<audio id="audio-player"></audio>
<?php foreach ($recent_playlists as $playlist): ?>
    <audio id="modal-audio-player-<?= $playlist['id'] ?>"></audio>
<?php endforeach; ?>

<script>
// Main player playlist data
const playlist = <?php
$playlist_data = array_map(function($song) {
    return [
        'id' => $song['id'],
        'title' => addslashes($song['title']),
        'artist' => addslashes($song['username']),
        'src' => 'assets/uploads/audio/' . addslashes($song['file_path']),
        'cover' => 'assets/uploads/thumbnails/' . addslashes($song['thumbnail']),
        'duration' => 'N/A'
    ];
}, $related_items);
echo json_encode($playlist_data);
?>;

// Modal playlist data
const modalPlaylists = <?php
$modal_playlists_data = [];
foreach ($recent_playlists as $playlist) {
    $songs = get_playlist_songs($playlist['id']);
    $modal_playlists_data[$playlist['id']] = array_map(function($song) {
        return [
            'id' => $song['id'],
            'title' => addslashes($song['title']),
            'artist' => addslashes($song['username']),
            'src' => 'assets/uploads/audio/' . addslashes($song['file_path']),
            'cover' => 'assets/uploads/thumbnails/' . addslashes($song['thumbnail']),
            'duration' => 'N/A'
        ];
    }, $songs);
}
echo json_encode($modal_playlists_data);
?>;

// Main player DOM elements
const audioPlayer = document.getElementById('audio-player');
const playBtn = document.getElementById('play-btn');
const prevBtn = document.getElementById('prev-btn');
const nextBtn = document.getElementById('next-btn');
const progressBar = document.getElementById('progress-bar');
const progress = document.getElementById('progress');
const currentTimeEl = document.getElementById('current-time');
const durationEl = document.getElementById('duration');
const playlistEl = document.getElementById('playlist');
const coverArt = document.getElementById('cover-art');
const songTitle = document.getElementById('song-title');
const songArtist = document.getElementById('song-artist');

let currentSongIndex = 0;
let isPlaying = false;

// Modal player states
const modalStates = {};

// Initialize modal states
<?php foreach ($recent_playlists as $playlist): ?>
    modalStates[<?= $playlist['id'] ?>] = {
        currentSongIndex: 0,
        isPlaying: false,
        audioPlayer: document.getElementById('modal-audio-player-<?= $playlist['id'] ?>'),
        playBtn: document.getElementById('modal-play-btn-<?= $playlist['id'] ?>'),
        progressBar: document.getElementById('modal-progress-bar-<?= $playlist['id'] ?>'),
        progress: document.getElementById('modal-progress-<?= $playlist['id'] ?>'),
        currentTimeEl: document.getElementById('modal-current-time-<?= $playlist['id'] ?>'),
        durationEl: document.getElementById('modal-duration-<?= $playlist['id'] ?>'),
        coverArt: document.getElementById('modal-cover-art-<?= $playlist['id'] ?>'),
        songTitle: document.getElementById('modal-song-title-<?= $playlist['id'] ?>'),
        songArtist: document.getElementById('modal-song-artist-<?= $playlist['id'] ?>'),
        playlistEl: document.getElementById('modal-playlist-<?= $playlist['id'] ?>')
    };
<?php endforeach; ?>

// Render main playlist
function renderPlaylist() {
    playlistEl.innerHTML = '';
    
    playlist.forEach((song, index) => {
        const li = document.createElement('li');
        li.className = 'playlist-item';
        li.dataset.index = index;
        li.innerHTML = `
            <div class="playlist-item-number">${index + 1}</div>
            <div class="playlist-item-info">
                <h3 class="playlist-item-title">${song.title}</h3>
                <p class="playlist-item-artist">${song.artist}</p>
            </div>
            <div class="playlist-item-duration">${song.duration}</div>
            <?php if (is_logged_in()): ?>
                <button class="add-playlist-btn" title="Add to playlist" data-song-id="${song.id}">+</button>
            <?php endif; ?>
        `;
        
        li.addEventListener('click', (e) => {
            if (e.target.className === 'add-playlist-btn') return;
            
            currentSongIndex = parseInt(li.dataset.index);
            loadSong(currentSongIndex);
            playSong();
        });
        
        const addBtn = li.querySelector('.add-playlist-btn');
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openAddPlaylistModal(song.id);
            });
        }
        
        playlistEl.appendChild(li);
    });
    
    updateActiveTrack();
}

// Render modal playlist
function renderModalPlaylist(playlistId) {
    const state = modalStates[playlistId];
    state.playlistEl.innerHTML = '';
    
    modalPlaylists[playlistId].forEach((song, index) => {
        const li = document.createElement('li');
        li.className = 'playlist-item';
        li.dataset.index = index;
        li.innerHTML = `
            <div class="playlist-item-number">${index + 1}</div>
            <div class="playlist-item-info">
                <h3 class="playlist-item-title">${song.title}</h3>
                <p class="playlist-item-artist">${song.artist}</p>
            </div>
            <div class="playlist-item-duration">${song.duration}</div>
            <?php if (is_logged_in()): ?>
                <button class="delete-btn" title="Remove from playlist" data-song-id="${song.id}" data-playlist-id="${playlistId}">✕</button>
            <?php endif; ?>
        `;
        
        li.addEventListener('click', (e) => {
            if (e.target.className === 'delete-btn') return;
            
            state.currentSongIndex = parseInt(li.dataset.index);
            loadModalSong(playlistId, state.currentSongIndex);
            playModalSong(playlistId);
        });
        
        const deleteBtn = li.querySelector('.delete-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteModalSong(playlistId, song.id, index);
            });
        }
        
        state.playlistEl.appendChild(li);
    });
    
    updateModalActiveTrack(playlistId);
}

function deleteModalSong(playlistId, songId, index) {
    if (!confirm('Are you sure you want to remove this song from the playlist?')) return;
    
    console.log('Deleting song', songId, 'from playlist', playlistId);
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href; // Ensure we post to current URL
    form.innerHTML = `
        <input type="hidden" name="playlist_id" value="${playlistId}">
        <input type="hidden" name="song_id" value="${songId}">
        <input type="hidden" name="remove_from_playlist" value="1">
    `;
    
    // Log form for debugging
    console.log('Form created:', form);
    
    document.body.appendChild(form);
    form.submit();
}

// Update active track (main player)
function updateActiveTrack() {
    document.querySelectorAll('#playlist .playlist-item').forEach((item, idx) => {
        item.classList.toggle('active', idx === currentSongIndex);
    });
}

// Update active track (modal player)
function updateModalActiveTrack(playlistId) {
    const state = modalStates[playlistId];
    document.querySelectorAll(`#modal-playlist-${playlistId} .playlist-item`).forEach((item, idx) => {
        item.classList.toggle('active', idx === state.currentSongIndex);
    });
}

// Initialize main player
function initPlayer() {
    renderPlaylist();
    loadSong(currentSongIndex);
    
    playBtn.addEventListener('click', togglePlay);
    prevBtn.addEventListener('click', playPrev);
    nextBtn.addEventListener('click', playNext);
    
    audioPlayer.addEventListener('timeupdate', updateProgress);
    progressBar.addEventListener('click', setProgress);
    audioPlayer.addEventListener('ended', playNext);
}

// Initialize modal players
function initModalPlayer(playlistId) {
    const state = modalStates[playlistId];
    renderModalPlaylist(playlistId);
    loadModalSong(playlistId, state.currentSongIndex);
    
    state.playBtn.addEventListener('click', () => toggleModalPlay(playlistId));
    state.audioPlayer.addEventListener('timeupdate', () => updateModalProgress(playlistId));
    state.progressBar.addEventListener('click', (e) => setModalProgress(playlistId, e));
    state.audioPlayer.addEventListener('ended', () => playModalNext(playlistId));
}

// Load song (main player)
function loadSong(index) {
    if (playlist.length === 0) {
        pauseSong();
        songTitle.textContent = "No Songs Available";
        songArtist.textContent = "Add songs to playlist";
        coverArt.src = "assets/uploads/thumbnails/default.jpg";
        audioPlayer.src = "";
        return;
    }
    
    const song = playlist[index];
    audioPlayer.src = song.src;
    coverArt.src = song.cover;
    songTitle.textContent = song.title;
    songArtist.textContent = song.artist;
    
    updateActiveTrack();
    
    const activeItem = document.querySelector('#playlist .playlist-item.active');
    if (activeItem) {
        activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Load song (modal player)
function loadModalSong(playlistId, index) {
    const state = modalStates[playlistId];
    const songs = modalPlaylists[playlistId];
    
    if (songs.length === 0) {
        pauseModalSong(playlistId);
        state.songTitle.textContent = "No Songs Available";
        state.songArtist.textContent = "Add songs to playlist";
        state.coverArt.src = "assets/uploads/thumbnails/default.jpg";
        state.audioPlayer.src = "";
        return;
    }
    
    const song = songs[index];
    state.audioPlayer.src = song.src;
    state.coverArt.src = song.cover;
    state.songTitle.textContent = song.title;
    state.songArtist.textContent = song.artist;
    
    updateModalActiveTrack(playlistId);
    
    const activeItem = document.querySelector(`#modal-playlist-${playlistId} .playlist-item.active`);
    if (activeItem) {
        activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Play song (main player)
function playSong() {
    isPlaying = true;
    playBtn.innerHTML = '<i class="fas fa-pause"></i>';
    audioPlayer.play();
}

// Play song (modal player)
function playModalSong(playlistId) {
    const state = modalStates[playlistId];
    state.isPlaying = true;
    state.playBtn.innerHTML = '<i class="fas fa-pause"></i>';
    state.audioPlayer.play();
}

// Pause song (main player)
function pauseSong() {
    isPlaying = false;
    playBtn.innerHTML = '<i class="fas fa-play"></i>';
    audioPlayer.pause();
}

// Pause song (modal player)
function pauseModalSong(playlistId) {
    const state = modalStates[playlistId];
    state.isPlaying = false;
    state.playBtn.innerHTML = '<i class="fas fa-play"></i>';
    state.audioPlayer.pause();
}

// Toggle play/pause (main player)
function togglePlay() {
    if (isPlaying) {
        pauseSong();
    } else {
        playSong();
    }
}

// Toggle play/pause (modal player)
function toggleModalPlay(playlistId) {
    const state = modalStates[playlistId];
    if (state.isPlaying) {
        pauseModalSong(playlistId);
    } else {
        playModalSong(playlistId);
    }
}

// Play previous song (main player)
function playPrev() {
    currentSongIndex--;
    if (currentSongIndex < 0) {
        currentSongIndex = playlist.length - 1;
    }
    
    loadSong(currentSongIndex);
    playSong();
}

// Play previous song (modal player)
function playModalPrev(playlistId) {
    const state = modalStates[playlistId];
    state.currentSongIndex--;
    if (state.currentSongIndex < 0) {
        state.currentSongIndex = modalPlaylists[playlistId].length - 1;
    }
    
    loadModalSong(playlistId, state.currentSongIndex);
    playModalSong(playlistId);
}

// Play next song (main player)
function playNext() {
    currentSongIndex++;
    if (currentSongIndex >= playlist.length) {
        currentSongIndex = 0;
    }
    
    loadSong(currentSongIndex);
    playSong();
}

// Play next song (modal player)
function playModalNext(playlistId) {
    const state = modalStates[playlistId];
    state.currentSongIndex++;
    if (state.currentSongIndex >= modalPlaylists[playlistId].length) {
        state.currentSongIndex = 0;
    }
    
    loadModalSong(playlistId, state.currentSongIndex);
    playModalSong(playlistId);
}

// Update progress bar (main player)
function updateProgress(e) {
    const { duration, currentTime } = e.srcElement;
    
    if (duration) {
        const progressPercent = (currentTime / duration) * 100;
        progress.style.width = `${progressPercent}%`;
        
        currentTimeEl.textContent = formatTime(currentTime);
        durationEl.textContent = formatTime(duration);
    }
}

// Update progress bar (modal player)
function updateModalProgress(playlistId) {
    const state = modalStates[playlistId];
    const { duration, currentTime } = state.audioPlayer;
    
    if (duration) {
        const progressPercent = (currentTime / duration) * 100;
        state.progress.style.width = `${progressPercent}%`;
        
        state.currentTimeEl.textContent = formatTime(currentTime);
        state.durationEl.textContent = formatTime(duration);
    }
}

// Set progress (main player)
function setProgress(e) {
    const width = this.clientWidth;
    const clickX = e.offsetX;
    const duration = audioPlayer.duration;
    
    if (duration) {
        audioPlayer.currentTime = (clickX / width) * duration;
    }
}

// Set progress (modal player)
function setModalProgress(playlistId, e) {
    const state = modalStates[playlistId];
    const width = state.progressBar.clientWidth;
    const clickX = e.offsetX;
    const duration = state.audioPlayer.duration;
    
    if (duration) {
        state.audioPlayer.currentTime = (clickX / width) * duration;
    }
}

// Format time in MM:SS
function formatTime(time) {
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
}

// Add to playlist modal
function openAddPlaylistModal(songId) {
    const modal = document.getElementById('add-playlist-modal');
    const songIdInput = document.getElementById('song_id');
    songIdInput.value = songId;
    modal.style.display = 'flex';
}

document.getElementById('close-add-playlist-modal')?.addEventListener('click', () => {
    document.getElementById('add-playlist-modal').style.display = 'none';
});

// Playlist modal handling
document.querySelectorAll('.playlist-card').forEach(card => {
    card.addEventListener('click', () => {
        const playlistId = card.dataset.id;
        const modal = document.getElementById(`playlist-modal-${playlistId}`);
        modal.style.display = 'flex';
        initModalPlayer(playlistId);
    });
});

document.querySelectorAll('.close-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        const modal = btn.closest('.modal');
        const playlistId = modal.id.replace('playlist-modal-', '');
        if (modalStates[playlistId]) {
            pauseModalSong(playlistId);
        }
        modal.style.display = 'none';
    });
});

// Review handling
document.addEventListener('DOMContentLoaded', function() {
    const ratingSelectors = document.querySelectorAll('.rating-selector');
    
    ratingSelectors.forEach(function(selector) {
        const labels = selector.querySelectorAll('label');
        
        labels.forEach(function(label) {
            label.addEventListener('click', function() {
                const value = this.getAttribute('data-index');
                selector.setAttribute('data-selected', value);
                
                const input = document.getElementById(this.getAttribute('for'));
                if (input) {
                    input.checked = true;
                }
                
                labels.forEach(function(lbl) {
                    const starIndex = parseInt(lbl.getAttribute('data-index'));
                    const starIcon = lbl.querySelector('i.fa-star');
                    
                    if (starIndex <= value) {
                        starIcon.classList.add('filled');
                        lbl.setAttribute('data-selected', 'yes');
                    } else {
                        starIcon.classList.remove('filled');
                        lbl.setAttribute('data-selected', 'no');
                    }
                });
            });
        });
    });
    
    const reviewForm = document.getElementById('review-form');
    const updateReviewForm = document.getElementById('update-review-form');
    const editReviewBtn = document.getElementById('edit-review-btn');
    const cancelEditBtn = document.getElementById('cancel-edit');
    const editReviewForm = document.getElementById('edit-review-form');
    const userReview = document.querySelector('.user-review');
    
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            const ratingInputs = document.querySelectorAll('input[name="rating"]');
            let isRatingSelected = false;
            
            ratingInputs.forEach(input => {
                if (input.checked) {
                    isRatingSelected = true;
                }
            });
            
            if (!isRatingSelected) {
                e.preventDefault();
                alert('Please select a rating');
            }
        });
    }
    
    if (editReviewBtn) {
        editReviewBtn.addEventListener('click', function() {
            userReview.style.display = 'none';
            editReviewForm.style.display = 'block';
        });
    }
    
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            editReviewForm.style.display = 'none';
            userReview.style.display = 'block';
        });
    }
    
    if (updateReviewForm) {
        updateReviewForm.addEventListener('submit', function(e) {
            const ratingInputs = document.querySelectorAll('#update-review-form input[name="rating"]');
            let isRatingSelected = false;
            
            ratingInputs.forEach(input => {
                if (input.checked) {
                    isRatingSelected = true;
                }
            });
            
            if (!isRatingSelected) {
                e.preventDefault();
                alert('Please select a rating');
            }
        });
    }
});

// Initialize main player
window.addEventListener('load', initPlayer);

// Auto-hide alerts with fade-out effect after 1 second
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-error, .alert-success');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden'); // Add fade-out class
            setTimeout(() => {
                alert.remove(); // Remove from DOM after fade-out completes
            }, 500); // Match the transition duration (0.5s = 500ms)
        }, 1000); // 1000 milliseconds = 1 second
    });
});

</script>

<?php require_once 'includes/footer.php'; ?>