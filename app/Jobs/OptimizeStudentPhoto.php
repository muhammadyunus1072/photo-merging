<?php

namespace App\Jobs;

use App\Models\ScannedPhoto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Illuminate\Support\Facades\Log;

class OptimizeStudentPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(
        // public ScannedPhoto $photo
        public $folder,
        public $filename
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('private');

        $originalRelativePath = "{$this->folder}/{$this->filename}";
        $optimizedDirectory = "optimized/{$this->folder}";
        $optimizedRelativePath = "{$optimizedDirectory}/{$this->filename}";

        // if (!$disk->exists($originalRelativePath)) {
        //     $->update(['status' => 'failed_missing_file']);
        //     return;
        // }

        try {
            if (!$disk->exists($optimizedDirectory)) {
                $disk->makeDirectory($optimizedDirectory);
            }

            $absoluteSourcePath = $disk->path($originalRelativePath);
            $absoluteDestPath = $disk->path($optimizedRelativePath);

            // 1. Get original dimensions and image type safely
            $imageInfo = getimagesize($absoluteSourcePath);
            if (!$imageInfo) {
                throw new \Exception("File is not a valid image format.");
            }

            [$origWidth, $origHeight, $imageType] = $imageInfo;

            // 2. Load the image into memory based on its MIME type
            $sourceImage = match ($imageType) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($absoluteSourcePath),
                IMAGETYPE_PNG => imagecreatefrompng($absoluteSourcePath),
                IMAGETYPE_WEBP => imagecreatefromwebp($absoluteSourcePath),
                default => throw new \Exception("Unsupported image type: {$imageType}")
            };

            // 3. Define the target aspect ratio (3:4)
            $targetRatio = 3 / 4;
            $origRatio = $origWidth / $origHeight;

            // 4. Calculate the maximum center-crop coordinates and dimensions
            if ($origRatio > $targetRatio) {
                // Image is too wide -> limit by original height
                $cropHeight = $origHeight;
                $cropWidth = (int) round($origHeight * $targetRatio);
                $cropX = (int) round(($origWidth - $cropWidth) / 2);
                $cropY = 0;
            } else {
                // Image is too tall -> limit by original width
                $cropWidth = $origWidth;
                $cropHeight = (int) round($origWidth / $targetRatio);
                $cropX = 0;
                $cropY = (int) round(($origHeight - $cropHeight) / 2);
            }

            // 5. Create a blank canvas using the exact crop dimensions (No downscaling)
            $destinationImage = imagecreatetruecolor($cropWidth, $cropHeight);

            // Preserve transparency for PNGs and WebP
            if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
                imagealphablending($destinationImage, false);
                imagesavealpha($destinationImage, true);
                $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
                imagefilledrectangle($destinationImage, 0, 0, $cropWidth, $cropHeight, $transparent);
            }

            // 6. Crop onto the new canvas 
            // By making Destination Width/Height match Source Width/Height, no resizing occurs.
            imagecopyresampled(
                $destinationImage,
                $sourceImage,
                0,
                0,                             // Destination X, Y
                $cropX,
                $cropY,                   // Source X, Y (the crop offset)
                $cropWidth,
                $cropHeight,          // Destination Width, Height
                $cropWidth,
                $cropHeight           // Source Width, Height
            );

            // 5. Create a blank canvas for the new optimized image
            // $destinationImage = imagecreatetruecolor($targetWidth, $targetHeight);

            // Preserve transparency for PNGs and WebP
            // if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
            //     imagealphablending($destinationImage, false);
            //     imagesavealpha($destinationImage, true);
            //     $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
            //     imagefilledrectangle($destinationImage, 0, 0, $targetWidth, $targetHeight, $transparent);
            // }

            // 6. Resample, crop, and resize onto the new canvas
            // imagecopyresampled(
            //     $destinationImage,
            //     $sourceImage,
            //     0,
            //     0,                             // Destination X, Y
            //     $cropX,
            //     $cropY,                   // Source X, Y (the crop offset)
            //     $targetWidth,
            //     $targetHeight,      // Destination Width, Height
            //     $cropWidth,
            //     $cropHeight           // Source Width, Height (the crop box size)
            // );

            // 7. Save the optimized image to disk
            match ($imageType) {
                IMAGETYPE_JPEG => imagejpeg($destinationImage, $absoluteDestPath, 80),
                IMAGETYPE_PNG => imagepng($destinationImage, $absoluteDestPath, 8), // PNG compression is 0-9
                IMAGETYPE_WEBP => imagewebp($destinationImage, $absoluteDestPath, 80),
            };

            // 8. CRITICAL: Free up server memory 
            imagedestroy($sourceImage);
            imagedestroy($destinationImage);

            // $->update(['status' => 'optimized']);
        } catch (Throwable $e) {
            Log::error("Failed to natively optimize photo ID {$this->filename}: " . $e->getMessage());
            // $->update(['status' => 'failed_processing']);

            // Re-throw if you want the queue worker to retry
            throw $e;
        }
    }
}
