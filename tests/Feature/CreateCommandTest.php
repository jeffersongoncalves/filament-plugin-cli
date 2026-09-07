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

it('rejects an unknown branch', function () {
    $dir = sys_get_temp_dir().'/filament-plugin-cli-test-'.uniqid();

    $this->artisan('create', [
        'vendor-package' => 'acme/example-plugin',
        '--branch' => '4.x',
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
