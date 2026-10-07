<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductImageUpload
{
    /** Store the exact uploaded original privately; expose only the branded derivative. */
    public function store(UploadedFile $file): string
    {
        $bytes = file_get_contents($file->getRealPath());
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (! $size || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || strlen($bytes) > 5 * 1024 * 1024 || $size[0] * $size[1] > 12000000) {
            throw ValidationException::withMessages(['image_path' => 'Încarcă o imagine JPEG, PNG sau WebP de maximum 5 MB și 12 megapixeli.']);
        }

        $id = (string) Str::uuid();
        $original = "product-originals/{$id}.source";
        $public = "products/branded/{$id}.webp";
        $image = $canvas = $logo = $stamp = null;
        try {
            $image = @imagecreatefromstring($bytes);
            if (! $image) {
                throw new RuntimeException('Cannot decode image.');
            }
            // Preserve orientation in the public copy without rewriting the original.
            if ($size['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($file->getRealPath());
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                }
                $angle = match ($orientation) {
                    3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
                };
                if ($angle) {
                    $image = imagerotate($image, $angle, 0);
                }
            }
            $scale = min(1, 2000 / max(imagesx($image), imagesy($image)));
            $w = max(1, (int) round(imagesx($image) * $scale));
            $h = max(1, (int) round(imagesy($image) * $scale));
            $canvas = imagecreatetruecolor($w, $h);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, imagesx($image), imagesy($image));

            $logo = imagecreatefrompng(public_path('apple-touch-icon.png'));
            $edge = max(1, (int) round(min($w, $h) * 0.12));
            $pad = max(1, (int) round(min($w, $h) * 0.025));
            $stamp = imagecreatetruecolor($edge, $edge);
            imagealphablending($stamp, false);
            imagesavealpha($stamp, true);
            imagecopyresampled($stamp, $logo, 0, 0, 0, 0, $edge, $edge, imagesx($logo), imagesy($logo));
            for ($y = 0; $y < $edge; $y++) {
                for ($x = 0; $x < $edge; $x++) {
                    $c = imagecolorsforindex($stamp, imagecolorat($stamp, $x, $y));
                    $alpha = (int) round(127 - (127 - $c['alpha']) * 0.48);
                    imagesetpixel($stamp, $x, $y, imagecolorallocatealpha($stamp, $c['red'], $c['green'], $c['blue'], $alpha));
                }
            }
            imagealphablending($canvas, true);
            imagecopy($canvas, $stamp, max(0, $w - $edge - $pad), max(0, $h - $edge - $pad), 0, 0, $edge, $edge);
            ob_start();
            try {
                $encoded = imagewebp($canvas, null, 90);
                $derivative = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if (! $encoded || ! $derivative) {
                throw new RuntimeException('Cannot encode image.');
            }
            if (! Storage::disk('local')->put($original, $bytes, 'private')) {
                throw new RuntimeException('Cannot preserve original.');
            }
            if (! Storage::disk('public')->put($public, $derivative, 'public')) {
                throw new RuntimeException('Cannot store derivative.');
            }

            return $public;
        } catch (Throwable $exception) {
            // Only this new upload's UUID paths can be removed on failure.
            Storage::disk('public')->delete($public);
            Storage::disk('local')->delete($original);
            report($exception);
            throw ValidationException::withMessages(['image_path' => 'Imaginea nu a putut fi procesată. Reîncearcă sau contactează administratorul.']);
        }
    }
}
