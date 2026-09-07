<?php

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

it('rejects a filament-version out of range', function () {
    $this->artisan('branch', [
        'vendor-package' => 'acme/example-plugin',
        '--branch' => '2.x',
        '--filament-version' => '9',
        '--path' => sys_get_temp_dir(),
        '--dry-run' => true,
    ])->assertExitCode(1);
});
