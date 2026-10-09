<?php

use App\Support\Packagist;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function packagistWith(array $versions): Packagist
{
    $body = json_encode(['packages' => ['acme/thing' => array_map(fn ($v) => ['version' => $v], $versions)]]);

    return new Packagist(new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $body)]))]));
}

it('finds a version whether or not the tag has a v prefix', function () {
    expect(packagistWith(['v1.2.0', 'v1.1.1'])->hasVersion('acme/thing', '1.2.0'))->toBeTrue()
        ->and(packagistWith(['v1.2.0'])->hasVersion('acme/thing', 'v1.2.0'))->toBeTrue()
        ->and(packagistWith(['3.0.0'])->hasVersion('acme/thing', 'v3.0.0'))->toBeTrue()
        ->and(packagistWith(['1.1.1'])->hasVersion('acme/thing', '1.2.0'))->toBeFalse();
});

it('keeps polling when the package is not indexed yet', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(404)]))]);

    expect((new Packagist($client))->hasVersion('acme/thing', '1.0.0'))->toBeFalse();
});
