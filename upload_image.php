<?php
declare(strict_types=1);

// upload_image.php - receives image via POST and saves to uploads/ then returns JSON {success, url}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
        exit;
    }

    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['image'];
    if (!is_array($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload error']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
        exit;
    }

    $ext = $allowed[$mime];
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
            echo json_encode(['success' => false, 'error' => 'Cannot create uploads directory']);
            exit;
        }
    }

    $basename = bin2hex(random_bytes(8));
    $filename = $basename . '.' . $ext;
    $target = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }

    // Build URL relative to current script
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') ;
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $url = $baseUrl . $scriptDir . '/uploads/' . rawurlencode($filename);

    echo json_encode(['success' => true, 'url' => $url]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}