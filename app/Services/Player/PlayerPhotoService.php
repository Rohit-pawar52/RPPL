<?php

namespace App\Services\Player;

use App\Models\Player;
use App\Models\PlayerRegistration;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Makes the photo a player sent with a registration (private) their public
 * profile photo: resized so a 6 MB phone photo does not load on every
 * player card, and saved where the other player photos are.
 */
class PlayerPhotoService
{
    /**
     * The longest side of the saved profile photo, in pixels.
     */
    public const MAX_SIDE = 800;

    /**
     * @throws RuntimeException when the registration has no readable photo
     */
    public function useRegistrationPhoto(PlayerRegistration $registration): Player
    {
        $source = Storage::disk('local');

        if (! $registration->photo_path || ! $source->exists($registration->photo_path)) {
            throw new RuntimeException('This registration has no stored photo.');
        }

        [$bytes, $extension] = $this->profileImage($source->get($registration->photo_path), pathinfo($registration->photo_path, PATHINFO_EXTENSION));

        $player = $registration->player;
        $oldPath = $player->photo_path;
        $newPath = 'players/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($newPath, $bytes);

        try {
            $player->update(['photo_path' => $newPath]);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($newPath);

            throw $e;
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $player;
    }

    /**
     * The resized JPEG when the server has the GD extension; otherwise the
     * original bytes, untouched (better a large photo than none).
     *
     * @return array{0: string, 1: string} bytes and extension
     */
    private function profileImage(string $bytes, string $originalExtension): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [$bytes, $originalExtension ?: 'jpg'];
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException('The stored photo could not be read as an image.');
        }

        $image = $this->upright($image, $bytes);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // A flat white canvas, so a transparent PNG does not turn black as a JPEG.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();

        return [$jpeg, 'jpg'];
    }

    /**
     * A phone photo is often stored sideways with an EXIF "rotate me" flag;
     * resizing drops that flag, so the rotation is applied first.
     */
    private function upright(GdImage $image, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $degrees = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $degrees === 0 ? $image : (imagerotate($image, $degrees, 0) ?: $image);
    }
}
