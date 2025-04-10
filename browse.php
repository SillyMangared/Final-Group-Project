<?php
require_once 'includes/header.php';

// Get filter parameters
$type = isset($_GET['type']) ? $_GET['type'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Get items
$items = get_items($limit, $offset, null, $type);

// Get total items count for pagination
$database = new Database();
$db = $database->connect();

$query = 'SELECT COUNT(*) as total FROM items';
if($type) {
    $query .= ' WHERE type = ?';
    $stmt = $db->prepare($query);
    $stmt->execute([$type]);
} else {
    $stmt = $db->prepare($query);
    $stmt->execute();
}

$total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $limit);
?>

<section class="browse-header">
    <h2>Browse Content</h2>
    <div class="filter-options">
        <a href="browse.php" class="<?= !$type ? 'active' : '' ?>">All</a>
        <a href="browse.php?type=audio" class="<?= $type == 'audio' ? 'active' : '' ?>">Audio</a>
        <a href="browse.php?type=video" class="<?= $type == 'video' ? 'active' : '' ?>">Video</a>
        <a href="browse.php?type=url" class="<?= $type == 'url' ? 'active' : '' ?>">Courses</a>
    </div>
</section>

<section class="browse-content">
    <?php if(empty($items)): ?>
        <div class="no-items">
            <p>No items found</p>
        </div>
    <?php else: ?>
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
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="browse.php?page=<?= $page - 1 ?><?= $type ? '&type=' . $type : '' ?>" class="page-link">Previous</a>
                <?php endif; ?>
                
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="browse.php?page=<?= $i ?><?= $type ? '&type=' . $type : '' ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="browse.php?page=<?= $page + 1 ?><?= $type ? '&type=' . $type : '' ?>" class="page-link">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>