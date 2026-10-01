<?php
/**
 * KhuntaLocal — Secure media uploads.
 *
 * Phase 1 handles images (cover photo + avatars). Video + multi-photo galleries
 * arrive in Phase 3 and will reuse these primitives.
 *
 * Rules enforced:
 *   - verify the PHP upload succeeded (no partial / tampered uploads)
 *   - size ceiling (config)
 *   - MIME detected from file CONTENT via finfo (never trust the browser)
 *   - extension allowlist cross-checked against the detected MIME
 *   - a random, safe filename is generated — the original name is never used
 *     on disk (only stored, sanitised, for display)
 *   - executable/script files can never land in the public uploads tree
 */

declare(strict_types=1);

if (!function_exists('upload_result')) {
    /** @return array{ok:bool,error:?string,data:array} */
    function upload_result(bool $ok, ?string $error = null, array $data = []): array
    {
        return ['ok' => $ok, 'error' => $error, 'data' => $data];
    }
}

if (!function_exists('upload_error_message')) {
    function upload_error_message(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The file is too large.';
            case UPLOAD_ERR_PARTIAL:
                return 'The file was only partially uploaded. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return 'The server could not save the file. Please try again later.';
            default:
                return 'Upload failed. Please try again.';
        }
    }
}

if (!function_exists('upload_image')) {
    /**
     * Validate and store an uploaded image.
     *
     * @param array $file   a single entry from $_FILES
     * @param string $subdir  'news' | 'avatars'
     * @param array{max_size?:int,thumb_width?:int} $opts
     * @return array{ok:bool,error:?string,data:array}
     *         data: path, thumb_path, mime, size, width, height, original_name
     */
    function upload_image(array $file, string $subdir = 'news', array $opts = []): array
    {
        // 1) Basic upload integrity.
        if (!isset($file['tmp_name'], $file['error'])) {
            return upload_result(false, 'No file was uploaded.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return upload_result(false, upload_error_message((int) $file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return upload_result(false, 'Invalid upload.');
        }

        // 2) Size.
        $maxSize = (int) ($opts['max_size'] ?? setting_int('max_image_size', (int) config('uploads.max_image_size', 5 * 1024 * 1024)));
        $size    = (int) ($file['size'] ?? filesize($file['tmp_name']));
        if ($size <= 0) {
            return upload_result(false, 'The file appears to be empty.');
        }
        if ($size > $maxSize) {
            return upload_result(false, 'The image must be smaller than ' . round($maxSize / 1048576, 1) . ' MB.');
        }

        // 3) Detect MIME from content.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);

        $allowedMimes = (array) config('uploads.image_mimes', ['image/jpeg', 'image/png', 'image/webp']);
        $allowedExts  = (array) config('uploads.image_exts', ['jpg', 'jpeg', 'png', 'webp']);

        if (!in_array($mime, $allowedMimes, true)) {
            return upload_result(false, 'Only JPG, PNG and WEBP images are allowed.');
        }

        // 4) Confirm it is a real image and get dimensions.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return upload_result(false, 'The file is not a valid image.');
        }
        [$width, $height] = $info;

        // 5) Map MIME -> safe extension (do not trust the client extension).
        $extForMime = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $ext = $extForMime[$mime] ?? null;
        if ($ext === null || !in_array($ext, $allowedExts, true)) {
            return upload_result(false, 'Unsupported image type.');
        }

        // 6) Build destination with a random filename.
        $subdir   = preg_replace('/[^a-z0-9_]/', '', strtolower($subdir)) ?: 'news';
        $baseDir  = rtrim((string) config('uploads.path', dirname(__DIR__) . '/uploads'), '/');
        $destDir  = $baseDir . '/' . $subdir . '/' . date('Y') . '/' . date('m');
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return upload_result(false, 'Could not prepare the upload directory.');
        }

        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $destDir . '/' . $safeName;

        if (!@move_uploaded_file($file['tmp_name'], $destPath)) {
            return upload_result(false, 'Could not save the uploaded file.');
        }
        @chmod($destPath, 0644);

        // Path relative to the uploads base (what we store in the DB).
        $relative = $subdir . '/' . date('Y') . '/' . date('m') . '/' . $safeName;

        // 7) Thumbnail (best effort).
        $thumbRelative = null;
        $thumbWidth    = (int) ($opts['thumb_width'] ?? 480);
        if ($thumbWidth > 0 && function_exists('imagecreatetruecolor')) {
            $thumbName  = 'thumb_' . $safeName;
            $thumbPath  = $destDir . '/' . $thumbName;
            if (upload_make_thumbnail($destPath, $thumbPath, $mime, $thumbWidth)) {
                $thumbRelative = $subdir . '/' . date('Y') . '/' . date('m') . '/' . $thumbName;
            }
        }

        return upload_result(true, null, [
            'path'          => $relative,
            'thumb_path'    => $thumbRelative,
            'mime'          => $mime,
            'size'          => $size,
            'width'         => (int) $width,
            'height'        => (int) $height,
            'original_name' => upload_safe_display_name($file['name'] ?? ''),
        ]);
    }
}

if (!function_exists('upload_video')) {
    /**
     * Validate and store an uploaded video (Phase 3).
     *
     * @param array $file  a single entry from $_FILES
     * @return array{ok:bool,error:?string,data:array}
     */
    function upload_video(array $file, string $subdir = 'news', array $opts = []): array
    {
        if (!isset($file['tmp_name'], $file['error'])) {
            return upload_result(false, 'No file was uploaded.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return upload_result(false, upload_error_message((int) $file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return upload_result(false, 'Invalid upload.');
        }

        $maxSize = (int) ($opts['max_size'] ?? setting_int('max_video_size', (int) config('uploads.max_video_size', 50 * 1024 * 1024)));
        $size    = (int) ($file['size'] ?? filesize($file['tmp_name']));
        if ($size <= 0) {
            return upload_result(false, 'The video file appears to be empty.');
        }
        if ($size > $maxSize) {
            return upload_result(false, 'The video must be smaller than ' . round($maxSize / 1048576, 1) . ' MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        $allowedMimes = (array) config('uploads.video_mimes', ['video/mp4', 'video/webm']);
        $allowedExts  = (array) config('uploads.video_exts', ['mp4', 'webm']);
        if (!in_array($mime, $allowedMimes, true)) {
            return upload_result(false, 'Only MP4 and WEBM videos are allowed.');
        }
        $extForMime = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        $ext = $extForMime[$mime] ?? null;
        if ($ext === null || !in_array($ext, $allowedExts, true)) {
            return upload_result(false, 'Unsupported video type.');
        }

        $subdir  = preg_replace('/[^a-z0-9_]/', '', strtolower($subdir)) ?: 'news';
        $baseDir = rtrim((string) config('uploads.path', dirname(__DIR__) . '/uploads'), '/');
        $destDir = $baseDir . '/' . $subdir . '/video/' . date('Y') . '/' . date('m');
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return upload_result(false, 'Could not prepare the upload directory.');
        }
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $destDir . '/' . $safeName;
        if (!@move_uploaded_file($file['tmp_name'], $destPath)) {
            return upload_result(false, 'Could not save the uploaded video.');
        }
        @chmod($destPath, 0644);
        $relative = $subdir . '/video/' . date('Y') . '/' . date('m') . '/' . $safeName;

        return upload_result(true, null, [
            'path'          => $relative,
            'thumb_path'    => null,
            'mime'          => $mime,
            'size'          => $size,
            'original_name' => upload_safe_display_name($file['name'] ?? ''),
        ]);
    }
}

if (!function_exists('normalize_files_array')) {
    /**
     * Turn a multi-file $_FILES entry (name="photos[]") into a list of single
     * file arrays, skipping empty slots.
     *
     * @return array<int,array<string,mixed>>
     */
    function normalize_files_array(array $entry): array
    {
        if (!isset($entry['name'])) {
            return [];
        }
        // Single file (not an array input).
        if (!is_array($entry['name'])) {
            return ($entry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$entry];
        }
        $out = [];
        foreach ($entry['name'] as $i => $name) {
            if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => $entry['name'][$i],
                'type'     => $entry['type'][$i] ?? '',
                'tmp_name' => $entry['tmp_name'][$i],
                'error'    => $entry['error'][$i],
                'size'     => $entry['size'][$i] ?? 0,
            ];
        }
        return $out;
    }
}

if (!function_exists('store_news_media')) {
    /**
     * Validate + store a batch of photos and/or one video for a news item,
     * inserting news_media rows. Sets the news cover to the first image when the
     * item has no cover yet. Returns per-file errors (does not throw) and the
     * number stored.
     *
     * Call INSIDE a transaction when atomicity with the news row matters.
     *
     * @param array{photos?:array,video?:array} $files  raw $_FILES entries
     * @return array{stored:int,errors:array<int,string>,first_image_id:?int}
     */
    function store_news_media(int $newsId, array $files, int $maxPhotos = 8): array
    {
        $errors = [];
        $stored = 0;
        $firstImageId = null;

        // Existing media count (so edit can't exceed the cap).
        $existing = (int) fetch_column("SELECT COUNT(*) FROM news_media WHERE news_id = ? AND type = 'image'", [$newsId], 0);
        $sort     = (int) fetch_column('SELECT COALESCE(MAX(sort_order), -1) FROM news_media WHERE news_id = ?', [$newsId], -1) + 1;

        // Photos.
        if (!empty($files['photos'])) {
            $photos = normalize_files_array($files['photos']);
            foreach ($photos as $p) {
                if ($existing + $stored >= $maxPhotos) {
                    $errors[] = 'Only ' . $maxPhotos . ' photos are allowed; extra photos were skipped.';
                    break;
                }
                $res = upload_image($p, 'news', ['thumb_width' => 800]);
                if (!$res['ok']) {
                    $errors[] = ($p['name'] ?? 'Photo') . ': ' . ($res['error'] ?? 'upload failed');
                    continue;
                }
                $d = $res['data'];
                $mediaId = db_insert('news_media', [
                    'news_id' => $newsId, 'type' => 'image',
                    'path' => $d['path'], 'thumb_path' => $d['thumb_path'],
                    'mime' => $d['mime'], 'size_bytes' => $d['size'],
                    'width' => $d['width'], 'height' => $d['height'],
                    'original_name' => $d['original_name'], 'sort_order' => $sort++,
                ]);
                if ($firstImageId === null) {
                    $firstImageId = $mediaId;
                }
                $stored++;
            }
        }

        // Video (single).
        if (!empty($files['video']) && !empty($files['video']['name'])) {
            $res = upload_video($files['video'], 'news');
            if (!$res['ok']) {
                $errors[] = 'Video: ' . ($res['error'] ?? 'upload failed');
            } else {
                $d = $res['data'];
                db_insert('news_media', [
                    'news_id' => $newsId, 'type' => 'video',
                    'path' => $d['path'], 'thumb_path' => null,
                    'mime' => $d['mime'], 'size_bytes' => $d['size'],
                    'original_name' => $d['original_name'], 'sort_order' => $sort++,
                ]);
                $stored++;
            }
        }

        // Set cover if the news item doesn't have one yet.
        if ($firstImageId !== null) {
            $hasCover = (int) fetch_column('SELECT cover_media_id IS NOT NULL FROM news WHERE id = ?', [$newsId], 0);
            if (!$hasCover) {
                db_update('news', ['cover_media_id' => $firstImageId], ['id' => $newsId]);
            }
        }

        return ['stored' => $stored, 'errors' => $errors, 'first_image_id' => $firstImageId];
    }
}

if (!function_exists('upload_safe_display_name')) {
    /** Sanitise the original filename for safe *display* only (not used on disk). */
    function upload_safe_display_name(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\- ]+/u', '', $name) ?? '';
        return mb_substr(trim($name), 0, 255);
    }
}

if (!function_exists('upload_make_thumbnail')) {
    /**
     * Create a width-constrained thumbnail using GD. Returns success.
     */
    function upload_make_thumbnail(string $srcPath, string $destPath, string $mime, int $maxWidth): bool
    {
        try {
            switch ($mime) {
                case 'image/jpeg': $src = @imagecreatefromjpeg($srcPath); break;
                case 'image/png':  $src = @imagecreatefrompng($srcPath);  break;
                case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false; break;
                default:           $src = false;
            }
            if (!$src) {
                return false;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            if ($w <= $maxWidth) {
                // No need to shrink; just copy the original as the thumb.
                imagedestroy($src);
                return @copy($srcPath, $destPath);
            }

            $newW = $maxWidth;
            $newH = (int) round($h * ($maxWidth / $w));
            $dst  = imagecreatetruecolor($newW, $newH);

            if (in_array($mime, ['image/png', 'image/webp'], true)) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            switch ($mime) {
                case 'image/jpeg': $ok = imagejpeg($dst, $destPath, 82); break;
                case 'image/png':  $ok = imagepng($dst, $destPath, 6);   break;
                case 'image/webp': $ok = function_exists('imagewebp') ? imagewebp($dst, $destPath, 82) : false; break;
                default:           $ok = false;
            }
            imagedestroy($src);
            imagedestroy($dst);
            if ($ok) {
                @chmod($destPath, 0644);
            }
            return (bool) $ok;
        } catch (Throwable $ex) {
            error_log('thumbnail failed: ' . $ex->getMessage());
            return false;
        }
    }
}
