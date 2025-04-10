<?php
session_start();

require_once 'config.php';
require_once 'db.php';

// User authentication functions
function register_user($username, $email, $password) {
    $database = new Database();
    $db = $database->connect();
    
    // Check if username or email already exists
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    
    if($stmt->rowCount() > 0) {
        return false;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $result = $stmt->execute([$username, $email, $hashed_password]);
    
    if($result) {
        return $db->lastInsertId();
    } else {
        return false;
    }
}

function login_user($username, $password) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    
    if($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['dark_mode'] = $user['dark_mode'];
            return true;
        }
    }
    
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function logout_user() {
    session_unset();
    session_destroy();
}

function get_user($user_id) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT id, username, email, profile_pic, created_at, dark_mode FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Item functions
function create_item($user_id, $title, $description, $file_path, $thumbnail, $type) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('INSERT INTO items (user_id, title, description, file_path, thumbnail, type) VALUES (?, ?, ?, ?, ?, ?)');
    $result = $stmt->execute([$user_id, $title, $description, $file_path, $thumbnail, $type]);
    
    if($result) {
        return $db->lastInsertId();
    } else {
        return false;
    }
}

function get_item($item_id) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT i.*, u.username, COUNT(r.id) as review_count, AVG(r.rating) as avg_rating 
                         FROM items i 
                         JOIN users u ON i.user_id = u.id 
                         LEFT JOIN reviews r ON i.id = r.item_id 
                         WHERE i.id = ? 
                         GROUP BY i.id');
    $stmt->execute([$item_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_items($limit = 10, $offset = 0, $user_id = null, $type = null) {
    $database = new Database();
    $db = $database->connect();
    
    $query = 'SELECT i.*, u.username, COUNT(r.id) as review_count, AVG(r.rating) as avg_rating 
             FROM items i 
             JOIN users u ON i.user_id = u.id 
             LEFT JOIN reviews r ON i.id = r.item_id';
    
    $params = [];
    
    if($user_id) {
        $query .= ' WHERE i.user_id = ?';
        $params[] = $user_id;
    }
    
    if($type) {
        $query .= $user_id ? ' AND i.type = ?' : ' WHERE i.type = ?';
        $params[] = $type;
    }
    
    $query .= ' GROUP BY i.id ORDER BY i.created_at DESC LIMIT ? OFFSET ?';
    
    // Use integer parameters for LIMIT and OFFSET
    $stmt = $db->prepare($query);
    
    // Bind all parameters except LIMIT and OFFSET
    for($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i]);
    }
    
    // Bind LIMIT and OFFSET as integers
    $limitInt = (int)$limit;
    $offsetInt = (int)$offset;
    $stmt->bindParam(count($params) + 1, $limitInt, PDO::PARAM_INT);
    $stmt->bindParam(count($params) + 2, $offsetInt, PDO::PARAM_INT);
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Review functions
function create_review($item_id, $user_id, $rating, $comment) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('INSERT INTO reviews (item_id, user_id, rating, comment) VALUES (?, ?, ?, ?)');
    $result = $stmt->execute([$item_id, $user_id, $rating, $comment]);
    
    return $result;
}

function get_reviews($item_id) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT r.*, u.username, u.profile_pic 
                         FROM reviews r 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.item_id = ? 
                         ORDER BY r.created_at DESC');
    $stmt->execute([$item_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// File upload functions
function upload_file($file, $type) {
    $filename = time() . '_' . basename($file['name']);
    
    // Determine target directory based on file type
    if ($type == 'audio') {
        $target_dir = AUDIO_PATH;
    } elseif ($type == 'image') {
        // Use thumbnail path for all images (including profile pics)
        $target_dir = THUMBNAIL_PATH;
    } else {
        $target_dir = THUMBNAIL_PATH; // Default to thumbnails
    }
    
    $target_file = $target_dir . $filename;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_types = ($type == 'audio') ? ALLOWED_AUDIO : ALLOWED_IMAGE;
    if(!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    // Validate file size
    if($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    // Create directory if it doesn't exist
    if(!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Upload file
    if(move_uploaded_file($file['tmp_name'], $target_file)) {
        return $filename;
    } else {
        return false;
    }
}

// Update user profile picture
function update_profile_picture($user_id, $profile_pic) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
    $result = $stmt->execute([$profile_pic, $user_id]);
    
    return $result;
}

// Update review function
function update_review($review_id, $user_id, $rating, $comment) {
    $database = new Database();
    $db = $database->connect();
    
    // First, check if the review belongs to the user
    $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ? AND user_id = ?');
    $stmt->execute([$review_id, $user_id]);
    
    if($stmt->rowCount() == 0) {
        return false; // Review doesn't exist or doesn't belong to the user
    }
    
    // Update the review
    $stmt = $db->prepare('UPDATE reviews SET rating = ?, comment = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $result = $stmt->execute([$rating, $comment, $review_id, $user_id]);
    
    return $result;
}

// Delete review function
function delete_review($review_id, $user_id) {
    $database = new Database();
    $db = $database->connect();
    
    // First, check if the review belongs to the user
    $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ? AND user_id = ?');
    $stmt->execute([$review_id, $user_id]);
    
    if($stmt->rowCount() == 0) {
        return false; // Review doesn't exist or doesn't belong to the user
    }
    
    // Delete the review
    $stmt = $db->prepare('DELETE FROM reviews WHERE id = ? AND user_id = ?');
    $result = $stmt->execute([$review_id, $user_id]);
    
    return $result;
}


// Theme switcher function
function toggle_dark_mode($user_id, $mode) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('UPDATE users SET dark_mode = ? WHERE id = ?');
    $result = $stmt->execute([$mode, $user_id]);
    
    if($result) {
        $_SESSION['dark_mode'] = $mode;
        return true;
    } else {
        return false;
    }
}
?>


