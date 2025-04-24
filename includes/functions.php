<?php
session_start();

require_once 'config.php';
require_once 'db.php';

// User authentication functions
function register_user($username, $email, $password) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    
    if($stmt->rowCount() > 0) {
        return false;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $result = $stmt->execute([$username, $email, $hashed_password]);
    
    if($result) {
        return $db->lastInsertId();
    }
    return false;
}

function login_user($username, $password) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    
    if($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($password, $user['password'])) {
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
    }
    return false;
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
    
    $stmt = $db->prepare($query);
    
    for($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i]);
    }
    
    $limitInt = (int)$limit;
    $offsetInt = (int)$offset;
    $stmt->bindParam(count($params) + 1, $limitInt, PDO::PARAM_INT);
    $stmt->bindParam(count($params) + 2, $offsetInt, PDO::PARAM_INT);
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Playlist functions
function get_playlists($limit = 10, $offset = 0, $user_id = null) {
    $database = new Database();
    $db = $database->connect();
    
    $query = 'SELECT p.*, u.username, COUNT(pi.item_id) as song_count 
             FROM playlists p 
             JOIN users u ON p.user_id = u.id 
             LEFT JOIN playlist_items pi ON p.id = pi.playlist_id';
    
    $params = [];
    
    if($user_id) {
        $query .= ' WHERE p.user_id = ?';
        $params[] = $user_id;
    }
    
    $query .= ' GROUP BY p.id ORDER BY p.created_at DESC LIMIT ? OFFSET ?';
    
    $stmt = $db->prepare($query);
    
    for($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i]);
    }
    
    $limitInt = (int)$limit;
    $offsetInt = (int)$offset;
    $stmt->bindParam(count($params) + 1, $limitInt, PDO::PARAM_INT);
    $stmt->bindParam(count($params) + 2, $offsetInt, PDO::PARAM_INT);
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_playlist_songs($playlist_id) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT i.*, u.username 
                         FROM playlist_items pi 
                         JOIN items i ON pi.item_id = i.id 
                         JOIN users u ON i.user_id = u.id 
                         WHERE pi.playlist_id = ? 
                         ORDER BY pi.position');
    $stmt->execute([$playlist_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_song_to_playlist($playlist_id, $item_id, $position) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('INSERT INTO playlist_items (playlist_id, item_id, position) VALUES (?, ?, ?)');
    $result = $stmt->execute([$playlist_id, $item_id, $position]);
    
    return $result;
}
function remove_song_from_playlist($playlist_id, $item_id) {
    $database = new Database();
    $db = $database->connect();
    
    // Log operation
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Trying to remove song $item_id from playlist $playlist_id\n", FILE_APPEND);
    
    try {
        // Log SQL query
        $sql = "DELETE FROM playlist_items WHERE playlist_id = $playlist_id AND item_id = $item_id";
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - SQL: $sql\n", FILE_APPEND);
        
        $stmt = $db->prepare('DELETE FROM playlist_items WHERE playlist_id = ? AND item_id = ?');
        $result = $stmt->execute([$playlist_id, $item_id]);
        
        // Log result
        $affected = $stmt->rowCount();
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Rows affected: $affected\n", FILE_APPEND);
        
        if ($affected > 0) {
            // Rest of your code for reordering...
            
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Successfully removed and reordered\n", FILE_APPEND);
            return true;
        } else {
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - No rows deleted - song may not be in playlist\n", FILE_APPEND);
            return false;
        }
    } catch (PDOException $e) {
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// Album functions
function get_albums($limit = 10, $offset = 0, $user_id = null) {
    $database = new Database();
    $db = $database->connect();
    
    $query = 'SELECT a.*, u.username, COUNT(ai.item_id) as song_count 
             FROM albums a 
             JOIN users u ON a.user_id = u.id 
             LEFT JOIN album_items ai ON a.id = ai.album_id';
    
    $params = [];
    
    if($user_id) {
        $query .= ' WHERE a.user_id = ?';
        $params[] = $user_id;
    }
    
    $query .= ' GROUP BY a.id ORDER BY a.created_at DESC LIMIT ? OFFSET ?';
    
    $stmt = $db->prepare($query);
    
    for($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i]);
    }
    
    $limitInt = (int)$limit;
    $offsetInt = (int)$offset;
    $stmt->bindParam(count($params) + 1, $limitInt, PDO::PARAM_INT);
    $stmt->bindParam(count($params) + 2, $offsetInt, PDO::PARAM_INT);
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_album_songs($album_id) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT i.*, u.username 
                         FROM album_items ai 
                         JOIN items i ON ai.item_id = i.id 
                         JOIN users u ON i.user_id = u.id 
                         WHERE ai.album_id = ? 
                         ORDER BY ai.track_number');
    $stmt->execute([$album_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_song_to_album($album_id, $item_id, $track_number) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('INSERT INTO album_items (album_id, item_id, track_number) VALUES (?, ?, ?)');
    $result = $stmt->execute([$album_id, $item_id, $track_number]);
    
    return $result;
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

function update_review($review_id, $user_id, $rating, $comment) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ? AND user_id = ?');
    $stmt->execute([$review_id, $user_id]);
    
    if ($stmt->rowCount() == 0) {
        return false;
    }
    
    $stmt = $db->prepare('UPDATE reviews SET rating = ?, comment = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $result = $stmt->execute([$rating, $comment, $review_id, $user_id]);
    
    return $result;
}

function delete_review($review_id, $user_id) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ? AND user_id = ?');
    $stmt->execute([$review_id, $user_id]);
    
    if ($stmt->rowCount() == 0) {
        return false;
    }
    
    $stmt = $db->prepare('DELETE FROM reviews WHERE id = ? AND user_id = ?');
    $result = $stmt->execute([$review_id, $user_id]);
    
    return $result;
}

// File upload functions
function upload_file($file, $type) {
    $filename = time() . '_' . basename($file['name']);
    
    if ($type == 'audio') {
        $target_dir = AUDIO_PATH;
    } elseif ($type == 'image') {
        $target_dir = THUMBNAIL_PATH;
    } else {
        $target_dir = THUMBNAIL_PATH;
    }
    
    $target_file = $target_dir . $filename;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $allowed_types = ($type == 'audio') ? ALLOWED_AUDIO : ALLOWED_IMAGE;
    if (!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $filename;
    }
    return false;
}

// Update user profile picture
function update_profile_picture($user_id, $profile_pic) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
    $result = $stmt->execute([$profile_pic, $user_id]);
    
    return $result;
}

// Theme switcher function
function toggle_dark_mode($user_id, $mode) {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare('UPDATE users SET dark_mode = ? WHERE id = ?');
    $result = $stmt->execute([$mode, $user_id]);
    
    if ($result) {
        $_SESSION['dark_mode'] = $mode;
        return true;
    }
    return false;
}


?>