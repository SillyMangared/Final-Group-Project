<?php
// Application configuration
define('SITE_NAME', 'MusicShare');
define('BASE_URL', 'http://localhost:8080/musicshare');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/musicshare/assets/uploads/');
define('AUDIO_PATH', UPLOAD_PATH . 'audio/');
define('THUMBNAIL_PATH', UPLOAD_PATH . 'thumbnails/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_AUDIO', ['mp3', 'wav', 'ogg']);
define('ALLOWED_IMAGE', ['jpg', 'jpeg', 'png', 'gif']);
?>