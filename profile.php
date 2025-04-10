<?php
require_once 'includes/header.php';

// Check if user is logged in
if(!is_logged_in() && !isset($_GET['id'])) {
    header('Location: login.php');
    exit;
}

// Determine which user profile to display
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];
$user = get_user($profile_id);

// Check if user exists
if(!$user) {
    header('Location: index.php');
    exit;
}

// Handle profile picture update
$profileError = '';
$profileSuccess = '';

if(isset($_POST['update_profile_pic']) && is_logged_in() && $profile_id == $_SESSION['user_id']) {
    if(!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] != 0) {
        $profileError = 'Please select an image to upload';
    } else {
        // Upload profile picture
        $profile_pic = upload_file($_FILES['profile_pic'], 'image');
        
        if(!$profile_pic) {
            $profileError = 'Failed to upload profile picture. Please check file type and size.';
        } else {
            // Update user profile picture in database
            $database = new Database();
            $db = $database->connect();
            
            $stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
            $result = $stmt->execute([$profile_pic, $_SESSION['user_id']]);
            
            if($result) {
                $profileSuccess = 'Profile picture updated successfully';
                // Refresh page to show new profile picture
                header('Location: profile.php?profile=success');
                exit;
            } else {
                $profileError = 'Failed to update profile picture';
            }
        }
    }
}

// Handle file upload
$uploadError = '';
$uploadSuccess = '';

if(isset($_POST['upload_item']) && is_logged_in() && $profile_id == $_SESSION['user_id']) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    
    if(empty($title)) {
        $uploadError = 'Please enter a title';
    } elseif($type == 'url') {
        // Handle URL upload
        $url = trim($_POST['url']);
        
        if(empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $uploadError = 'Please enter a valid URL';
        } elseif(!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] != 0) {
            $uploadError = 'Please select a thumbnail image';
        } else {
            // Upload thumbnail
            $thumbnail = upload_file($_FILES['thumbnail'], 'image');
            
            if(!$thumbnail) {
                $uploadError = 'Failed to upload thumbnail. Please check file type and size.';
            } else {
                // Create item
                $item_id = create_item($_SESSION['user_id'], $title, $description, $url, $thumbnail, $type);
                
                if($item_id) {
                    $uploadSuccess = 'Course URL uploaded successfully';
                    // Refresh page to show new item
                    header('Location: profile.php?upload=success');
                    exit;
                } else {
                    $uploadError = 'Failed to create item';
                }
            }
        }
    } elseif(!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
        $uploadError = 'Please select a file to upload';
    } elseif(!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] != 0) {
        $uploadError = 'Please select a thumbnail image';
    } else {
        // Upload file
        $file_path = upload_file($_FILES['file'], $type);
        
        if(!$file_path) {
            $uploadError = 'Failed to upload file. Please check file type and size.';
        } else {
            // Upload thumbnail
            $thumbnail = upload_file($_FILES['thumbnail'], 'image');
            
            if(!$thumbnail) {
                $uploadError = 'Failed to upload thumbnail. Please check file type and size.';
            } else {
                // Create item
                $item_id = create_item($_SESSION['user_id'], $title, $description, $file_path, $thumbnail, $type);
                
                if($item_id) {
                    $uploadSuccess = 'Item uploaded successfully';
                    // Refresh page to show new item
                    header('Location: profile.php?upload=success');
                    exit;
                } else {
                    $uploadError = 'Failed to create item';
                }
            }
        }
    }
}

// Get user's items
$user_items = get_items(10, 0, $profile_id);

// Get user's stats
$database = new Database();
$db = $database->connect();

// Get total uploads count
$stmt = $db->prepare('SELECT COUNT(*) as total_uploads FROM items WHERE user_id = ?');
$stmt->execute([$profile_id]);
$total_uploads = $stmt->fetch(PDO::FETCH_ASSOC)['total_uploads'];

// Get total reviews count
$stmt = $db->prepare('SELECT COUNT(*) as total_reviews FROM reviews WHERE user_id = ?');
$stmt->execute([$profile_id]);
$total_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total_reviews'];

// Get average rating of user's items
$stmt = $db->prepare('SELECT AVG(r.rating) as avg_rating FROM items i 
                     LEFT JOIN reviews r ON i.id = r.item_id 
                     WHERE i.user_id = ? AND r.rating IS NOT NULL');
$stmt->execute([$profile_id]);
$avg_rating = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'];
$avg_rating = $avg_rating ? number_format($avg_rating, 1) : 'N/A';

// Check if viewing own profile
$is_own_profile = is_logged_in() && $profile_id == $_SESSION['user_id'];
?>

<section class="profile-container">
    <div class="profile-header">
        <div class="profile-avatar">
            <img src="assets/uploads/thumbnails/<?= htmlspecialchars($user['profile_pic']) ?>" alt="<?= htmlspecialchars($user['username']) ?>">
            <?php if($is_own_profile): ?>
                <div class="profile-avatar-overlay">
                    <button id="change-avatar-btn" class="btn-change-avatar">
                        <i class="fas fa-camera"></i> Change
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($user['username']) ?></h2>
            <p><i class="fas fa-calendar-alt"></i> Member since <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
            <?php if($is_own_profile): ?>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
            <?php endif; ?>
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-value"><?= $total_uploads ?></span>
                    <span class="stat-label">Uploads</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $total_reviews ?></span>
                    <span class="stat-label">Reviews</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $avg_rating ?></span>
                    <span class="stat-label">Avg. Rating</span>
                </div>
            </div>
        </div>
    </div>

    <?php if($is_own_profile): ?>
        <!-- Profile picture change form (hidden by default) -->
        <div id="profile-pic-form" class="profile-pic-form" style="display: none;">
            <h3>Change Profile Picture</h3>
            <?php if($profileError): ?>
                <div class="alert alert-error"><?= $profileError ?></div>
            <?php endif; ?>
            <?php if(isset($_GET['profile']) && $_GET['profile'] == 'success'): ?>
                <div class="alert alert-success">Profile picture updated successfully</div>
            <?php endif; ?>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_pic">Select Image</label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/*" required>
                    <div class="preview" id="profile-pic-preview"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_profile_pic" class="btn btn-primary">Update</button>
                    <button type="button" id="cancel-profile-pic" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>

        <section class="upload-section">
            <h3>Upload New Item</h3>
            <?php if($uploadError): ?>
                <div class="alert alert-error"><?= $uploadError ?></div>
            <?php endif; ?>
            <?php if(isset($_GET['upload']) && $_GET['upload'] == 'success'): ?>
                <div class="alert alert-success">Item uploaded successfully</div>
            <?php endif; ?>
            <form action="" method="post" enctype="multipart/form-data" id="upload-form">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        <option value="audio">Audio</option>
                        <option value="video">Video</option>
                        <option value="url">URL Link Course</option>
                    </select>
                </div>
                <!-- URL Field (shown only when URL type is selected) -->
                <div class="form-group url-field" style="display: none;">
                    <label for="url">Course URL</label>
                    <input type="url" id="url" name="url" placeholder="https://example.com/course">
                    <p class="field-note">Enter the full URL including https:// or http://</p>
                </div>
                <!-- File upload field (shown only when audio or video type is selected) -->
                <div class="form-group file-field">
                    <label for="file">File</label>
                    <input type="file" id="file" name="file" required>
                    <div class="file-preview" id="file-preview"></div>
                </div>
                <div class="form-group">
                    <label for="thumbnail">Thumbnail</label>
                    <input type="file" id="thumbnail" name="thumbnail" required>
                    <div class="thumbnail-preview" id="thumbnail-preview"></div>
                </div>
                <button type="submit" name="upload_item" class="btn btn-primary">Upload</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="user-items">
        <h3><?= $is_own_profile ? 'Your Uploads' : htmlspecialchars($user['username']) . "'s Uploads" ?></h3>
        <?php if(empty($user_items)): ?>
            <div class="no-items">
                <p>No items uploaded yet</p>
                <?php if($is_own_profile): ?>
                    <p>Start sharing your music by uploading your first item!</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach($user_items as $item): ?>
                    <div class="item-card">
                        <a href="item.php?id=<?= $item['id'] ?>">
                            <div class="item-thumbnail">
                                <img src="assets/uploads/thumbnails/<?= htmlspecialchars($item['thumbnail']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                <span class="item-type"><?= ucfirst($item['type']) ?></span>
                            </div>
                            <div class="item-info">
                                <h3><?= htmlspecialchars($item['title']) ?></h3>
                                <div class="item-meta">
                                    <span><i class="fas fa-star"></i> <?= number_format($item['avg_rating'], 1) ?></span>
                                    <span><i class="fas fa-comment"></i> <?= $item['review_count'] ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if(count($user_items) == 10): ?>
                <div class="view-all">
                    <a href="browse.php?user=<?= $profile_id ?>" class="btn btn-secondary">View All Uploads</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>

<style>
/* Additional styles for the enhanced profile page */
.profile-container {
    margin-bottom: 40px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 40px;
    background-color: #f9f9f9;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.dark-mode .profile-header {
    background-color: #2a2a2a;
}

.profile-avatar {
    position: relative;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    border: 3px solid #fff;
}

.dark-mode .profile-avatar {
    border-color: #444;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.5);
    padding: 5px;
    display: flex;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.profile-avatar:hover .profile-avatar-overlay {
    opacity: 1;
}

.btn-change-avatar {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 0.9rem;
    padding: 5px;
}

.profile-info {
    flex: 1;
}

.profile-info h2 {
    margin-bottom: 10px;
    font-size: 1.8rem;
}

.profile-info p {
    color: #666;
    margin-bottom: 15px;
}

.dark-mode .profile-info p {
    color: #aaa;
}

.profile-info p i {
    margin-right: 8px;
    color: var(--primary-color);
}

.profile-stats {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
}

.dark-mode .stat-label {
    color: #aaa;
}

.profile-pic-form {
    background-color: #f9f9f9;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.dark-mode .profile-pic-form {
    background-color: #2a2a2a;
}

.preview {
    margin-top: 15px;
    text-align: center;
}

.no-items {
    text-align: center;
    padding: 40px;
    background-color: #f9f9f9;
    border-radius: 8px;
}

.dark-mode .no-items {
    background-color: #2a2a2a;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-stats {
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle file/URL fields based on type selection
    const typeSelect = document.getElementById('type');
    const fileField = document.querySelector('.file-field');
    const urlField = document.querySelector('.url-field');
    
    if(typeSelect && fileField && urlField) {
        typeSelect.addEventListener('change', function() {
            if(this.value === 'url') {
                fileField.style.display = 'none';
                urlField.style.display = 'block';
                document.getElementById('file').removeAttribute('required');
                document.getElementById('url').setAttribute('required', 'required');
            } else {
                fileField.style.display = 'block';
                urlField.style.display = 'none';
                document.getElementById('file').setAttribute('required', 'required');
                document.getElementById('url').removeAttribute('required');
            }
        });
    }
    
    // Profile picture change functionality
    const changeAvatarBtn = document.getElementById('change-avatar-btn');
    const profilePicForm = document.getElementById('profile-pic-form');
    const cancelProfilePicBtn = document.getElementById('cancel-profile-pic');
    
    if(changeAvatarBtn && profilePicForm) {
        changeAvatarBtn.addEventListener('click', function() {
            profilePicForm.style.display = 'block';
            window.scrollTo({ top: profilePicForm.offsetTop - 20, behavior: 'smooth' });
        });
    }
    
    if(cancelProfilePicBtn) {
        cancelProfilePicBtn.addEventListener('click', function() {
            profilePicForm.style.display = 'none';
        });
    }
    
    // Profile picture preview
    const profilePicInput = document.getElementById('profile_pic');
    const profilePicPreview = document.getElementById('profile-pic-preview');
    
    if(profilePicInput && profilePicPreview) {
        profilePicInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if(file) {
                const fileType = file.type.split('/')[0];
                
                if(fileType === 'image') {
                    profilePicPreview.innerHTML = `
                        <img src="${URL.createObjectURL(file)}" alt="Profile Picture Preview" style="max-width: 200px; max-height: 200px; border-radius: 50%;">
                    `;
                } else {
                    profilePicPreview.innerHTML = `<p>Please select an image file</p>`;
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>