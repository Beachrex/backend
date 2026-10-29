<?php

namespace Tests\Unit\Webcams;

use App\Services\Webcams\WebcamIdExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** TASK-006: every legacy `beach.webcam` format found in Phase 0, plus admin paste formats and junk. */
class WebcamIdExtractorTest extends TestCase
{
    public static function formats(): array
    {
        return [
            'windy timelapse embed (66 legacy rows)' => [
                '<a name="windy-webcam-timelapse-player" data-id="1569527725" data-play="day" href="https://windy.com/webcams/1569527725" target="_blank">Lido di Pomposa: Spiaggia</a><script async type="text/javascript" src="https://webcams.windy.com/webcams/public/embed/script/player.js"></script>',
                1569527725,
            ],
            'lookr timelapse-player embed' => [
                '<a name="lkr-timelapse-player" data-id="1400255409" data-play="day" href="https://www.lookr.com/lookout/1400255409-Pula#action-play-day" target="_blank">Pula: Banjole bay &mdash; Play 24 hours</a><script async type="text/javascript" src="https://api.lookr.com/embed/script/timelapse.js"></script>',
                1400255409,
            ],
            'lookr timelapse embed (short name)' => [
                '<a name="lkr-timelapse" data-id="1209320953" data-play="day" href="https://lookr.com/lookout/1209320953-Trassenheide#action-play-day" target="_blank">Trassenheide</a>',
                1209320953,
            ],
            'escaped quotes from JSON' => ['<a name=\"windy-webcam-timelapse-player\" data-id=\"1616668973\" data-play=\"day\">Neum</a>', 1616668973],
            'data-id before name' => ['<a data-id="1616668973" name="windy-webcam-timelapse-player">x</a>', 1616668973],
            'iframe player url' => ['<iframe src="https://webcams.windy.com/webcams/public/embed/player?webcamId=1616668973&playerType=day"></iframe>', 1616668973],
            'old iframe path' => ['<iframe src="https://webcams.windy.com/webcams/public/embed/player/1616668973/day"></iframe>', 1616668973],
            'windy page link' => ['https://www.windy.com/webcams/1616668973', 1616668973],
            'windy localized page link' => ['https://www.windy.com/hr/-Webkamere/Bosna-i-Hercegovina/Neum/webcams/1616668973?44.1,17.6,9', 1616668973],
            'lookr short link' => ['//lookr.com/1431982296', 1431982296],
            'bare id' => [' 1616668973 ', 1616668973],
        ];
    }

    #[Test]
    #[DataProvider('formats')]
    public function extracts_the_webcam_id(string $raw, int $id): void
    {
        $this->assertSame($id, (new WebcamIdExtractor())->extract($raw));
    }

    public static function junk(): array
    {
        return [
            'null' => [null], 'empty' => ['  '], 'placeholder brez' => ['brez'], 'placeholder bret' => ['bret'],
            'whatsupcams' => ['http://www.whatsupcams.com/en/webcams/slovenia/coastal-karst/piran/piran-seaside-promenade'],
            'webcamgalore' => ['<a href="https://www.webcamgalore.com/webcam/Italy/Isola-Verde/31172.html">x</a>'],
            'short number' => ['12345'],
            'other site with webcams path' => ['https://example.com/webcams/1616668973'],
        ];
    }

    #[Test]
    #[DataProvider('junk')]
    public function returns_null_for_anything_else(?string $raw): void
    {
        $this->assertNull((new WebcamIdExtractor())->extract($raw));
    }

    #[Test]
    public function labels_legacy_formats(): void
    {
        $e = new WebcamIdExtractor();
        $this->assertSame('placeholder', $e->format('brez'));
        $this->assertSame('empty', $e->format(null));
        $this->assertSame('lookr_embed', $e->format('<a name="lkr-timelapse-player" data-id="1400255409">'));
        $this->assertSame('windy_embed', $e->format('<a name="windy-webcam-timelapse-player" data-id="1569527725">'));
        $this->assertSame('windy_url', $e->format('https://www.windy.com/webcams/1616668973'));
        $this->assertSame('unparseable', $e->format('http://www.whatsupcams.com/en/webcams/x'));
    }
}
