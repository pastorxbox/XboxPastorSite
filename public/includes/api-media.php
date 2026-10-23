<?php

declare(strict_types=1);

const API_ALLOWED_IMAGE_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/avif' => 'avif',
];

function api_require_multipart_content_type(): void
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (!str_starts_with($contentType, 'multipart/form-data')) {
        api_error(415, 'Content-Type must be multipart/form-data');
    }
}

function api_reject_dangerous_filename(string $filename): void
{
    $basename = strtolower(basename($filename));
    if (preg_match('/\.(php\d*|phtml|phar|php3|php4|php5|php7|php8|inc)$/i', $basename)) {
        api_error(400, 'File type not allowed');
    }
}

function api_detect_image_mime(string $path): ?string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);

    return is_string($mime) ? strtolower($mime) : null;
}

function api_reencode_image(string $sourcePath, string $mime, string $destPath): void
{
    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/gif' => @imagecreatefromgif($sourcePath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourcePath) : false,
        default => false,
    };

    if ($image === false) {
        api_error(400, 'Invalid image file');
    }

    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($image, $destPath, 85),
        'image/png' => imagepng($image, $destPath, 6),
        'image/gif' => imagegif($image, $destPath),
        'image/webp' => function_exists('imagewebp') ? imagewebp($image, $destPath, 85) : false,
        'image/avif' => function_exists('imageavif') ? imageavif($image, $destPath, 70) : false,
        default => false,
    };

    imagedestroy($image);

    if ($saved !== true) {
        api_error(500, 'Failed to process image');
    }
}

function api_store_uploaded_media(array $file, string $uploadsRoot): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        api_error(400, 'Invalid upload');
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        api_error(400, 'Upload failed');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > API_MAX_BYTES) {
        api_error(413, 'Payload too large');
    }

    $originalName = (string) ($file['name'] ?? 'upload.bin');
    api_reject_dangerous_filename($originalName);

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        api_error(400, 'Invalid upload');
    }

    $mime = api_detect_image_mime($tmpPath);
    if ($mime === null || !isset(API_ALLOWED_IMAGE_MIMES[$mime])) {
        api_error(400, 'File type not allowed');
    }

    $extension = API_ALLOWED_IMAGE_MIMES[$mime];
    $subdir = date('Y/m');
    $destDir = rtrim($uploadsRoot, '/') . '/' . $subdir;
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        api_error(500, 'Failed to create upload directory');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destPath = $destDir . '/' . $filename;

    // GIF: copiar bytes originais para preservar animação (reencode GD achata frames).
    // AVIF: copiar quando GD não tiver imagecreatefromavif/imageavif (comum em shared hosting).
    $copyOriginal = $mime === 'image/gif'
        || ($mime === 'image/avif' && (!function_exists('imagecreatefromavif') || !function_exists('imageavif')));

    if ($copyOriginal) {
        if (!move_uploaded_file($tmpPath, $destPath) && !copy($tmpPath, $destPath)) {
            api_error(500, 'Failed to store image');
        }
    } else {
        api_reencode_image($tmpPath, $mime, $destPath);
    }

    return '/uploads/' . $subdir . '/' . $filename;
}

function api_handle_media_upload(array $config): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    api_validate_content_length();
    api_require_multipart_content_type();

    if (!isset($_FILES['file'])) {
        api_error(400, 'Missing file field');
    }

    $uploadsRoot = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0755, true) && !is_dir($uploadsRoot)) {
        api_error(500, 'Upload directory unavailable');
    }

    $url = api_store_uploaded_media($_FILES['file'], $uploadsRoot);

    api_json(200, ['url' => $url]);
}
