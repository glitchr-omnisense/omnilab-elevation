# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Added

- Omnilab Elevation: the elevation of points and of a profile between two points from a digital
  elevation model (SRTM, ASTER, EU-DEM, Mapzen, ETOPO1, GEBCO...), through Open Topo Data's public
  API or a self-hosted instance, as Points with their elevation in metres, outside the contract's
  shapes; no key needed (100 locations a call, a call a second, 1,000 a day on the public instance).

### Release

- Requires `omnisense/omnilab` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnilab/elevation` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.
