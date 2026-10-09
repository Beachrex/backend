<?php

namespace Tests\Feature;

use Aws\CommandInterface;
use Aws\Result;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * The inventory reads every vtour text file. On the real bucket that is tens of thousands of GETs, so on S3
 * they run in parallel (CommandPool). This drives the S3 code path through a mocked S3 handler.
 */
class AsciiStoragePathsS3ReadTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> key => body */
    private array $objects = [];

    /** @var list<string> */
    private array $gets = [];

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);

        $this->objects = [
            'public/img/beaches/hr-9/vtour/tour.xml' => '<image url="panos/brač_1.tiles/pano.jpg" />',
            'public/img/beaches/hr-9/vtour/tour.html' => '<html>ascii only</html>',
            'public/img/beaches/hr-9/vtour/panos/brač_1.tiles/pano.jpg' => 'binary',
        ];
        for ($i = 0; $i < 600; $i++) {
            $this->objects["public/img/beaches/x-{$i}/vtour/plugins/p.js"] = "var a = {$i};";
        }

        config(['filesystems.disks.s3mock' => [
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'us-east-1',
            'bucket' => 'beachrex-test',
            'handler' => fn (CommandInterface $command, RequestInterface $request) => Create::promiseFor($this->respond($command)),
        ]]);

        $this->csv = sys_get_temp_dir().'/ascii-s3-'.uniqid().'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    private function respond(CommandInterface $command): Result
    {
        if ($command->getName() === 'ListObjectsV2') {
            $prefix = (string) ($command['Prefix'] ?? '');
            $contents = [];
            foreach ($this->objects as $key => $body) {
                if (str_starts_with($key, $prefix)) {
                    $contents[] = ['Key' => $key, 'Size' => strlen($body), 'ETag' => '"'.md5($body).'"', 'LastModified' => new \DateTimeImmutable];
                }
            }

            return new Result(['Contents' => $contents, 'IsTruncated' => false]);
        }

        if ($command->getName() === 'GetObject') {
            $this->gets[] = $command['Key'];

            return new Result(['Body' => Utils::streamFor($this->objects[$command['Key']])]);
        }

        throw new \LogicException('Unexpected S3 call: '.$command->getName());
    }

    public function test_reads_vtour_text_files_through_the_s3_pool_and_finds_references(): void
    {
        $this->artisan('storage:ascii-paths', ['--inventory' => true, '--disk' => 's3mock', '--output' => $this->csv])
            ->expectsOutputToContain('Reading 602 vtour text files')
            ->expectsOutputToContain('read 500/602 vtour text files')
            ->expectsOutputToContain('read 602/602 vtour text files')
            ->expectsOutputToContain('Read-only')
            ->assertSuccessful();

        // Only vtour text files are fetched, each once; images are never downloaded.
        $this->assertCount(602, $this->gets);
        $this->assertCount(602, array_unique($this->gets));
        $this->assertNotContains('public/img/beaches/hr-9/vtour/panos/brač_1.tiles/pano.jpg', $this->gets);

        $lines = array_map('str_getcsv', file($this->csv, FILE_IGNORE_NEW_LINES));
        $header = array_shift($lines);
        $rows = array_map(fn ($line) => array_combine($header, $line), $lines);
        $vtour = array_values(array_filter($rows, fn ($r) => $r['source'] === 'vtour_ref'));

        $this->assertCount(1, $vtour);
        $this->assertSame('public/img/beaches/hr-9/vtour/tour.xml', $vtour[0]['location']);
        $this->assertStringContainsString('brač_1.tiles/pano.jpg', $vtour[0]['detail']);
    }

    public function test_copied_keys_are_recognised_from_listing_etags_without_head_requests(): void
    {
        // An old NFD key with an identical ASCII copy, and one whose ASCII key holds a different file.
        $this->objects = [
            \Normalizer::normalize('public/img/beaches/hr-114-o-brač/a.jpg', \Normalizer::FORM_D) => 'same',
            'public/img/beaches/hr-114-o-brac/a.jpg' => 'same',
            'public/img/beaches/hr-1-o-šolta/b.jpg' => 'old',
            'public/img/beaches/hr-1-o-solta/b.jpg' => 'new',
        ];

        // respond() throws on any call other than ListObjectsV2/GetObject, so a HeadObject would fail the run.
        $this->artisan('storage:ascii-paths', ['--inventory' => true, '--disk' => 's3mock', '--output' => $this->csv])
            ->assertSuccessful();

        $lines = array_map('str_getcsv', file($this->csv, FILE_IGNORE_NEW_LINES));
        $header = array_shift($lines);
        $issues = [];
        foreach ($lines as $line) {
            $row = array_combine($header, $line);
            $issues[$row['new']] = $row['issue'];
        }

        $this->assertSame('copied', $issues['public/img/beaches/hr-114-o-brac/a.jpg']);
        $this->assertSame('collision', $issues['public/img/beaches/hr-1-o-solta/b.jpg']);
    }
}
