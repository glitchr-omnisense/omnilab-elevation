# omnilab/elevation

## Installation

```sh
composer require omnilab/elevation
```

```php
use Omnilab\Elevation\ElevationSourceFactory;

$dem = (new ElevationSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional
    'dataset' => 'srtm30m',            // optional: a dataset, or a list (the first with a value answers)
    'interpolation' => 'bilinear',     // optional: nearest, bilinear, cubic
]);
```

With the Symfony bundle, the factory is registered as `elevation` when the bundle knows it;
otherwise register `ElevationSourceFactory` as a service.

## Which API

Open Topo Data (`https://api.opentopodata.org/v1/{datasets}?locations=lat,lng|lat,lng&interpolation=`
and `&samples=` for a profile). Its public instance served on 2026-10-16: `aster30m`, `bkg200m`,
`emod2018`, `etopo1`, `eudem25m`, `gebco2020`, `mapzen`, `ned10m`, `nzdem8m`, `srtm30m`,
`srtm90m` and others (its `datasets` list). A dataset answers `null` outside its extent (SRTM at
sea, at 0, -150). Errors: `{"error": ..., "status": "INVALID_REQUEST"}` with a 400 (an unknown
dataset), a `ProviderException` with the message; a 429 a `RateLimitedException`.

## Its shape: none of the contract's

`capabilities()` is empty; `series()`, `entity()` and the rest throw `NotSupportedException`.

| Method | Answers |
|---|---|
| `at(Point ...$points)` | the points, in their order, each with its `elevation` (m, rounded to the centimetre; null where no dataset has a value): a call for each hundred |
| `profile(Point $from, Point $to, int $samples = 100)` | `$samples` points (2 to 100) evenly spaced along the great circle, the ends included, each with its elevation: one call |
| `lastDatasets()` | the dataset each point of the last answer came from (null for none) |

## The Collector

No path: nothing here is an entity.

## What the API does not give here

The datasets' list (`/datasets`), the health endpoint, POST requests of many locations, and the
`nodata_value` option (null is kept for "no value") are not used.

## Verified, and not

Against the public API on 2026-10-16: Strasbourg (150 m), the summit of Mont Blanc (4,791 m in
SRTM 30 m, 4,780.3 m in EU-DEM 25 m) and a point of the Pacific (null in SRTM; -4,449 m in GEBCO
2020) in one call each; the profile from Strasbourg to Mont Blanc in five samples (150, 210, 882,
1,021, 4,791 m); an unknown dataset (400). The fixtures in `Tests/Fixtures/` are its answers.

Not verified: the daily limit of 1,000 calls (not reached) and the public instance's answer past
it; a self-hosted instance.
