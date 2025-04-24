<?php
require_once 'includes/header.php';

// Set defaults
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$user_id = isset($_GET['user']) ? (int)$_GET['user'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Items per page
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Get user if user_id is provided
$user = null;
if ($user_id) {
    $user = get_user($user_id);
    if (!$user) {
        header('Location: browse.php');
        exit;
    }
}

// Initialize content arrays
$items = [];
$albums = [];
$playlists = [];

// Database connection
$database = new Database();
$db = $database->connect();

// Enable error reporting for debugging
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Total counts for pagination
$total_items = 0;
$total_albums = 0;
$total_playlists = 0;

// Filter content based on type
if ($type === 'all' || $type === 'songs') {
    // Get items (songs only)
    if ($search) {
        // Build search query manually
        $search_term = "%$search%"; // Prepare the search term with wildcards
        
        $sql = "SELECT i.*, u.username, COUNT(r.id) as review_count, AVG(r.rating) as avg_rating 
                FROM items i 
                JOIN users u ON i.user_id = u.id 
                LEFT JOIN reviews r ON i.id = r.item_id 
                WHERE i.type = 'audio'";
                
        $params = [];
        
        if ($user_id) {
            $sql .= " AND i.user_id = ?";
            $params[] = $user_id;
        }
        
        $sql .= " AND (i.title LIKE ? OR i.description LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        
        $sql .= " GROUP BY i.id ORDER BY i.created_at DESC LIMIT $items_per_page OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count query for pagination
        $count_sql = "SELECT COUNT(DISTINCT i.id) as total 
                      FROM items i 
                      JOIN users u ON i.user_id = u.id 
                      WHERE i.type = 'audio'";
        $count_params = [];
        
        if ($user_id) {
            $count_sql .= " AND i.user_id = ?";
            $count_params[] = $user_id;
        }
        
        $count_sql .= " AND (i.title LIKE ? OR i.description LIKE ?)";
        $count_params[] = $search_term;
        $count_params[] = $search_term;
        
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_items = $count_stmt->fetchColumn();
    } else {
        // Without search term
        $items = get_items($items_per_page, $offset, $user_id, 'audio');
        
        // Count total items for pagination
        $count_query = "SELECT COUNT(*) as total FROM items WHERE type = 'audio'";
        $params = [];
        
        if ($user_id) {
            $count_query .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_items = $stmt->fetchColumn();
    }
}

if ($type === 'all' || $type === 'albums') {
    // Get albums
    if ($search) {
        $search_term = "%$search%";
        
        $sql = "SELECT a.*, u.username, COUNT(ai.item_id) as song_count 
                FROM albums a 
                JOIN users u ON a.user_id = u.id 
                LEFT JOIN album_items ai ON a.id = ai.album_id 
                WHERE a.title LIKE ?";
        
        $params = [$search_term];
        
        if ($user_id) {
            $sql .= " AND a.user_id = ?";
            $params[] = $user_id;
        }
        
        $sql .= " GROUP BY a.id ORDER BY a.created_at DESC LIMIT $items_per_page OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count for pagination
        $count_sql = "SELECT COUNT(DISTINCT a.id) as total 
                      FROM albums a 
                      JOIN users u ON a.user_id = u.id 
                      WHERE a.title LIKE ?";
        
        $count_params = [$search_term];
        
        if ($user_id) {
            $count_sql .= " AND a.user_id = ?";
            $count_params[] = $user_id;
        }
        
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_albums = $count_stmt->fetchColumn();
    } else {
        $albums = get_albums($items_per_page, $offset, $user_id);
        
        // Count total albums for pagination
        $count_query = "SELECT COUNT(*) as total FROM albums";
        $params = [];
        
        if ($user_id) {
            $count_query .= " WHERE user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_albums = $stmt->fetchColumn();
    }
}

if ($type === 'all' || $type === 'playlists') {
    // Get playlists
    if ($search) {
        $search_term = "%$search%";
        
        $sql = "SELECT p.*, u.username, COUNT(pi.item_id) as song_count 
                FROM playlists p 
                JOIN users u ON p.user_id = u.id 
                LEFT JOIN playlist_items pi ON p.id = pi.playlist_id 
                WHERE p.title LIKE ?";
        
        $params = [$search_term];
        
        if ($user_id) {
            $sql .= " AND p.user_id = ?";
            $params[] = $user_id;
        }
        
        $sql .= " GROUP BY p.id ORDER BY p.created_at DESC LIMIT $items_per_page OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count for pagination
        $count_sql = "SELECT COUNT(DISTINCT p.id) as total 
                      FROM playlists p 
                      JOIN users u ON p.user_id = u.id 
                      WHERE p.title LIKE ?";
        
        $count_params = [$search_term];
        
        if ($user_id) {
            $count_sql .= " AND p.user_id = ?";
            $count_params[] = $user_id;
        }
        
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_playlists = $count_stmt->fetchColumn();
    } else {
        $playlists = get_playlists($items_per_page, $offset, $user_id);
        
        // Count total playlists for pagination
        $count_query = "SELECT COUNT(*) as total FROM playlists";
        $params = [];
        
        if ($user_id) {
            $count_query .= " WHERE user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_playlists = $stmt->fetchColumn();
    }
}

// Determine total content count based on type
$total_content = $type === 'songs' ? $total_items : ($type === 'albums' ? $total_albums : ($type === 'playlists' ? $total_playlists : $total_items + $total_albums + $total_playlists));

// Calculate total pages
$total_pages = ceil($total_content / $items_per_page);
?>

<div class="container">
    <section class="browse-section">
        <div class="browse-header">
            <h2><?= $user ? htmlspecialchars($user['username']) . "'s Content" : "Browse Music" ?></h2>
            
            <!-- Search form -->
            <form action="" method="get" class="search-form">
                <?php if ($user_id): ?>
                    <input type="hidden" name="user" value="<?= $user_id ?>">
                <?php endif; ?>
                <?php if ($type !== 'all'): ?>
                    <input type="hidden" name="type" value="<?= $type ?>">
                <?php endif; ?>
                <div class="search-input-group">
                    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>
        
        <!-- Content type filter -->
        <div class="filter-options">
            <a href="?<?= $user_id ? 'user=' . $user_id . '&' : '' ?><?= $search ? 'search=' . urlencode($search) . '&' : '' ?>type=all" class="<?= $type === 'all' ? 'active' : '' ?>">All</a>
            <a href="?<?= $user_id ? 'user=' . $user_id . '&' : '' ?><?= $search ? 'search=' . urlencode($search) . '&' : '' ?>type=songs" class="<?= $type === 'songs' ? 'active' : '' ?>">Songs</a>
            <a href="?<?= $user_id ? 'user=' . $user_id . '&' : '' ?><?= $search ? 'search=' . urlencode($search) . '&' : '' ?>type=albums" class="<?= $type === 'albums' ? 'active' : '' ?>">Albums</a>
            <a href="?<?= $user_id ? 'user=' . $user_id . '&' : '' ?><?= $search ? 'search=' . urlencode($search) . '&' : '' ?>type=playlists" class="<?= $type === 'playlists' ? 'active' : '' ?>">Playlists</a>
        </div>
        
        <?php if (empty($items) && empty($albums) && empty($playlists)): ?>
            <div class="no-content">
                <p>No content found</p>
                <?php if ($search): ?>
                    <p>Try with different search terms</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (($type === 'all' || $type === 'songs') && !empty($items)): ?>
                <div class="content-section">
                    <h3>Songs</h3>
                    <div class="item-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="item-card">
                                <a href="item.php?id=<?= $item['id'] ?>">
                                    <div class="item-thumbnail">
                                        <img src="assets/uploads/thumbnails/<?= htmlspecialchars($item['thumbnail']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                        <span class="item-type">Audio</span>
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (($type === 'all' || $type === 'albums') && !empty($albums)): ?>
                <div class="content-section">
                    <h3>Albums</h3>
                    <div class="item-grid">
                        <?php foreach ($albums as $album): ?>
                            <div class="item-card">
                                <a href="album.php?id=<?= $album['id'] ?>">
                                    <div class="item-thumbnail">
                                        <img src="assets/uploads/thumbnails/<?= htmlspecialchars($album['thumbnail']) ?>" alt="<?= htmlspecialchars($album['title']) ?>">
                                        <span class="item-type">Album</span>
                                    </div>
                                    <div class="item-info">
                                        <h3><?= htmlspecialchars($album['title']) ?></h3>
                                        <p>By <?= htmlspecialchars($album['username']) ?></p>
                                        <div class="item-meta">
                                            <span><i class="fas fa-music"></i> <?= $album['song_count'] ?> songs</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (($type === 'all' || $type === 'playlists') && !empty($playlists)): ?>
                <div class="content-section">
                    <h3>Playlists</h3>
                    <div class="item-grid">
                        <?php foreach ($playlists as $playlist): ?>
                            <div class="item-card">
                                <a href="playlist.php?id=<?= $playlist['id'] ?>">
                                    <div class="item-thumbnail">
                                        <img src="assets/uploads/thumbnails/<?= htmlspecialchars($playlist['thumbnail']) ?>" alt="<?= htmlspecialchars($playlist['title']) ?>">
                                        <span class="item-type">Playlist</span>
                                    </div>
                                    <div class="item-info">
                                        <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                                        <p>By <?= htmlspecialchars($playlist['username']) ?></p>
                                        <div class="item-meta">
                                            <span><i class="fas fa-music"></i> <?= $playlist['song_count'] ?> songs</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= $user_id ? 'user=' . $user_id . '&' : '' ?><?= $type !== 'all' ? 'type=' . $type . '&' : '' ?><?= $search ? 'search=' . urlencode($search) . '&' : '' ?>page=<?= $page - 1 ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php
                        // Show limited page numbers
                        if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)) {
                            echo '<a href="?' . ($user_id ? 'user=' . $user_id . '&' : '') . ($type !== 'all' ? 'type=' . $type . '&' : '') . ($search ? 'search=' . urlencode($search) . '&' : '') . 'page=' . $i . '" class="page-link ' . ($i === $page ? 'active' : '') . '">' . $i . '</a>';
                        } elseif (($i == $page - 3 || $i == $page + 3) && $total_pages > 5) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                        ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= $user_id ? 'user=' . $user_id . '&' : '' ?><?= $type !== 'all' ? 'type=' . $type . '&' : '' ?><?= $search ? 'search=' . urlencode($search) . '&' : '' ?>page=<?= $page + 1 ?>" class="page-link">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<style>
    /* Additional styles for browse page */
    .search-form {
        margin-bottom: 20px;
    }
    
    .search-input-group {
        display: flex;
        max-width: 400px;
    }
    
    .search-input-group input {
        flex: 1;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 5px 0 0 5px;
        font-size: 1rem;
    }
    
    .search-input-group button {
        background-color: var(--primary-color);
        border: none;
        color: white;
        padding: 10px 15px;
        border-radius: 0 5px 5px 0;
        cursor: pointer;
    }
    
    .browse-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .content-section {
        margin-bottom: 40px;
    }
    
    .content-section h3 {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .no-content {
        text-align: center;
        padding: 50px 0;
        background-color: #f9f9f9;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    .dark-mode .no-content {
        background-color: #2a2a2a;
    }
    
    .page-ellipsis {
        padding: 8px 12px;
        color: var(--text-color);
    }
    
    @media (max-width: 768px) {
        .browse-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .search-input-group {
            max-width: 100%;
            width: 100%;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>