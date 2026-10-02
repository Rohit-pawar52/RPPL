<?php

namespace Tests\Unit;

use App\Support\UploadLimits;
use PHPUnit\Framework\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_php_ini_sizes_are_read_in_bytes(): void
    {
        $this->assertSame(40 * 1024 * 1024, UploadLimits::bytes('40M'));
        $this->assertSame(2 * 1024 ** 3, UploadLimits::bytes('2G'));
        $this->assertSame(512 * 1024, UploadLimits::bytes('512K'));
        $this->assertSame(8388608, UploadLimits::bytes('8388608'));

        // post_max_size=0 means "no limit", and anything that is not a size
        // is treated the same way rather than guessed at.
        foreach (['0', '', '-1', 'lots'] as $notation) {
            $this->assertSame(0, UploadLimits::bytes($notation), $notation);
        }
    }

    public function test_the_per_file_limit_is_what_the_server_can_really_take(): void
    {
        // Plenty of room: the 10 MB that was asked for.
        $this->assertSame(10240, UploadLimits::perFileKilobytes(10240, 2, '40M', '40M'));

        // upload_max_filesize is the smaller limit.
        $this->assertSame(2048, UploadLimits::perFileKilobytes(10240, 2, '2M', '40M'));

        // post_max_size: 8 MB less 1 MB of form data, shared by two files.
        $this->assertSame(3584, UploadLimits::perFileKilobytes(10240, 2, '40M', '8M'));

        // post_max_size=0 is unlimited; and the answer is never zero.
        $this->assertSame(10240, UploadLimits::perFileKilobytes(10240, 2, '40M', '0'));
        $this->assertSame(1, UploadLimits::perFileKilobytes(10240, 2, '40M', '1M'));
    }

    public function test_megabytes_are_shown_without_trailing_zeros(): void
    {
        $this->assertSame('10', UploadLimits::megabytes(10240));
        $this->assertSame('2', UploadLimits::megabytes(2048));
        $this->assertSame('3.5', UploadLimits::megabytes(3584));
        $this->assertSame('0.5', UploadLimits::megabytes(512));
    }
}
