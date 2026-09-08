<?php

use Illuminate\Support\Facades\File;

it('scaffolds a single branch in dry-run mode without touching disk', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        'description' => 'Example plugin',
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(is_dir($dir))->toBeFalse();
});

it('scaffolds all branches in dry-run mode without touching disk', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        '--all-branches' => true,
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(is_dir($dir))->toBeFalse();
});

it('scaffolds a custom filament-version range with --all-branches', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        '--all-branches' => true,
        '--filament-version' => '4',
        '--to-filament-version' => '5',
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(0);
});

it('accepts a single starting branch on a later filament-version', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        '--filament-version' => '5',
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(0);
});

it('rejects a filament-version out of range', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        '--filament-version' => '9',
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(1);
});

it('rejects a to-filament-version lower than filament-version', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        '--all-branches' => true,
        '--filament-version' => '5',
        '--to-filament-version' => '3',
        '--path' => $dir,
        '--dry-run' => true,
    ])->assertExitCode(1);
});

it('defaults to the nested Vendor\\Filament\\Name namespace', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'jeffersongoncalves/filament-cep-field',
        '--path' => $dir,
        '--filament-version' => '5',
        '--no-git' => true,
    ])->assertExitCode(0);

    $composer = json_decode(file_get_contents($dir.'/composer.json'), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Jeffersongoncalves\\Filament\\CepField\\')
        ->and($composer['extra']['laravel']['providers'])->toBe(['Jeffersongoncalves\\Filament\\CepField\\CepFieldServiceProvider'])
        ->and(is_file($dir.'/src/CepFieldServiceProvider.php'))->toBeTrue()
        ->and(is_file($dir.'/src/CepFieldPlugin.php'))->toBeTrue()
        ->and(is_file($dir.'/config/filament-cep-field.php'))->toBeTrue();

    File::deleteDirectory($dir);
});

it('honours --namespace, --keywords and --require', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'jeffersongoncalves/filament-ban',
        '--path' => $dir,
        '--namespace' => 'JeffersonGoncalves\\Filament\\Ban',
        '--keywords' => 'laravel, filament, ban, bannable',
        '--require' => 'cybercog/laravel-ban:^4.10',
        '--author' => 'Jefferson Gonçalves',
        '--email' => 'gerson.simao.92@gmail.com',
        '--filament-version' => '5',
        '--no-git' => true,
    ])->assertExitCode(0);

    $composer = json_decode(file_get_contents($dir.'/composer.json'), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('JeffersonGoncalves\\Filament\\Ban\\')
        ->and($composer['extra']['laravel']['providers'])->toBe(['JeffersonGoncalves\\Filament\\Ban\\BanServiceProvider'])
        ->and($composer['keywords'])->toBe(['laravel', 'filament', 'ban', 'bannable'])
        ->and($composer['type'])->toBe('library')
        ->and($composer['authors'])->toBe([['name' => 'Jefferson Gonçalves', 'email' => 'gerson.simao.92@gmail.com', 'role' => 'Developer']])
        ->and($composer['require'])->toHaveKey('cybercog/laravel-ban')
        ->and(is_file($dir.'/src/BanServiceProvider.php'))->toBeTrue()
        ->and(is_file($dir.'/src/BanPlugin.php'))->toBeTrue();

    File::deleteDirectory($dir);
});

it('rejects a vendor-package without a slash', function () {
    $this->artisan('create', [
        'vendor-package' => 'not-a-vendor-package',
        '--dry-run' => true,
    ])->assertExitCode(1);
});
