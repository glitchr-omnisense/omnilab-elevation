# omnilab/elevation

The elevation of points, and of a profile between two points, for
[omniscient/omnilab](https://github.com/glitchr-studio/omnilab), from a digital elevation model -
SRTM, ASTER, EU-DEM, Mapzen, ETOPO1, GEBCO and the other datasets
[Open Topo Data](https://www.opentopodata.org) serves - through its public API or an instance of
your own. No key.

```php
$dem = (new ElevationSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
[$strasbourg, $blanc] = $dem->at(new Point(48.5734, 7.7521), new Point(45.8326, 6.8652));
$strasbourg->elevation;              // 150.0 (m, SRTM 30 m)
$blanc->elevation;                   // 4791.0

$profile = $dem->profile(new Point(48.5734, 7.7521), new Point(45.8326, 6.8652), 5);
// 150, 210, 882, 1021, 4791 m along the great circle

$both = (new ElevationSourceFactory($http))->create(['dataset' => ['eudem25m', 'gebco2020']]);
$both->at(new Point(0, -150));       // -4449.0: the first dataset with a value answers (GEBCO's sea floor)
$both->lastDatasets();               // ['gebco2020']
```

```yaml
omnilab:
    sources:
        elevation:
            factory: elevation
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
                dataset: srtm30m               # or a list: [eudem25m, srtm30m, gebco2020]
                interpolation: bilinear        # nearest, bilinear, cubic
                base_uri: 'https://api.opentopodata.org/v1/'   # or a self-hosted instance
```

**Outside the contract**: an elevation is none of the contract's four shapes - not a thing
named, not a variable over time, not a catalogued product, not something that happened -, so
the source declares no capability and answers by its own methods, `at()` and `profile()`, with
the core's `Point`s, their `elevation` set in metres above sea level.

See [docs/](docs/index.md) for what is read and what the API does not give here.

## Terms of use

Open Topo Data's public API is free and needs no key, but limited: at most 100 locations a call,
one call a second, 1,000 calls a day - the source sends 100 a call and waits a second between two;
beyond that, run your own instance (the software is open source) and set `base_uri`. Each dataset
keeps its own licence and attribution (SRTM: NASA/USGS, public domain; EU-DEM: the EU's Copernicus
programme; GEBCO: the GEBCO Compilation Group; ASTER GDEM: NASA/METI...), which Open Topo Data's
datasets pages state: credit the dataset you use.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Open Topo Data or the producers of the elevation datasets it serves. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
