<?php

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

it('rejects a vendor-package without a slash', function () {
    $this->artisan('create', [
        'vendor-package' => 'not-a-vendor-package',
        '--dry-run' => true,
    ])->assertExitCode(1);
});
