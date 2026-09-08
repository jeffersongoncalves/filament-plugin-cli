<?php

namespace App\Support;

use JeffersonGoncalves\LaravelZero\PackageScaffold\Namespaces;
use JeffersonGoncalves\LaravelZero\PackageScaffold\Scaffold as SharedScaffold;

/**
 * The generic templates live in jeffersongoncalves/laravel-zero-package-scaffold,
 * shared with laravel-package-cli. Only the Filament-specific pieces stay here,
 * so call sites keep using Scaffold::license() unchanged.
 */
class Scaffold extends SharedScaffold
{
    /**
     * php floor, orchestra/testbench range and the CI Laravel version to
     * test, per Filament major. Branch NAMING is always sequential
     * (1.x, 2.x, 3.x, ...) and independent of which Filament major each
     * branch targets — that mapping is chosen at scaffold time via
     * --filament-version/--to-filament-version, not hardcoded. See
     * jeffersongoncalves' own filament-plugin-creator skill for where these
     * floors come from.
     */
    public const FILAMENT_VERSIONS = [
        3 => ['filament' => '^3.0', 'php' => '^8.1', 'testbench' => '^8.0|^9.0', 'ciLaravel' => '12.*'],
        4 => ['filament' => '^4.0', 'php' => '^8.2', 'testbench' => '^9.0|^10.0', 'ciLaravel' => '12.*'],
        5 => ['filament' => '^5.0', 'php' => '^8.2', 'testbench' => '^10.0|^11.0', 'ciLaravel' => '13.*'],
    ];

    public const MIN_FILAMENT_VERSION = 3;

    public const MAX_FILAMENT_VERSION = 5;

    public static function branchName(int $index): string
    {
        return ($index + 1).'.x';
    }

    /**
     * The standard plugin namespace nests under a Filament segment:
     * filament-ban => Vendor\Filament\Ban, so the classes read
     * BanServiceProvider/BanPlugin rather than repeating the Filament prefix.
     * Casing per slug comes from the shared ~/.package/vendornamespace.json;
     * --namespace overrides the lot.
     */
    public static function rootNamespace(string $vendor, string $package): string
    {
        $name = Namespaces::segment((string) preg_replace('/^filament-/', '', $package));

        return Namespaces::segment($vendor).'\\Filament\\'.($name ?: 'Plugin');
    }

    public static function testsYml(string $branch, int $filamentMajor): string
    {
        $v = self::FILAMENT_VERSIONS[$filamentMajor];

        return <<<YAML
        name: Tests

        on:
          push:
            branches: [{$branch}]
          pull_request:
            branches: [{$branch}]

        jobs:
          test:
            runs-on: ubuntu-latest
            strategy:
              fail-fast: false
              matrix:
                php: [8.4]
                laravel: ['{$v['ciLaravel']}']
            steps:
              - uses: actions/checkout@11d5960a326750d5838078e36cf38b85af677262 # v4
              - uses: shivammathur/setup-php@b604ade2a87db23f8871b7182e69ec5e75effb45 # v2
                with:
                  php-version: \${{ matrix.php }}
                  coverage: none
              - run: composer require "laravel/framework:\${{ matrix.laravel }}" --no-interaction --no-update
              - run: composer update --prefer-dist --no-interaction
              - run: vendor/bin/pest

        YAML;
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array<string, string>  $extraRequire
     */
    public static function filamentComposerJson(string $vendor, string $package, string $namespace, string $serviceProvider, string $description, int $filamentMajor, string $author = '', string $email = '', array $keywords = [], array $extraRequire = []): array
    {
        $v = self::FILAMENT_VERSIONS[$filamentMajor];

        return [
            'name' => "$vendor/$package",
            'description' => $description,
            'keywords' => $keywords ?: ['laravel', 'filament', 'filament-plugin', $package],
            'homepage' => "https://github.com/$vendor/$package",
            'license' => 'MIT',
            'type' => 'library',
            'authors' => [['name' => $author, 'email' => $email, 'role' => 'Developer']],
            'require' => array_merge([
                'php' => $v['php'],
                'filament/filament' => $v['filament'],
                'spatie/laravel-package-tools' => '^1.16',
            ], $extraRequire),
            'require-dev' => [
                'orchestra/testbench' => $v['testbench'],
                'pestphp/pest' => '^3.0|^4.0',
                'larastan/larastan' => '^2.0|^3.0',
                'laravel/pint' => '^1.0',
            ],
            'autoload' => ['psr-4' => ["$namespace\\" => 'src/']],
            'autoload-dev' => ['psr-4' => ["$namespace\\Tests\\" => 'tests/']],
            'scripts' => [
                'test' => 'vendor/bin/pest',
                'test-coverage' => 'vendor/bin/pest --coverage',
                'format' => 'vendor/bin/pint',
                'analyse' => 'vendor/bin/phpstan analyse',
            ],
            'config' => [
                'sort-packages' => true,
                'allow-plugins' => ['pestphp/pest-plugin' => true],
            ],
            'extra' => [
                'laravel' => ['providers' => ["$namespace\\$serviceProvider"]],
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ];
    }
}
