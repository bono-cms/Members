<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */
namespace Members\Service;

/**
 * Extracts latitude and longitude from map URLs.
 *
 * Supports the most common map providers used in Uzbekistan and
 * the CIS region: Google Maps, Yandex Maps, OpenStreetMap and
 * 2GIS. Each provider has its own URL shape, so the parser tries
 * them in order and returns the first match.
 *
 * The parser is intentionally permissive: it accepts URLs with
 * extra query parameters, fragments, and URL-encoded coordinates.
 * If nothing matches, it returns null so the caller can fall
 * back to manual entry.
 *
 * This class has no external dependencies and makes no network
 * requests — it only parses strings.
 *
 * Known limitation: short links (maps.app.goo.gl, yandex.uz/s/...,
 * 2gis.uz/...) are NOT resolved — they require an HTTP redirect.
 * The user must paste the full URL or enter coordinates manually.
 */
final class LocationParser
{
    /**
     * Parses a map URL and returns the coordinates.
     *
     * @param  string $url Raw URL pasted by the user
     * @return array|null  ['lat' => float, 'lng' => float] or null
     */
    public function parse($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Quick sanity check: must look like a URL
        if (!preg_match('~^https?://~i', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || !isset($parts['host'])) {
            return null;
        }

        $host     = strtolower($parts['host']);
        $query    = isset($parts['query']) ? $parts['query'] : '';
        // parse_url() strips the leading "#" from the fragment
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';
        $path     = isset($parts['path']) ? $parts['path'] : '';

        parse_str($query, $queryParams);

        // --- Google Maps ---
        // Shapes:
        //   maps.google.com/?q=LAT,LNG
        //   maps.google.com/maps/@LAT,LNG,ZOOM,/data=!3m1!4b1
        //   google.com/maps/place/.../@LAT,LNG,ZOOM,...
        //   google.com/maps/place/.../!3dLAT!4dLNG (modern)
        if (preg_match('~(^|\.)google\.(com|ru|uz|co\..+)$~', $host)) {
            if (!empty($queryParams['q']) && ($coords = $this->extractLatLonFromPair($queryParams['q']))) {
                return $coords;
            }

            if (preg_match('~@(-?\d+\.\d+),(-?\d+\.\d+)~', $path . $fragment, $matches)) {
                return $this->normalize((float) $matches[1], (float) $matches[2]);
            }

            if (preg_match('~!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)~', $path . $fragment, $matches)) {
                return $this->normalize((float) $matches[1], (float) $matches[2]);
            }
        }

        // --- Yandex Maps ---
        if (preg_match('~(^|\.)yandex\.(ru|uz|com|kz)$~', $host)) {
            if (!empty($queryParams['pt']) && ($coords = $this->extractLonLatFromPair($queryParams['pt']))) {
                return $coords;
            }

            if (!empty($queryParams['ll']) && ($coords = $this->extractLonLatFromPair($queryParams['ll']))) {
                return $coords;
            }
        }

        // --- OpenStreetMap ---
        if (preg_match('~(^|\.)openstreetmap\.org$~', $host)) {
            if (!empty($queryParams['mlat']) && !empty($queryParams['mlon'])) {
                return $this->normalize(
                    $this->toFloat($queryParams['mlat']),
                    $this->toFloat($queryParams['mlon'])
                );
            }

            // Fragment does NOT include the leading "#"
            if (preg_match('~map=\d+/(-?\d+\.\d+)/(-?\d+\.\d+)~', $fragment, $matches)) {
                return $this->normalize((float) $matches[1], (float) $matches[2]);
            }

            if (preg_match('~map=\d+/(-?\d+\.\d+)/(-?\d+\.\d+)~', $query, $matches)) {
                return $this->normalize((float) $matches[1], (float) $matches[2]);
            }
        }

        // --- 2GIS ---
        if (preg_match('~(^|\.)2gis\.(ru|uz)$~', $host)) {
            if (!empty($queryParams['m']) && ($coords = $this->extractLonLatFromPair($queryParams['m']))) {
                return $coords;
            }

            if (preg_match('~[/-](-?\d+\.\d+)[,/](-?\d+\.\d+)(?:/|\?|$)~', $path, $matches)) {
                return $this->normalize((float) $matches[2], (float) $matches[1]);
            }
        }

        // --- Generic fallback: look for LAT,LNG anywhere in the URL ---
        if (preg_match('~(-?\d+\.\d+)[,/ ](-?\d+\.\d+)~', $url, $matches)) {
            $a = (float) $matches[1];
            $b = (float) $matches[2];

            if ($this->isLat($a) && $this->isLng($b)) {
                return $this->normalize($a, $b);
            }

            if ($this->isLat($b) && $this->isLng($a)) {
                return $this->normalize($b, $a);
            }
        }

        return null;
    }

    /**
     * Extracts [lat, lng] from a "LAT,LNG" string.
     *
     * @param  string $pair
     * @return array|null
     */
    private function extractLatLonFromPair($pair)
    {
        if (!preg_match('~(-?\d+\.\d+)[,\s]+(-?\d+\.\d+)~', (string) $pair, $matches)) {
            return null;
        }

        $a = (float) $matches[1];
        $b = (float) $matches[2];

        if ($this->isLat($a) && $this->isLng($b)) {
            return $this->normalize($a, $b);
        }

        return null;
    }

    /**
     * Extracts [lat, lng] from a "LNG,LAT" string (Yandex, 2GIS order).
     *
     * @param  string $pair
     * @return array|null
     */
    private function extractLonLatFromPair($pair)
    {
        if (!preg_match('~(-?\d+\.\d+)[,\s]+(-?\d+\.\d+)~', (string) $pair, $matches)) {
            return null;
        }

        $a = (float) $matches[1];
        $b = (float) $matches[2];

        if ($this->isLng($a) && $this->isLat($b)) {
            return $this->normalize($b, $a);
        }

        return null;
    }

    /**
     * Wraps the coordinates in the canonical shape.
     *
     * @param  float $lat
     * @param  float $lng
     * @return array
     */
    private function normalize($lat, $lng)
    {
        return [
            'lat' => round((float) $lat, 8),
            'lng' => round((float) $lng, 8),
        ];
    }

    /**
     * Coerces a value to float, returning 0.0 on failure.
     *
     * @param  mixed $value
     * @return float
     */
    private function toFloat($value)
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Checks whether a value is within the latitude range.
     *
     * @param  float $value
     * @return bool
     */
    private function isLat($value)
    {
        return $value >= -90.0 && $value <= 90.0;
    }

    /**
     * Checks whether a value is within the longitude range.
     *
     * @param  float $value
     * @return bool
     */
    private function isLng($value)
    {
        return $value >= -180.0 && $value <= 180.0;
    }
}