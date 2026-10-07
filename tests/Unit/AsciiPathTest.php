<?php

namespace Tests\Unit;

use App\Support\AsciiPath;
use Normalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsciiPathTest extends TestCase
{
    public static function paths(): array
    {
        return [
            'owner rule čšžć → cszc' => ['čšžć', 'cszc'],
            'uppercase' => ['ČŠŽĆ', 'CSZC'],
            'đ → dj' => ['Đakovo-đ', 'Djakovo-dj'],
            'Brač key' => ['public/img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg', 'public/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg'],
            'other Latin' => ['äöüéñ', 'aouen'],
            'ß' => ['straße', 'strasse'],
            'space → -' => ['Plaža Čiovo.jpg', 'Plaza-Ciovo.jpg'],
            'other chars → - and collapse' => ['a  (b)!!c.jpg', 'a-b-c.jpg'],
            'case kept' => ['Hr-114-O-Brač', 'Hr-114-O-Brac'],
            'separators kept' => ['/img//a/', '/img//a/'],
            'ASCII unchanged' => ['img/beaches/hr-69-o-pag/x_1.JPG', 'img/beaches/hr-69-o-pag/x_1.JPG'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('paths')]
    public function test_from_path(string $input, string $expected): void
    {
        $this->assertSame($expected, AsciiPath::fromPath($input));
    }

    public function test_nfd_input_gives_the_same_result_as_nfc(): void
    {
        $nfc = 'hr-114-o-brač/žal-šć.jpg';
        $nfd = Normalizer::normalize($nfc, Normalizer::FORM_D);
        $this->assertNotSame($nfc, $nfd);

        $this->assertSame('hr-114-o-brac/zal-sc.jpg', AsciiPath::fromPath($nfd));
        $this->assertSame(AsciiPath::fromPath($nfc), AsciiPath::fromPath($nfd));
    }

    public function test_cyrillic_and_greek_are_transliterated(): void
    {
        $this->assertTrue(AsciiPath::isAscii(AsciiPath::fromPath('Београд/Αθήνα.jpg')));
        $this->assertSame('Beograd', AsciiPath::fromPath('Београд'));
    }

    #[DataProvider('paths')]
    public function test_idempotent(string $input): void
    {
        $once = AsciiPath::fromPath($input);
        $this->assertSame($once, AsciiPath::fromPath($once));
    }

    public function test_output_is_always_ascii(): void
    {
        foreach (['ŁódźŘŮŐ', 'emoji 🏖.jpg', "c\u{030C}", 'ﬁ'] as $input) {
            $this->assertTrue(AsciiPath::isAscii(AsciiPath::fromPath($input)), $input);
        }
    }

    public function test_nfc_and_nfd_variants_collide(): void
    {
        $nfc = 'brač.jpg';
        $nfd = Normalizer::normalize($nfc, Normalizer::FORM_D);
        $this->assertSame(AsciiPath::fromPath($nfc), AsciiPath::fromPath($nfd));
    }

    public function test_unicode_form(): void
    {
        $this->assertSame('ascii', AsciiPath::unicodeForm('brac'));
        $this->assertSame('NFC', AsciiPath::unicodeForm('brač'));
        $this->assertSame('NFD', AsciiPath::unicodeForm(Normalizer::normalize('brač', Normalizer::FORM_D)));
    }
}
