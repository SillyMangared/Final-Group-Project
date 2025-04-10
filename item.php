<?php
require_once 'includes/header.php';

// Check if item ID is provided
if(!isset($_GET['id'])) {
    header('Location: browse.php');
    exit;
}

$item_id = (int)$_GET['id'];
$item = get_item($item_id);

// Check if item exists
if(!$item) {
    header('Location: browse.php');
    exit;
}

// Handle review submission, update and deletion
$reviewError = '';
$reviewSuccess = '';
$userReview = null;

// Handle review deletion
if(isset($_POST['delete_review']) && is_logged_in()) {
    $review_id = (int)$_POST['review_id'];
    
    // Delete the review
    if(delete_review($review_id, $_SESSION['user_id'])) {
        $reviewSuccess = 'Review deleted successfully';
        // Refresh page
        header('Location: item.php?id=' . $item_id . '&review=deleted');
        exit;
    } else {
        $reviewError = 'Failed to delete review';
    }
}

// Handle review update
if(isset($_POST['update_review']) && is_logged_in()) {
    // Validate that rating is set and is between 1-5
    if(!isset($_POST['rating']) || !is_numeric($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > 5) {
        $reviewError = 'Please select a valid rating (1-5 stars)';
    } else {
        $review_id = (int)$_POST['review_id'];
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        
        if(update_review($review_id, $_SESSION['user_id'], $rating, $comment)) {
            $reviewSuccess = 'Review updated successfully';
            // Refresh page to show updated review
            header('Location: item.php?id=' . $item_id . '&review=updated');
            exit;
        } else {
            $reviewError = 'Failed to update review';
        }
    }
}

// Handle new review submission
if(isset($_POST['submit_review']) && is_logged_in()) {
    // Validate that rating is set and is between 1-5
    if(!isset($_POST['rating']) || !is_numeric($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > 5) {
        $reviewError = 'Please select a valid rating (1-5 stars)';
    } else {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        
        if(create_review($item_id, $_SESSION['user_id'], $rating, $comment)) {
            $reviewSuccess = 'Review submitted successfully';
            // Refresh page to show new review
            header('Location: item.php?id=' . $item_id . '&review=success');
            exit;
        } else {
            $reviewError = 'Failed to submit review. You may have already reviewed this item.';
        }
    }
}

// Get reviews
$reviews = get_reviews($item_id);

// Check if user has already submitted a review and get the review
$hasReviewed = false;
if(is_logged_in()) {
    foreach($reviews as $review) {
        if($review['user_id'] == $_SESSION['user_id']) {
            $hasReviewed = true;
            $userReview = $review;
            break;
        }
    }
}
?>

<section class="item-details">

    <!-- Header Section: Title, Author, and Upload Date -->
    <div class="item-header">
        <h2><?= htmlspecialchars($item['title']) ?></h2>
        <p>By 
            <a href="profile.php?id=<?= $item['user_id'] ?>">
                <?= htmlspecialchars($item['username']) ?>
            </a>
        </p>
        <p>Uploaded on <?= date('F j, Y', strtotime($item['created_at'])) ?></p>
    </div>

    <!-- Main Content Section -->
    <div class="item-content">

        <!-- Media Section: Handles Audio, Video, or External URL -->
        <div class="item-media">
        <?php if ($item['type'] === 'audio'): ?>
    <!-- Audio Player -->
    <div class="audio-player">
        <img 
            src="assets/uploads/thumbnails/<?= htmlspecialchars($item['thumbnail']) ?>" 
            alt="<?= htmlspecialchars($item['title']) ?>" 
            class="audio-thumbnail"
        >
        <audio controls>
            <source 
                src="assets/uploads/audio/<?= htmlspecialchars($item['file_path']) ?>" 
                type="audio/mpeg"
            >
            Your browser does not support the audio element.
        </audio>
        <div class="visualizer-button">
            <a href="haha.html?audio=<?= urlencode('assets/uploads/audio/' . htmlspecialchars($item['file_path'])) ?>" 
               target="_blank" 
               class="btn btn-primary">
                <i class="fas fa-grin-squint-tears"></i> Try Me!
            </a>
        </div>
    </div>

            <?php elseif ($item['type'] === 'video'): ?>
                <!-- Video Player -->
                <div class="video-player">
                    <video 
                        controls 
                        poster="assets/uploads/thumbnails/<?= htmlspecialchars($item['thumbnail']) ?>"
                    >
                        <source 
                            src="assets/uploads/video/<?= htmlspecialchars($item['file_path']) ?>" 
                            type="video/mp4"
                        >
                        Your browser does not support the video element.
                    </video>
                </div>

            <?php elseif ($item['type'] === 'url'): ?>
                <!-- External Course (e.g., YouTube or Direct Link) -->
                <div class="url-course">
                    <?php
                        $youtube_id = '';

                        // Detect YouTube format (youtube.com or youtu.be)
                        if (strpos($item['file_path'], 'youtube.com') !== false) {
                            parse_str(parse_url($item['file_path'], PHP_URL_QUERY), $params);
                            if (isset($params['v'])) {
                                $youtube_id = $params['v'];
                            }
                        } elseif (strpos($item['file_path'], 'youtu.be') !== false) {
                            $youtube_id = substr(parse_url($item['file_path'], PHP_URL_PATH), 1);
                        }
                    ?>

                    <?php if ($youtube_id): ?>
                        <!-- Embedded YouTube Player -->
                        <div class="youtube-embed">
                            <iframe 
                                width="100%" 
                                height="400" 
                                src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_id) ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen
                            ></iframe>
                        </div>
                    <?php else: ?>
                        <!-- Thumbnail + External Course Link -->
                        <img 
                            src="assets/uploads/thumbnails/<?= htmlspecialchars($item['thumbnail']) ?>" 
                            alt="<?= htmlspecialchars($item['title']) ?>" 
                            class="course-thumbnail"
                        >
                        <div class="course-actions">
                            <a 
                                href="<?= htmlspecialchars($item['file_path']) ?>" 
                                target="_blank" 
                                class="btn btn-primary"
                            >
                                <i class="fas fa-external-link-alt"></i> Access Course
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Additional Info Section -->
        <div class="item-info">

            <!-- Description Section -->
            <div class="item-description">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
            </div>

            <!-- Ratings Section -->
            <div class="item-stats">
                <div class="rating-summary">
                    <div class="rating-average">
                        <span class="rating-number"><?= number_format($item['avg_rating'], 1) ?></span>
                        
                        <!-- Star Rating -->
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($item['avg_rating']) ? 'filled' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Number of Reviews -->
                        <span class="rating-count"><?= $item['review_count'] ?> reviews</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>



<section class="reviews">
    <h3>Reviews</h3>
    
    <?php if($reviewError): ?>
        <div class="alert alert-error"><?= $reviewError ?></div>
    <?php endif; ?>
    
    <?php if(isset($_GET['review'])): ?>
        <?php if($_GET['review'] == 'success'): ?>
            <div class="alert alert-success">Review submitted successfully</div>
        <?php elseif($_GET['review'] == 'updated'): ?>
            <div class="alert alert-success">Review updated successfully</div>
        <?php elseif($_GET['review'] == 'deleted'): ?>
            <div class="alert alert-success">Review deleted successfully</div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if(is_logged_in() && !$hasReviewed): ?>
        <div class="review-form">
            <h4>Leave a Review</h4>
            <form action="" method="post" id="review-form">
                <div class="form-group">
                    <label for="rating">Rating</label>
                    <div class="rating-selector" data-selected="0">
                        <?php for($i = 5; $i >= 1; $i--): ?>
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
    <?php elseif(is_logged_in() && $hasReviewed): ?>
        <div class="user-review-actions">
            <h4>Your Review</h4>
            <div class="review-item user-review">
                <div class="review-header">
                    <img src="assets/uploads/thumbnails/<?= htmlspecialchars($userReview['profile_pic']) ?>" alt="<?= htmlspecialchars($userReview['username']) ?>" class="review-avatar">
                    <div class="review-meta">
                        <span class="review-author"><?= htmlspecialchars($userReview['username']) ?></span>
                        <span class="review-date"><?= date('F j, Y', strtotime($userReview['created_at'])) ?></span>
                        <div class="review-rating" data-user-rating="<?= $userReview['rating'] ?>">
                            <?php for($i = 1; $i <= 5; $i++): ?>
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
            
            <!-- Edit Review Form (hidden by default) -->
            <div id="edit-review-form" style="display: none;" class="review-form">
                <h4>Edit Your Review</h4>
                <form action="" method="post" id="update-review-form">
                    <input type="hidden" name="review_id" value="<?= $userReview['id'] ?>">
                    <div class="form-group">
                        <label for="edit-rating">Rating</label>
                        <div class="rating-selector" data-current-rating="<?= $userReview['rating'] ?>">
                            <?php for($i = 5; $i >= 1; $i--): ?>
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
    <?php if(empty($reviews)): ?>
        <div class="no-reviews">
            <p>No reviews yet. Be the first to review this item!</p>
        </div>
    <?php else: ?>
        <h4>All Reviews</h4>
        <?php foreach($reviews as $review): ?>
            <?php if(!is_logged_in() || $review['user_id'] != $_SESSION['user_id']): ?>
                <div class="review-item">
                    <div class="review-header">
                        <img src="assets/uploads/thumbnails/<?= htmlspecialchars($review['profile_pic']) ?>" alt="<?= htmlspecialchars($review['username']) ?>" class="review-avatar">
                        <div class="review-meta">
                            <span class="review-author"><?= htmlspecialchars($review['username']) ?></span>
                            <span class="review-date"><?= date('F j, Y', strtotime($review['created_at'])) ?></span>
                            <div class="review-rating" data-review-rating="<?= $review['rating'] ?>">
                                <?php for($i = 1; $i <= 5; $i++): ?>
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


<script>

document.addEventListener('DOMContentLoaded', function() {
    // Handle the rating selector hover and click effects
    const ratingSelectors = document.querySelectorAll('.rating-selector');
    
    ratingSelectors.forEach(function(selector) {
        const labels = selector.querySelectorAll('label');
        const inputs = selector.querySelectorAll('input[type="radio"]');
        
        // Set up click handler for labels
        labels.forEach(function(label) {
            label.addEventListener('click', function() {
                const value = this.getAttribute('data-index');
                console.log('Selected rating:', value);
                
                // Update the data-selected attribute on the selector
                selector.setAttribute('data-selected', value);
                
                // Update the checked state for the corresponding input
                const input = document.getElementById(this.getAttribute('for'));
                if (input) {
                    input.checked = true;
                }
                
                // Add visual feedback for the selection
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
});


document.addEventListener('DOMContentLoaded', function() {
    // Handle the review form submission
    const reviewForm = document.getElementById('review-form');
    const updateReviewForm = document.getElementById('update-review-form');
    const editReviewBtn = document.getElementById('edit-review-btn');
    const cancelEditBtn = document.getElementById('cancel-edit');
    const editReviewForm = document.getElementById('edit-review-form');
    const userReview = document.querySelector('.user-review');
    
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            // Check if a rating is selected
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
    
    // Edit review functionality
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
            // Check if a rating is selected
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
</script>

<?php require_once 'includes/footer.php'; ?>