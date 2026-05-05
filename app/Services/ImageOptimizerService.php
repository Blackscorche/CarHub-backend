<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageOptimizerService
{
    protected ?ImageManager $manager;

    public function __construct()
    {
        try {
            $this->manager = new ImageManager(new Driver());
        } catch (\Throwable $e) {
            $this->manager = null;
        }
    }

    /**
     * Optimize and store an uploaded image.
     * Resizes to max dimensions, compresses JPEG quality.
     */
    public function storeOptimized(
        UploadedFile $file,
        string $folder,
        string $disk = 'public',
        int $maxWidth = 1600,
        int $maxHeight = 1600,
        int $quality = 80,
    ): string {
        try {
            if (!$this->manager) throw new \RuntimeException('GD not available');
            $image = $this->manager->read($file->getRealPath());

            // Resize only if larger than max dimensions (keep aspect ratio)
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image->scaleDown(width: $maxWidth, height: $maxHeight);
            }

            $extension = 'jpg';
            $filename = uniqid() . '.' . $extension;
            $path = $folder . '/' . $filename;

            // Encode as JPEG with compression
            $encoded = $image->toJpeg($quality);

            Storage::disk($disk)->put($path, (string) $encoded);

            return '/storage/' . $path;
        } catch (\Throwable $e) {
            Log::warning('Image optimization failed, storing original', ['error' => $e->getMessage()]);
            // Fallback: store original file
            $storedPath = $file->store($folder, $disk);
            return '/storage/' . $storedPath;
        }
    }

    /**
     * Create a thumbnail version.
     */
    public function createThumbnail(
        UploadedFile $file,
        string $folder,
        int $size = 200,
        string $disk = 'public',
    ): string {
        try {
            $image = $this->manager->read($file->getRealPath());
            $image->cover($size, $size);

            $filename = 'thumb_' . uniqid() . '.jpg';
            $path = $folder . '/thumbs/' . $filename;

            Storage::disk($disk)->put($path, (string) $image->toJpeg(75));

            return '/storage/' . $path;
        } catch (\Throwable $e) {
            Log::warning('Thumbnail creation failed', ['error' => $e->getMessage()]);
            return '';
        }
    }
}
