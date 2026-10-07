<?php

namespace App\Support;

use GuzzleHttp\Client;
use Throwable;

/**
 * Read-only lookups against Packagist's public metadata (repo.packagist.org/p2).
 */
class Packagist
{
    public function __construct(private ?Client $client = null) {}

    public function hasVersion(string $package, string $version): bool
    {
        try {
            $response = ($this->client ??= new Client(['timeout' => 30]))
                ->get("https://repo.packagist.org/p2/{$package}.json");
            $data = json_decode((string) $response->getBody(), true);
        } catch (Throwable) {
            return false; // not indexed yet (404) or a transient error: the caller keeps polling
        }

        foreach ($data['packages'][$package] ?? [] as $release) {
            if (($release['version'] ?? null) === $version) {
                return true;
            }
        }

        return false;
    }
}
