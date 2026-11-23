<?php

namespace Tests\Feature;

use App\Models\Amc\AmcFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AmcFixtures;
use Tests\TestCase;

/** Fixes after the AMC browser re-test (21/11/2026): phone photos stored sideways keep their shape in a collage. */
class AmcRetestFixesTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        if (! function_exists('imagecreatetruecolor') || ! function_exists('exif_read_data')) {
            $this->markTestSkipped('GD and EXIF are needed');
        }
    }

    /**
     * A phone-style JPEG: stored 1200×900 (left half red, right half blue) with EXIF Orientation $o. With 6 the photo is
     * upright at 900×1200, red on top and blue below.
     */
    private function sideways(string $name, int $o = 6): UploadedFile
    {
        $img = imagecreatetruecolor(1200, 900);
        imagefilledrectangle($img, 0, 0, 599, 899, imagecolorallocate($img, 220, 30, 30));
        imagefilledrectangle($img, 600, 0, 1199, 899, imagecolorallocate($img, 30, 30, 220));
        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($img);
        // APP1 "Exif": little-endian TIFF header, one IFD entry 0x0112 (Orientation, SHORT, 1) = $o, no next IFD.
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('vvVv', 0x0112, 3, 1, $o).pack('v', 0).pack('V', 0);
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;
        $path = tempnam(sys_get_temp_dir(), 'amc');
        file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));
        $this->assertSame($o, exif_read_data($path)['Orientation']);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function colourAt($img, int $x, int $y): string
    {
        $c = imagecolorat($img, $x, $y);
        [$r, $b] = [($c >> 16) & 0xFF, $c & 0xFF];

        return $r > 150 && $b < 110 ? 'red' : ($b > 150 && $r < 110 ? 'blue' : 'other');
    }

    public function test_sideways_phone_photos_keep_their_shape_in_a_collage(): void
    {
        $c = $this->amcContract($this->amcClient());
        $this->actingAs($this->admin())->post(route('amc.contract.files.store', $c), [
            'category' => 'deployment', 'files' => [$this->sideways('a.jpg'), $this->sideways('b.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $img = imagecreatefromstring(Storage::disk('local')->get(AmcFile::firstOrFail()->path));
        // Each upright tile is 800×1067 (portrait), not 800×600: one row, 8 px gaps.
        $this->assertSame([2 * 800 + 3 * 8, 8 + 1067 + 8], [imagesx($img), imagesy($img)]);
        foreach ([8 + 400, 816 + 400] as $x) {
            $this->assertSame('red', $this->colourAt($img, $x, 8 + 100), 'top of the tile');
            $this->assertSame('blue', $this->colourAt($img, $x, 8 + 1000), 'bottom of the tile, not squashed');
        }
    }

    public function test_a_single_sideways_photo_is_stored_upright(): void
    {
        $c = $this->amcContract($this->amcClient());
        $this->actingAs($this->admin())->post(route('amc.contract.files.store', $c), [
            'category' => 'challan_image', 'files' => [$this->sideways('one.jpg', 8)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $img = imagecreatefromstring(Storage::disk('local')->get(AmcFile::firstOrFail()->path));
        $this->assertSame([900, 1200], [imagesx($img), imagesy($img)]);
        // Orientation 8 turns the other way: the blue half is on top.
        $this->assertSame('blue', $this->colourAt($img, 450, 100));
        $this->assertSame('red', $this->colourAt($img, 450, 1100));
    }
}
