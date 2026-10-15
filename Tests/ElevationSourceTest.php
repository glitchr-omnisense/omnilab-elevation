<?php

namespace Omnilab\Elevation\Tests;

use Omnilab\Elevation\ElevationSource;
use Omnilab\Elevation\ElevationSourceFactory;
use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\RateLimitedException;
use Omnilab\Model\Point;
use Omnilab\Source\SeriesQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from api.opentopodata.org/v1 on 2026-10-16: Strasbourg,
 * the summit of Mont Blanc and a point of the Pacific in SRTM 30 m; the
 * profile between Strasbourg and Mont Blanc in five samples; Mont Blanc in
 * EU-DEM and the Pacific in GEBCO 2020 in one call; an unknown dataset.
 */
final class ElevationSourceTest extends TestCase
{
    /** @var list<array{string, array<string, string>}> */
    private array $calls = [];

    private function source(?MockResponse $response = null, array $options = []): ElevationSource
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            $path = (string) parse_url($url, \PHP_URL_PATH);
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $this->calls[] = [$path, $query];
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                '/v1/eudem25m,gebco2020' === $path => 'eudem25m-gebco2020.json',
                isset($query['samples']) => 'srtm30m-profile.json',
                '/v1/srtm30m' === $path => 'srtm30m-three.json',
                default => 'unknown-dataset.json',
            };

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture), ['http_code' => 'unknown-dataset.json' === $fixture ? 400 : 200]);
        });
        $source = (new ElevationSourceFactory($http))->create(['throttle' => 0] + $options);
        self::assertInstanceOf(ElevationSource::class, $source);

        return $source;
    }

    public function testThePointsElevationsInOneCall(): void
    {
        $source = $this->source();
        $points = $source->at(new Point(48.5734, 7.7521), new Point(45.8326, 6.8652), new Point(0, -150));

        self::assertEquals([new Point(48.5734, 7.7521, 150.0), new Point(45.8326, 6.8652, 4791.0), new Point(0, -150)], $points);
        self::assertSame(['srtm30m', 'srtm30m', null], $source->lastDatasets());
        self::assertSame(['/v1/srtm30m', ['locations' => '48.5734,7.7521|45.8326,6.8652|0,-150', 'interpolation' => 'bilinear']], $this->calls[0]);
    }

    public function testAHundredACall(): void
    {
        $answer = static fn (int $n) => json_encode(['results' => array_fill(0, $n, ['dataset' => 'srtm30m', 'elevation' => 1.0, 'location' => ['lat' => 1.0, 'lng' => 1.0]]), 'status' => 'OK']);
        $answers = [new MockResponse($answer(100)), new MockResponse($answer(50))];
        $calls = 0;
        $source = (new ElevationSourceFactory(new MockHttpClient(static function () use (&$answers, &$calls) {
            ++$calls;

            return array_shift($answers);
        })))->create(['throttle' => 0]);

        self::assertCount(150, $source->at(...array_fill(0, 150, new Point(1, 1))));
        self::assertSame(2, $calls);
    }

    public function testAProfile(): void
    {
        $profile = $this->source()->profile(new Point(48.5734, 7.7521), new Point(45.8326, 6.8652), 5);

        self::assertSame([150.0, 210.0, 882.0, 1021.0, 4791.0], array_map(static fn (Point $p) => $p->elevation, $profile));
        self::assertSame(47.2040, round($profile[2]->latitude, 4));
        self::assertSame('5', $this->calls[0][1]['samples']);
    }

    public function testSeveralDatasetsTheFirstWithAValueAnswering(): void
    {
        $source = $this->source(null, ['dataset' => ['eudem25m', 'gebco2020'], 'interpolation' => 'cubic']);
        $points = $source->at(new Point(45.8326, 6.8652), new Point(0, -150));

        self::assertSame([4780.3, -4449.0], [$points[0]->elevation, $points[1]->elevation]);
        self::assertSame(['eudem25m', 'gebco2020'], $source->lastDatasets());
        self::assertSame('cubic', $this->calls[0][1]['interpolation']);
    }

    public function testAnUnknownDatasetAndTheLimits(): void
    {
        try {
            $this->source(null, ['dataset' => 'nope'])->at(new Point(1, 1));
            self::fail('A 400.');
        } catch (ProviderException $e) {
            self::assertStringContainsString("Dataset 'nope' not in config.", $e->getMessage());
        }
        try {
            $this->source(new MockResponse('{"error": "Too many requests.", "status": "INVALID_REQUEST"}', ['http_code' => 429]))->at(new Point(1, 1));
            self::fail('A 429.');
        } catch (RateLimitedException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->source()->profile(new Point(0, 0), new Point(1, 1), 101);
    }

    public function testNoShapeOfTheContract(): void
    {
        $source = $this->source();
        self::assertSame([], $source->capabilities());
        self::assertSame('elevation', $source->getName());
        $this->expectException(NotSupportedException::class);
        $source->series(new SeriesQuery(['elevation'], new Point(0, 0)));
    }

    public function testABadOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->source(null, ['interpolation' => 'linear']);
    }
}
