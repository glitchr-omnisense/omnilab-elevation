<?php

namespace Omnilab\Elevation;

use Omnilab\Exception\ProviderException;
use Omnilab\Model\Point;
use Omnilab\Source\HttpSource;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The elevation of points and profiles from a digital elevation model,
 * through Open Topo Data (api.opentopodata.org/v1): SRTM, ASTER, EU-DEM,
 * Mapzen, ETOPO1, GEBCO and the other datasets it serves - outside the
 * contract's four shapes: an elevation is neither a thing named, nor a
 * variable over time, nor a catalogued product, nor something that
 * happened. The answers are Points with their elevation set, in metres
 * above sea level (negative below it, for a bathymetric dataset), null
 * where the dataset has none.
 *
 * No key. The public instance answers 100 locations a call, a call a
 * second, 1,000 calls a day: the source sends 100 a call and waits a second
 * between two.
 */
final class ElevationSource extends HttpSource
{
    public const BASE_URI = 'https://api.opentopodata.org/v1/';

    /** The most locations one call takes on the public instance. */
    public const MAX_LOCATIONS = 100;

    /** How the elevation between a model's cells is read. */
    public const INTERPOLATIONS = ['nearest', 'bilinear', 'cubic'];

    /** @var list<string|null> */
    private array $lastDatasets = [];

    /**
     * @param list<string> $datasets the datasets asked, the first that has a value answering (srtm30m, aster30m, eudem25m, mapzen, etopo1, gebco2020...)
     */
    public function __construct(
        HttpClientInterface $http,
        private readonly array $datasets = ['srtm30m'],
        private readonly string $interpolation = 'bilinear',
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 1.0,
    ) {
        if ([] === $datasets || array_filter($datasets, static fn (string $d) => !preg_match('~^[a-z0-9_-]+$~', $d))) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a list of Open Topo Data\'s datasets.', implode(',', $datasets)));
        }
        if (!\in_array($interpolation, self::INTERPOLATIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Open Topo Data interpolates by %s, not "%s".', implode(', ', self::INTERPOLATIONS), $interpolation));
        }
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'elevation';
    }

    /** None of the contract's: elevations are given by at() and profile(). */
    public function capabilities(): array
    {
        return [];
    }

    /**
     * The elevation of each point, in the order given: the points with
     * their elevation in metres (null where the datasets have none), a
     * call for each hundred.
     *
     * @return list<Point>
     */
    public function at(Point ...$points): array
    {
        $answered = [];
        $datasets = [];
        foreach (array_chunk($points, self::MAX_LOCATIONS) as $chunk) {
            $answered = [...$answered, ...$this->ask(['locations' => implode('|', array_map(static fn (Point $p) => $p->latitude.','.$p->longitude, $chunk))], \count($chunk))];
            $datasets = [...$datasets, ...$this->lastDatasets];
        }
        $this->lastDatasets = $datasets;

        return $answered;
    }

    /**
     * The profile from one point to another: $samples points evenly spaced
     * along the great circle, the ends included, each with its elevation.
     * One call (100 samples at most on the public instance).
     *
     * @return list<Point>
     */
    public function profile(Point $from, Point $to, int $samples = 100): array
    {
        if ($samples < 2 || $samples > self::MAX_LOCATIONS) {
            throw new \InvalidArgumentException(\sprintf('A profile has 2 to %d samples, not %d.', self::MAX_LOCATIONS, $samples));
        }

        return $this->ask(['locations' => $from->latitude.','.$from->longitude.'|'.$to->latitude.','.$to->longitude, 'samples' => $samples], $samples);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<Point>
     */
    private function ask(array $parameters, int $expected): array
    {
        $data = $this->getJson(implode(',', $this->datasets), $parameters + ['interpolation' => $this->interpolation]) ?? throw new ProviderException('elevation', 'Open Topo Data answered 404: is "'.implode(',', $this->datasets).'" one of its datasets?');
        if ('OK' !== ($data['status'] ?? null)) {
            throw new ProviderException('elevation', self::text(\is_string($data['error'] ?? null) ? $data['error'] : null) ?? 'Open Topo Data answered '.json_encode($data['status'] ?? null));
        }
        $points = [];
        $this->lastDatasets = [];
        foreach ($data['results'] ?? [] as $result) {
            $location = $result['location'] ?? null;
            if (!\is_array($location) || !is_numeric($location['lat'] ?? null) || !is_numeric($location['lng'] ?? null)) {
                throw new ProviderException('elevation', 'Open Topo Data answered a result without its location');
            }
            $elevation = is_numeric($result['elevation'] ?? null) ? round((float) $result['elevation'], 2) : null;
            $points[] = new Point((float) $location['lat'], (float) $location['lng'], $elevation);
            $this->lastDatasets[] = null === $elevation ? null : self::text(\is_string($result['dataset'] ?? null) ? $result['dataset'] : null);
        }
        if (\count($points) !== $expected) {
            throw new ProviderException('elevation', \sprintf('Open Topo Data answered %d locations for %d', \count($points), $expected));
        }

        return $points;
    }

    /**
     * The dataset each point of the last answer came from, in its order
     * (with several datasets, the first that had a value; null for none).
     *
     * @return list<string|null>
     */
    public function lastDatasets(): array
    {
        return $this->lastDatasets;
    }
}
