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
        $profile_pic = upload_file($_FILES['profile_pic'], 'image');
        
        if(!$profile_pic) {
            $profileError = 'Failed to upload profile picture. Please check file type and size.';
        } else {
            $database = new Database();
            $db = $database->connect();
            
            $stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
            $result = $stmt->execute([$profile_pic, $_SESSION['user_id']]);
            
            if($result) {
                $profileSuccess = 'Profile picture updated successfully';
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
    $album_id = !empty($_POST['album_id']) ? (int)$_POST['album_id'] : null;
    
    if(empty($title)) {
        $uploadError = 'Please enter a title';
    } elseif($type == 'url') {
        $url = trim($_POST['url']);
        
        if(empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $uploadError = 'Please enter a valid URL';
        } elseif(!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] != 0) {
            $uploadError = 'Please select a thumbnail image';
        } else {
            $thumbnail = upload_file($_FILES['thumbnail'], 'image');
            
            if(!$thumbnail) {
                $uploadError = 'Failed to upload thumbnail. Please check file type and size.';
            } else {
                $item_id = create_item($_SESSION['user_id'], $title, $description, $url, $thumbnail, $type);
                
                if($item_id) {
                    $uploadSuccess = 'Course URL uploaded successfully';
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
        $file_path = upload_file($_FILES['file'], $type);
        
        if(!$file_path) {
            $uploadError = 'Failed to upload file. Please check file type and size.';
        } else {
            $thumbnail = upload_file($_FILES['thumbnail'], 'image');
            
            if(!$thumbnail) {
                $uploadError = 'Failed to upload thumbnail. Please check file type and size.';
            } else {
                $item_id = create_item($_SESSION['user_id'], $title, $description, $file_path, $thumbnail, $type);
                
                if($item_id) {
                    if($album_id) {
                        $database = new Database();
                        $db = $database->connect();
                        
                        $stmt = $db->prepare('SELECT MAX(track_number) as max_track FROM album_items WHERE album_id = ?');
                        $stmt->execute([$album_id]);
                        $max_track = $stmt->fetch(PDO::FETCH_ASSOC)['max_track'];
                        $track_number = $max_track ? $max_track + 1 : 1;
                        
                        $stmt = $db->prepare('INSERT INTO album_items (album_id, item_id, track_number) VALUES (?, ?, ?)');
                        $album_result = $stmt->execute([$album_id, $item_id, $track_number]);
                        
                        if(!$album_result) {
                            $uploadError = 'Item uploaded but failed to add to album';
                        }
                    }
                    $uploadSuccess = 'Item uploaded successfully' . ($album_id ? ' and added to album' : '');
                    header('Location: profile.php?upload=success');
                    exit;
                } else {
                    $uploadError = 'Failed to create item';
                }
            }
        }
    }
}

// Handle playlist/album creation
$collectionError = '';
$collectionSuccess = '';

if(isset($_POST['create_collection']) && is_logged_in() && $profile_id == $_SESSION['user_id']) {
    $title = trim($_POST['collection_title']);
    $type = $_POST['collection_type'];
    
    if(empty($title)) {
        $collectionError = 'Please enter a title';
    } elseif(!isset($_FILES['collection_thumbnail']) || $_FILES['collection_thumbnail']['error'] != 0) {
        $collectionError = 'Please select a thumbnail image';
    } else {
        $thumbnail = upload_file($_FILES['collection_thumbnail'], 'image');
        
        if(!$thumbnail) {
            $collectionError = 'Failed to upload thumbnail. Please check file type and size.';
        } else {
            $database = new Database();
            $db = $database->connect();
            
            if($type == 'playlist') {
                $stmt = $db->prepare('INSERT INTO playlists (user_id, title, thumbnail) VALUES (?, ?, ?)');
            } else {
                $stmt = $db->prepare('INSERT INTO albums (user_id, title, thumbnail) VALUES (?, ?, ?)');
            }
            
            $result = $stmt->execute([$_SESSION['user_id'], $title, $thumbnail]);
            
            if($result) {
                $collectionSuccess = ucfirst($type) . ' created successfully';
                header('Location: profile.php?collection=success');
                exit;
            } else {
                $collectionError = 'Failed to create ' . $type;
            }
        }
    }
}

// Handle item deletion
$deleteError = '';
$deleteSuccess = '';

if(isset($_POST['delete_item']) && is_logged_in() && $profile_id == $_SESSION['user_id']) {
    $item_id = (int)$_POST['item_id'];
    $database = new Database();
    $db = $database->connect();
    
    // Verify item belongs to user
    $stmt = $db->prepare('SELECT * FROM items WHERE id = ? AND user_id = ?');
    $stmt->execute([$item_id, $_SESSION['user_id']]);
    
    if($stmt->rowCount() > 0) {
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        

        $thumbnail_path = THUMBNAIL_PATH . $item['thumbnail'];
        if(file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        // Delete from database
        $stmt = $db->prepare('DELETE FROM items WHERE id = ? AND user_id = ?');
        $result = $stmt->execute([$item_id, $_SESSION['user_id']]);
        
        if($result) {
            // Remove from albums and playlists
            $stmt = $db->prepare('DELETE FROM album_items WHERE item_id = ?');
            $stmt->execute([$item_id]);
            
            $stmt = $db->prepare('DELETE FROM playlist_items WHERE item_id = ?');
            $stmt->execute([$item_id]);
            
            $deleteSuccess = 'Item deleted successfully';
            header('Location: profile.php?delete=success');
            exit;
        } else {
            $deleteError = 'Failed to delete item';
        }
    } else {
        $deleteError = 'Item not found or you do not have permission to delete it';
    }
}

// Handle playlist deletion
if(isset($_POST['delete_playlist']) && is_logged_in() && $profile_id == $_SESSION['user_id']) {
    $playlist_id = (int)$_POST['playlist_id'];
    $database = new Database();
    $db = $database->connect();
    
    // Verify playlist belongs to user
    $stmt = $db->prepare('SELECT * FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlist_id, $_SESSION['user_id']]);
    
    if($stmt->rowCount() > 0) {
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete thumbnail
        $thumbnail_path = THUMBNAIL_PATH . $playlist['thumbnail'];
        if(file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        // Delete from database
        $stmt = $db->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?');
        $result = $stmt->execute([$playlist_id, $_SESSION['user_id']]);
        
        if($result) {
            // Remove associated playlist items
            $stmt = $db->prepare('DELETE FROM playlist_items WHERE playlist_id = ?');
            $stmt->execute([$playlist_id]);
            
            $deleteSuccess = 'Playlist deleted successfully';
            header('Location: profile.php?delete=success');
            exit;
        } else {
            $deleteError = 'Failed to delete playlist';
        }
    } else {
        $deleteError = 'Playlist not found or you do not have permission to delete it';
    }
}

// Handle album deletion
if(isset($_POST['delete_album']) && is_logged_in() && $profile_id == $_SESSION['user_id']) {
    $album_id = (int)$_POST['album_id'];
    $database = new Database();
    $db = $database->connect();
    
    // Verify album belongs to user
    $stmt = $db->prepare('SELECT * FROM albums WHERE id = ? AND user_id = ?');
    $stmt->execute([$album_id, $_SESSION['user_id']]);
    
    if($stmt->rowCount() > 0) {
        $album = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete thumbnail
        $thumbnail_path = THUMBNAIL_PATH . $album['thumbnail'];
        if(file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        // Delete from database
        $stmt = $db->prepare('DELETE FROM albums WHERE id = ? AND user_id = ?');
        $result = $stmt->execute([$album_id, $_SESSION['user_id']]);
        
        if($result) {
            // Remove associated album items
            $stmt = $db->prepare('DELETE FROM album_items WHERE album_id = ?');
            $stmt->execute([$album_id]);
            
            $deleteSuccess = 'Album deleted successfully';
            header('Location: profile.php?delete=success');
            exit;
        } else {
            $deleteError = 'Failed to delete album';
        }
    } else {
        $deleteError = 'Album not found or you do not have permission to delete it';
    }
}

// Get user's items
$user_items = get_items(10, 0, $profile_id);

// Get user's playlists
$user_playlists = get_playlists(10, 0, $profile_id);

// Get user's albums
$user_albums = get_albums(10, 0, $profile_id);

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

    <?php if($deleteError): ?>
        <div class="alert alert-error"><?= $deleteError ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['delete']) && $_GET['delete'] == 'success'): ?>
        <div class="alert alert-success">Item deleted successfully</div>
    <?php endif; ?>

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

        <!-- Upload item section -->
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
                <!-- Album selection -->
                <div class="form-group">
                    <label for="album_id">Album (Optional)</label>
                    <select id="album_id" name="album_id">
                        <option value="">None</option>
                        <?php
                        $user_albums = get_albums(100, 0, $_SESSION['user_id']);
                        foreach($user_albums as $album):
                        ?>
                            <option value="<?= $album['id'] ?>"><?= htmlspecialchars($album['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- URL Field (shown only when URL type is selected) -->
                <div class="form-group url-field" style="display: none;">
                    <label for="url">Course URL</label>
                    <input type="url" id="url" name="url" placeholder="https://example.com/course">
                    <p class="field-note">Enter the full URL including https:// orPicked up as text/plain; charset=utf-8
                    or http://</p>
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

        <!-- Create playlist/album section -->
        <section class="collection-section">
            <h3>Create New Playlist or Album</h3>
            <?php if($collectionError): ?>
                <div class="alert alert-error"><?= $collectionError ?></div>
            <?php endif; ?>
            <?php if(isset($_GET['collection']) && $_GET['collection'] == 'success'): ?>
                <div class="alert alert-success">Collection created successfully</div>
            <?php endif; ?>
            <form action="" method="post" enctype="multipart/form-data" id="collection-form">
                <div class="form-group">
                    <label for="collection_title">Title</label>
                    <input type="text" id="collection_title" name="collection_title" required>
                </div>
                <div class="form-group">
                    <label for="collection_type">Type</label>
                    <select id="collection_type" name="collection_type" required>
                        <option value="playlist">Playlist</option>
                        <option value="album">Album</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="collection_thumbnail">Thumbnail</label>
                    <input type="file" id="collection_thumbnail" name="collection_thumbnail"基本的
                    required>
                    <div class="thumbnail-preview" id="collection-thumbnail-preview"></div>
                </div>
                <button type="submit" name="create_collection" class="btn btn-primary">Create</button>
            </form>
        </section>
    <?php endif; ?>

    <!-- User's uploads -->
    <section class="user-items">
        <h3><?= $is_own_profile ? 'Your Uploads' : htmlspecialchars($user['username']) . "'s Uploads" ?></h3>
        <?php if(empty($user_items)): ?>
            <p>No items uploaded yet.</p>
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
                                <p>By <?= htmlspecialchars($item['username']) ?></p>
                                <div class="item-meta">
                                    <span><i class="fas fa-star"></i> <?= number_format($item['avg_rating'], 1) ?></span>
                                    <span><i class="fas fa-comment"></i> <?= $item['review_count'] ?></span>
                                </div>
                            </div>
                        </a>
                        <?php if($is_own_profile): ?>
                            <form action="" method="post" class="delete-form">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="delete_item" class="btn btn-danger delete-btn" onclick="return confirm('Are you sure you want to delete this item?')">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- User's playlists -->
    <section class="user-playlists">
        <h3><?= $is_own_profile ? 'Your Playlists' : htmlspecialchars($user['username']) . "'s Playlists" ?></h3>
        <?php if(empty($user_playlists)): ?>
            <p>No playlists created yet.</p>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach($user_playlists as $playlist): ?>
                    <div class="playlist-card" data-id="<?= $playlist['id'] ?>">
                        <div class="playlist-thumbnail">
                            <img src="assets/uploads/thumbnails/<?= htmlspecialchars($playlist['thumbnail']) ?>" alt="<?= htmlspecialchars($playlist['title']) ?>">
                            <span class="playlist-type">Playlist</span>
                        </div>
                        <div class="playlist-info">
                            <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                            <p>By <?= htmlspecialchars($playlist['username']) ?></p>
                            <div class="playlist-meta">
                                <span><i class="fas fa-music"></i> <?= $playlist['song_count'] ?> songs</span>
                            </div>
                        </div>
                        <?php if($is_own_profile): ?>
                            <form action="" method="post" class="delete-form">
                                <input type="hidden" name="playlist_id" value="<?= $playlist['id'] ?>">
                                <button type="submit" name="delete_playlist" class="btn btn-danger delete-btn" onclick="return confirm('Are you sure you want to delete this playlist?')">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <!-- Playlist Modal -->
                    <div class="modal" id="playlist-modal-<?= $playlist['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2><?= htmlspecialchars($playlist['title']) ?></h2>
                                <button class="close-modal">×</button>
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
                                            <?php if($is_own_profile): ?>
                                                <form action="" method="post" class="delete-form">
                                                    <input type="hidden" name="playlist_id" value="<?= $playlist['id'] ?>">
                                                    <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
                                                    <button type="submit" name="remove_from_playlist" class="btn btn-danger delete-btn" onclick="return confirm('Are you sure you want to remove this song from the playlist?')">Remove</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- User's albums -->
    <section class="user-albums">
        <h3><?= $is_own_profile ? 'Your Albums' : htmlspecialchars($user['username']) . "'s Albums" ?></h3>
        <?php if(empty($user_albums)): ?>
            <p>No albums created yet.</p>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach($user_albums as $album): ?>
                    <div class="album-card" data-id="<?= $album['id'] ?>">
                        <div class="album-thumbnail">
                            <img src="assets/uploads/thumbnails/<?= htmlspecialchars($album['thumbnail']) ?>" alt="<?= htmlspecialchars($album['title']) ?>">
                            <span class="album-type">Album</span>
                        </div>
                        <div class="album-info">
                            <h3><?= htmlspecialchars($album['title']) ?></h3>
                            <p>By <?= htmlspecialchars($album['username']) ?></p>
                            <div class="album-meta">
                                <span><i class="fas fa-music"></i> <?= $album['song_count'] ?> songs</span>
                            </div>
                        </div>
                        <?php if($is_own_profile): ?>
                            <form action="" method="post" class="delete-form">
                                <input type="hidden" name="album_id" value="<?= $album['id'] ?>">
                                <button type="submit" name="delete_album" class="btn btn-danger delete-btn" onclick="return confirm('Are you sure you want to delete this album?')">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <!-- Album Modal -->
                    <div class="modal" id="album-modal-<?= $album['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2><?= htmlspecialchars($album['title']) ?></h2>
                                <button class="close-modal">×</button>
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
                                            <?php if($is_own_profile): ?>
                                                <form action="" method="post" class="delete-form">
                                                    <input type="hidden" name="album_id" value="<?= $album['id'] ?>">
                                                    <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
                                                    <button type="submit" name="remove_from_album" class="btn btn-danger delete-btn" onclick="return confirm('Are you sure you want to remove this song from the album?')">Remove</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<script>
    // Toggle profile picture form
    document.getElementById('change-avatar-btn')?.addEventListener('click', function() {
        document.getElementById('profile-pic-form').style.display = 'block';
    });

    document.getElementById('cancel-profile-pic')?.addEventListener('click', function() {
        document.getElementById('profile-pic-form').style.display = 'none';
    });

    // Handle type selection for upload form
    document.getElementById('type')?.addEventListener('change', function() {
        const type = this.value;
        const urlField = document.querySelector('.url-field');
        const fileField = document.querySelector('.file-field');
        const albumField = document.querySelector('.album-field');
        
        if(type === 'url') {
            urlField.style.display = 'block';
            fileField.style.display = 'none';
            albumField.style.display = 'none';
            document.getElementById('file').removeAttribute('required');
            document.getElementById('url').setAttribute('required', 'required');
        } else {
            urlField.style.display = 'none';
            fileField.style.display = 'block';
            albumField.style.display = 'block';
            document.getElementById('url').removeAttribute('required');
            document.getElementById('file').setAttribute('required', 'required');
        }
    });

    // Profile picture preview
    document.getElementById('profile_pic')?.addEventListener('change', function() {
        const file = this.files[0];
        const preview = document.getElementById('profile-pic-preview');
        
        if(file && file.type.startsWith('image/')) {
            preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="Profile Picture Preview" style="max-width: 200px; max-height: 200px;">`;
        } else {
            preview.innerHTML = `<p>Please select an image file</p>`;
        }
    });

    // Collection thumbnail preview
    document.getElementById('collection_thumbnail')?.addEventListener('change', function() {
        const file = this.files[0];
        const preview = document.getElementById('collection-thumbnail-preview');
        
        if(file && file.type.startsWith('image/')) {
            preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="Thumbnail Preview" style="max-width: 200px; max-height: 200px;">`;
        } else {
            preview.innerHTML = `<p>Please select an image file</p>`;
        }
    });

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.add('alert-hidden');
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 1000);
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>