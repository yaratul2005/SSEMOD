<?php
declare(strict_types=1);

namespace App\Services;

class AvatarService {
    private string $avatarDir;

    public function __construct(array $appConfig) {
        $this->avatarDir = $appConfig['storage_path'] . '/avatars';
        if (!is_dir($this->avatarDir)) {
            mkdir($this->avatarDir, 0777, true);
        }
    }

    /**
     * Upload and resize avatar (center-crop to 200x200), saving as WebP.
     */
    public function upload(array $fileInfo, string $userId): string|false {
        $tmpPath = $fileInfo['tmp_name'] ?? '';
        if ($tmpPath === '' || !file_exists($tmpPath)) {
            return false;
        }

        // 1. Size check: max 2MB
        $maxSize = 2 * 1024 * 1024;
        if ($fileInfo['size'] > $maxSize) {
            return false;
        }

        // 2. MIME check via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg' => 'jpeg',
            'image/jpg'  => 'jpeg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimes[$mime])) {
            return false;
        }

        $type = $allowedMimes[$mime];

        // 3. Load resource in GD
        $srcImg = match ($type) {
            'jpeg' => @imagecreatefromjpeg($tmpPath),
            'png'  => @imagecreatefrompng($tmpPath),
            'webp' => @imagecreatefromwebp($tmpPath),
            default => false
        };

        if ($srcImg === false) {
            return false;
        }

        // 4. Center-crop and scale to 200x200
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        $dstImg = imagecreatetruecolor(200, 200);
        if ($dstImg === false) {
            imagedestroy($srcImg);
            return false;
        }

        // Retain alpha channel transparency
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        if ($srcW > $srcH) {
            // Landscape
            $cropW = $srcH;
            $cropH = $srcH;
            $srcX = (int)(($srcW - $srcH) / 2);
            $srcY = 0;
        } else {
            // Portrait
            $cropW = $srcW;
            $cropH = $srcW;
            $srcX = 0;
            $srcY = (int)(($srcH - $srcW) / 2);
        }

        $resized = imagecopyresampled(
            $dstImg,
            $srcImg,
            0, 0,
            $srcX, $srcY,
            200, 200,
            $cropW, $cropH
        );

        if (!$resized) {
            imagedestroy($srcImg);
            imagedestroy($dstImg);
            return false;
        }

        // 5. Output WebP
        $destPath = $this->avatarDir . '/' . $userId . '.webp';
        
        if (!is_dir($this->avatarDir)) {
            mkdir($this->avatarDir, 0777, true);
        }

        $saved = imagewebp($dstImg, $destPath, 80);
        
        imagedestroy($srcImg);
        imagedestroy($dstImg);

        if ($saved) {
            return $destPath;
        }

        return false;
    }
}
