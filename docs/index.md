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

## Credentials

None on the public instance (`api.opentopodata.org`): no key, no account, within 100 locations a
call, a call a second and 1,000 calls a day. Beyond that, a self-hosted instance of Open Topo Data
(open source) is named by `base_uri`.

## Options

The factory's options, with the defaults it applies (`ElevationSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `dataset` | `string\|list<string>` | `srtm30m` | no | the dataset asked, or a list (or a comma-separated string): with several, each point takes the first that has a value there. Lower-case letters, digits, `_` and `-` only, else `\InvalidArgumentException` at `create()` |
| `interpolation` | `string` | `bilinear` | no | `nearest`, `bilinear` or `cubic` (another is an `\InvalidArgumentException` at `create()`) |
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `1` | no | seconds left between two calls: the public instance takes one a second |
| `base_uri` | `string` | `https://api.opentopodata.org/v1/` | no | the instance: the public one, or a self-hosted one |

## Capabilities

`capabilities()` answers an empty list: every operation of the contract throws
`NotSupportedException` before any call. The source answers through methods of its own:
`at(Point ...$points): list<Point>` (a call for each hundred points), `profile(Point $from, Point
$to, int $samples = 100): list<Point>` (2 to 100 samples, one call) and `lastDatasets():
list<?string>` (the dataset each point of the last answer came from).

| Capability | Supported | |
|---|---|---|
| `entity` | no | nothing here is an entity |
| `search` | no |  |
| `series` | no | an elevation is no series: `at()` and `profile()` answer `Point`s |
| `products` | no |  |
| `occurrences` | no |  |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no |  |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no | `at()` takes points; a box is not read |
| `station` | no |  |
| `cloud_cover` | no |  |

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

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Elevation\ElevationSourceFactory;
use Omnilab\Model\Point;
use Symfony\Component\HttpClient\HttpClient;

$dem = (new ElevationSourceFactory(HttpClient::create()))->create([
    'mailto' => 'you@example.org',
    'dataset' => ['srtm30m', 'gebco2020'],   // SRTM on land, GEBCO where SRTM has no value
]);

$points = $dem->at(new Point(48.5734, 7.7521), new Point(45.8326, 6.8652), new Point(0, -150));
foreach ($points as $i => $point) {
    printf("%s,%s  %s m  (%s)\n", $point->latitude, $point->longitude, $point->elevation ?? 'null', $dem->lastDatasets()[$i] ?? 'no dataset');
}

$profile = $dem->profile(new Point(48.5734, 7.7521), new Point(45.8326, 6.8652), 5);
echo 'Strasbourg to Mont Blanc: ', implode(', ', array_map(static fn (Point $p) => $p->elevation, $profile)), " m\n";
```

```
48.5734,7.7521  150 m  (srtm30m)
45.8326,6.8652  4791 m  (srtm30m)
0,-150  -4449 m  (gebco2020)
Strasbourg to Mont Blanc: 150, 210, 882, 1021, 4791 m
```

(as answered on 2026-10-27, two calls a second apart)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        elevation: { factory: elevation, options: { mailto: '%env(OMNILAB_MAILTO)%', dataset: [srtm30m, gebco2020] } }
```

The bundle injects it as `SourceInterface $elevation`; `at()` and `profile()` are methods of
`Omnilab\Elevation\ElevationSource`, not of the interface: check the instance
(`$elevation instanceof ElevationSource`) before calling them.

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Besides: a 404 (an address the instance does not serve) is a `ProviderException` that asks
whether the dataset exists, not a null; an answer whose `status` is not `OK` a `ProviderException`
with Open Topo Data's `error` (an unknown dataset: `INVALID_REQUEST`, with a 400); an answer
without a result's location, or with fewer results than points asked, a `ProviderException`. A
profile of fewer than 2 or more than 100 samples is an `\InvalidArgumentException` before any
call; every operation of the contract a `NotSupportedException`.

## Verified, and not

Against the public API on 2026-10-16: Strasbourg (150 m), the summit of Mont Blanc (4,791 m in
SRTM 30 m, 4,780.3 m in EU-DEM 25 m) and a point of the Pacific (null in SRTM; -4,449 m in GEBCO
2020) in one call each; the profile from Strasbourg to Mont Blanc in five samples (150, 210, 882,
1,021, 4,791 m); an unknown dataset (400). The fixtures in `Tests/Fixtures/` are its answers.

Again on 2026-10-27: the same three points and the same profile, the same elevations (the
example above).

Not verified: the daily limit of 1,000 calls (not reached) and the public instance's answer past
it; a self-hosted instance.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Open Topo Data nor by the producers of its datasets. Each dataset keeps its own
licence and attribution (SRTM: NASA/USGS; EU-DEM: Copernicus; GEBCO: the GEBCO Compilation Group;
ASTER GDEM: NASA/METI...), as Open Topo Data's dataset pages state and the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-elevation#terms-of-use) sums up.
Whoever runs the package keeps to the public instance's limits, credits the dataset used, and is
responsible for any use made of what it returns: see the README's
[Responsibility](https://github.com/glitchr-omnisense/omnilab-elevation#responsibility) section.
