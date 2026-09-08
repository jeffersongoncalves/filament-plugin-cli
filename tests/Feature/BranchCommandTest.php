<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('requires an existing git repo at --path', function () {
    $this->artisan('branch', [
        'vendor-package' => 'acme/example-plugin',
        '--branch' => '2.x',
        '--filament-version' => '4',
        '--path' => sys_get_temp_dir().'/does-not-exist-'.uniqid(),
        '--dry-run' => true,
    ])->assertExitCode(1);
});

it('requires --filament-version', function () {
    $this->artisan('branch', [
        'vendor-package' => 'acme/example-plugin',
        '--branch' => '2.x',
        '--path' => sys_get_temp_dir(),
        '--dry-run' => true,
    ])->assertExitCode(1);
});

it('keeps the existing composer.json fields and only bumps the version constraints', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-branch-'.uniqid();
    File::ensureDirectoryExists($dir);
    Process::path($dir)->run('git init -q');
    File::put($dir.'/composer.json', json_encode([
        'name' => 'jeffersongoncalves/filament-ban',
        'description' => 'Ban and unban any Eloquent model from Filament tables.',
        'keywords' => ['laravel', 'filament', 'ban'],
        'authors' => [['name' => 'Jefferson Gonçalves', 'email' => 'gerson.simao.92@gmail.com', 'role' => 'Developer']],
        'require' => ['php' => '^8.2', 'filament/filament' => '^3.2', 'cybercog/laravel-ban' => '^4.10'],
        'require-dev' => ['orchestra/testbench' => '^8.0|^9.0'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $this->artisan('branch', [
        'vendor-package' => 'jeffersongoncalves/filament-ban',
        '--branch' => '3.x',
        '--filament-version' => '5',
        '--path' => $dir,
    ])->assertExitCode(0);

    $composer = json_decode(File::get($dir.'/composer.json'), true);

    expect($composer['description'])->toBe('Ban and unban any Eloquent model from Filament tables.')
        ->and($composer['keywords'])->toBe(['laravel', 'filament', 'ban'])
        ->and($composer['authors'][0]['name'])->toBe('Jefferson Gonçalves')
        ->and($composer['require'])->toHaveKey('cybercog/laravel-ban')
        ->and($composer['require']['filament/filament'])->not->toBe('^3.2')
        ->and($composer['require-dev']['orchestra/testbench'])->not->toBe('^8.0|^9.0');

    File::deleteDirectory($dir);
});

it('rejects a filament-version out of range', function () {
    $this->artisan('branch', [
        'vendor-package' => 'acme/example-plugin',
        '--branch' => '2.x',
        '--filament-version' => '9',
        '--path' => sys_get_temp_dir(),
        '--dry-run' => true,
    ])->assertExitCode(1);
});
