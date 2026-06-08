<?php

namespace Tests\Unit\Services;

use App\Services\Media\ImageSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ImageSanitizerTest extends TestCase
{
    private ImageSanitizer $sanitizer;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new ImageSanitizer;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_sanitizes_valid_jpeg(): void
    {
        $result = $this->sanitizer->sanitize($this->makeImage('jpeg', 100, 50));

        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame('jpg', $result['extension']);
        $this->assertSame(100, $result['width']);
        $this->assertSame(50, $result['height']);
        // Output is itself a decodable image
        $this->assertNotFalse(@imagecreatefromstring($result['bytes']));
    }

    public function test_sanitizes_valid_png_and_webp(): void
    {
        $this->assertSame('image/png', $this->sanitizer->sanitize($this->makeImage('png'))['mime']);
        $this->assertSame('image/webp', $this->sanitizer->sanitize($this->makeImage('webp'))['mime']);
    }

    public function test_destroys_php_payload_appended_to_valid_image(): void
    {
        // Polyglot: a real PNG with a PHP webshell appended — still a valid
        // image for most parsers, lethal if ever executed by a misconfigured
        // server. The re-encode must strip the payload.
        $path = $this->makeImage('png');
        file_put_contents($path, '<?php system($_GET["cmd"]); ?>', FILE_APPEND);

        $result = $this->sanitizer->sanitize($path);

        $this->assertStringNotContainsString('<?php', $result['bytes']);
        $this->assertStringNotContainsString('system(', $result['bytes']);
    }

    public function test_rejects_php_file_with_image_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        file_put_contents($path, '<?php echo "owned"; ?>');
        $this->tempFiles[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer->sanitize($path);
    }

    public function test_rejects_svg(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->tempFiles[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer->sanitize($path);
    }

    public function test_rejects_gif(): void
    {
        $image = imagecreatetruecolor(10, 10);
        $path = tempnam(sys_get_temp_dir(), 'img');
        imagegif($image, $path);
        imagedestroy($image);
        $this->tempFiles[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer->sanitize($path);
    }

    public function test_rejects_fake_magic_bytes_with_garbage_body(): void
    {
        // Real JPEG magic bytes followed by garbage — passes naive
        // signature checks, must fail the full decode.
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, "\xFF\xD8\xFF\xE0".random_bytes(2048));
        $this->tempFiles[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer->sanitize($path);
    }

    public function test_rejects_oversized_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dimensions exceed');

        $this->sanitizer->sanitize($this->makeImage('png', ImageSanitizer::MAX_DIMENSION + 1, 10));
    }

    public function test_strips_exif_metadata(): void
    {
        // JPEG with an EXIF APP1 segment (camera/GPS metadata carrier).
        $path = $this->makeImage('jpeg');
        $jpeg = file_get_contents($path);
        $exif = "\xFF\xE1".pack('n', 20)."Exif\x00\x00FAKEGPSDATA\x00";
        // Inject APP1 right after SOI marker
        file_put_contents($path, substr($jpeg, 0, 2).$exif.substr($jpeg, 2));

        $result = $this->sanitizer->sanitize($path);

        $this->assertStringNotContainsString('FAKEGPSDATA', $result['bytes']);
    }

    /**
     * Create a real temp image and return its path.
     */
    private function makeImage(string $format, int $width = 60, int $height = 40): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 200, 60, 60));

        $path = tempnam(sys_get_temp_dir(), 'img');
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };
        imagedestroy($image);

        $this->tempFiles[] = $path;

        return $path;
    }
}
