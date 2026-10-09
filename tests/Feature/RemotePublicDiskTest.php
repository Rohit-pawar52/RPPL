<?php

namespace Tests\Feature;

use App\Support\Media;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploads can live in an S3-compatible bucket (PUBLIC_DISK_DRIVER=s3) instead of the local disk, so a host that
 * wipes its disk on every restart (Render's free plan) keeps its pictures. These checks need no network.
 */
class RemotePublicDiskTest extends TestCase
{
    private const VARIABLES = [
        'PUBLIC_DISK_DRIVER' => 's3',
        'PUBLIC_S3_KEY' => 'key',
        'PUBLIC_S3_SECRET' => 'secret',
        'PUBLIC_S3_REGION' => 'ap-south-1',
        'PUBLIC_S3_BUCKET' => 'rppl',
        'PUBLIC_S3_ENDPOINT' => 'http://127.0.0.1:1',
        'PUBLIC_S3_URL' => 'https://files.example/storage/v1/object/public/rppl/',
    ];

    protected function tearDown(): void
    {
        foreach (array_keys(self::VARIABLES) as $name) {
            Env::getRepository()->clear($name);
        }

        parent::tearDown();
    }

    private function remoteConfig(): array
    {
        foreach (self::VARIABLES as $name => $value) {
            Env::getRepository()->set($name, $value);
        }

        return (require config_path('filesystems.php'))['disks']['public'];
    }

    public function test_the_public_disk_is_local_unless_a_bucket_is_configured(): void
    {
        $default = (require config_path('filesystems.php'))['disks']['public'];

        $this->assertSame('local', $default['driver']);
        $this->assertStringEndsWith('/storage', $default['url']);

        $remote = $this->remoteConfig();

        $this->assertSame('s3', $remote['driver']);
        $this->assertSame('rppl', $remote['bucket']);
        $this->assertSame('https://files.example/storage/v1/object/public/rppl', $remote['url']);
        $this->assertTrue($remote['throw'], 'A failed upload must be an error, not a path that points at nothing.');
    }

    public function test_a_picture_on_a_remote_bucket_gets_its_public_url_without_asking_the_bucket(): void
    {
        config(['filesystems.disks.public' => $this->remoteConfig()]);
        Storage::purge('public');

        // The endpoint above refuses connections: an exists() check would fail and give the default picture.
        $this->assertSame('https://files.example/storage/v1/object/public/rppl/players/a.jpg', Media::url('players/a.jpg', 'user'));
        $this->assertSame(Media::defaultUrl('user'), Media::url(null, 'user'));
        $this->assertNull(Media::existingUrl(''));
    }

    public function test_on_the_local_disk_a_missing_file_still_gives_the_default_picture(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('players/here.jpg', 'x');

        $this->assertStringContainsString('players/here.jpg', Media::url('players/here.jpg', 'user'));
        $this->assertSame(Media::defaultUrl('user'), Media::url('players/gone.jpg', 'user'));
    }
}
