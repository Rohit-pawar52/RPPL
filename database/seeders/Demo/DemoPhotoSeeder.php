<?php

namespace Database\Seeders\Demo;

use App\Models\Photo;
use Database\Seeders\Demo\Support\DemoImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Four gallery rows backed by generated placeholder PNGs. Titles say
 * plainly that these are placeholders — real match photography is
 * uploaded by the admin. A row is only created once its file exists.
 */
class DemoPhotoSeeder extends Seeder
{
    public function run(): void
    {
        for ($n = 1; $n <= 4; $n++) {
            $path = DemoImage::store("photos/demo-placeholder-{$n}.png", $n);

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            Photo::firstOrCreate(
                ['title' => "RPPL sample photo {$n} (placeholder image)"],
                [
                    'description' => 'Placeholder image for the gallery. Replace it with real tournament photos from the admin panel.',
                    'photo_path' => $path,
                    'status' => 'active',
                    'priority' => $n,
                ]
            );
        }
    }
}
