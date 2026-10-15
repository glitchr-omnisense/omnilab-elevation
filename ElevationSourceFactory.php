<?php

namespace Omnilab\Elevation;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Open Topo Data: the elevation of points and profiles from a digital
 * elevation model - no key.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     dataset: srtm30m                 # or a list, the first with a value answering: [eudem25m, srtm30m, gebco2020]
 *     interpolation: bilinear          # nearest, bilinear, cubic
 *     throttle: 1                      # seconds between two calls: the public instance takes one a second
 *     base_uri: 'https://api.opentopodata.org/v1/'   # or a self-hosted instance
 */
final class ElevationSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'elevation',
            'omnilab.required_options' => [],
            'dataset' => 'srtm30m',
            'interpolation' => 'bilinear',
            'throttle' => 1.0,
            'base_uri' => ElevationSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        $datasets = $c->get('dataset');

        return new ElevationSource(
            $this->http ?? HttpClient::create(),
            array_values(array_filter(array_map('trim', \is_array($datasets) ? $datasets : explode(',', (string) $datasets)))),
            (string) $c->get('interpolation'),
            (string) $c->get('base_uri'),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
