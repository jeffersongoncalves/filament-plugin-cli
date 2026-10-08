<?php

use App\Support\Packagist;
use App\Support\VersionBranches;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

function fakeRepo(): string
{
    $dir = sys_get_temp_dir().'/filament-plugin-cli-'.uniqid();
    File::ensureDirectoryExists($dir.'/.git');
    File::put($dir.'/composer.json', (string) json_encode(['require' => ['acme/laravel-thing' => '^1.0']]));

    return $dir;
}

/**
 * Patterns use * between words: Symfony renders array commands with the resolved executable path on Windows
 * and quotes every argument on Linux.
 *
 * Process fake answering the read-only git/gh calls the commands make.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeGit(array $overrides = []): void
{
    // Overrides first: the fake uses the first matching pattern, and '*' must stay last.
    Process::fake($overrides + [
        '*git*status*' => '',
        '*git*branch*' => "main\n2.x\n1.x\n3.x\n",
        '*git*rev-parse*' => "3.x\n",
        '*git*show*' => (string) json_encode(['description' => 'A plugin', 'require' => ['filament/filament' => '^5.3']]),
        '*git*remote*get-url*' => Process::result(exitCode: 2),
        '*gh*release*view*' => Process::result(exitCode: 1),
        '*' => '',
    ]);
}

function commandLine(PendingProcess $process): string
{
    return is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
}

function assertRan(string $needle): void
{
    Process::assertRan(fn (PendingProcess $p) => str_contains(commandLine($p), $needle));
}

function assertNotRan(string $needle): void
{
    Process::assertNotRan(fn (PendingProcess $p) => str_contains(commandLine($p), $needle));
}

it('sorts version branches numerically and ignores the rest', function () {
    expect(VersionBranches::sort(['main', '10.x', '2.x', ' 1.x ', 'feature/x']))->toBe(['1.x', '2.x', '10.x'])
        ->and(VersionBranches::lowestVersion('^2.3|^3.0'))->toBe('2.3.0')
        ->and(VersionBranches::lowestVersion('^1'))->toBe('1.0.0');
});

it('verifies every branch and goes back to the original one', function () {
    fakeGit();
    $dir = fakeRepo();

    $this->artisan('verify', ['--path' => $dir])->assertExitCode(0);

    assertRan('git checkout -q 1.x');
    assertRan('git checkout -q 2.x');
    assertRan('vendor/bin/pint --test');
    assertRan('phpstan clear-result-cache');
    assertRan('vendor/bin/pest');
    Process::assertRan(fn (PendingProcess $p) => commandLine($p) === 'git checkout -q 3.x');

    File::deleteDirectory($dir);
});

it('fails when a check fails on a branch', function () {
    fakeGit(['*phpstan*analyse*' => Process::result(exitCode: 1)]);
    $dir = fakeRepo();

    $this->artisan('verify', ['--path' => $dir, '--branches' => '2.x'])->assertExitCode(1);

    File::deleteDirectory($dir);
});

it('commits Pint --fix changes on their own branch so they do not leak into the next one', function () {
    fakeGit(['*git*diff*--name-only*' => Process::sequence(['', "src/Plugin.php\n", '', ''])]);
    $dir = fakeRepo();

    $this->artisan('verify', ['--path' => $dir, '--branches' => '1.x,2.x', '--fix' => true])->assertExitCode(0);

    assertRan('vendor/bin/pint');
    assertNotRan('pint --test');
    assertRan('git add -- src/Plugin.php');
    Process::assertRanTimes(fn (PendingProcess $p) => str_contains(commandLine($p), 'git commit -q -m style: apply Pint'), 1);

    File::deleteDirectory($dir);
});

it('refuses to run on a dirty working tree', function () {
    fakeGit(['*git*status*' => " M src/Plugin.php\n"]);
    $dir = fakeRepo();

    $this->artisan('verify', ['--path' => $dir])->assertExitCode(1);
    assertNotRan('git checkout');

    File::deleteDirectory($dir);
});

it('installs --local packages through a throwaway composer file', function () {
    fakeGit();
    $dir = fakeRepo();

    $this->artisan('verify', ['--path' => $dir, '--branches' => '1.x', '--local' => ['acme/laravel-thing='.sys_get_temp_dir()]])
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $p) => ($p->environment['COMPOSER'] ?? null) === 'composer.verify.json');
    expect(File::exists($dir.'/composer.verify.json'))->toBeFalse()
        ->and(File::get($dir.'/composer.json'))->not->toContain('repositories');

    File::deleteDirectory($dir);
});

it('publishes: repo, branches, default branch, releases with only the highest latest, Packagist', function () {
    fakeGit();
    $dir = fakeRepo();

    $this->artisan('publish', ['vendor-package' => 'acme/filament-thing', '--path' => $dir, '--topics' => 'filament,laravel'])
        ->assertExitCode(0);

    assertRan('gh repo create acme/filament-thing --public --source . --remote origin --description A plugin');
    assertRan('--add-topic filament --add-topic laravel');
    assertNotRan('--homepage');
    assertRan('git push -u origin 1.x');
    assertRan('git push -u origin 3.x');
    assertNotRan('git push -u origin 1.x 2.x');
    assertRan('--default-branch 3.x');
    assertRan('gh release create 1.0.0 --repo acme/filament-thing --target 1.x --title 1.0.0 --latest=false');
    assertRan('gh release create 3.0.0 --repo acme/filament-thing --target 3.x --title 3.0.0 --latest --notes First release for Filament 5.x.');
    assertRan('packagist submit acme/filament-thing');

    File::deleteDirectory($dir);
});

it('skips what already exists when re-run', function () {
    fakeGit(['*git*remote*get-url*' => 'git@github.com:acme/filament-thing.git', '*gh*release*view*' => '{}']);
    $dir = fakeRepo();

    $this->artisan('publish', ['vendor-package' => 'acme/filament-thing', '--path' => $dir, '--no-packagist' => true])->assertExitCode(0);

    assertNotRan('gh repo create');
    assertNotRan('gh release create');
    assertNotRan('packagist submit');

    File::deleteDirectory($dir);
});

it('waits for a dependency on Packagist before releasing', function () {
    fakeGit();
    Sleep::fake();
    $dir = fakeRepo();

    $packagist = Mockery::mock(Packagist::class);
    $packagist->shouldReceive('hasVersion')->with('acme/laravel-thing', '1.0.0')->andReturn(false, false, true);
    app()->instance(Packagist::class, $packagist);

    $this->artisan('publish', ['vendor-package' => 'acme/filament-thing', '--path' => $dir, '--wait-for' => ['acme/laravel-thing:1.0.0']])
        ->assertExitCode(0);

    Sleep::assertSleptTimes(2);
    assertRan('gh release create 3.0.0');
    File::deleteDirectory($dir);
});

it('stops before releasing when the dependency never shows up', function () {
    fakeGit();
    Sleep::fake();
    $dir = fakeRepo();

    $packagist = Mockery::mock(Packagist::class);
    $packagist->shouldReceive('hasVersion')->andReturn(false);
    app()->instance(Packagist::class, $packagist);

    $this->artisan('publish', ['vendor-package' => 'acme/filament-thing', '--path' => $dir, '--wait-for' => ['acme/laravel-thing:1.0.0'], '--wait-timeout' => 0])
        ->assertExitCode(1);

    assertNotRan('gh release create');
    File::deleteDirectory($dir);
});

it('prints the commands without running them on --dry-run', function () {
    fakeGit();
    $dir = fakeRepo();

    $this->artisan('publish', ['vendor-package' => 'acme/filament-thing', '--path' => $dir, '--dry-run' => true])
        ->expectsOutputToContain('(dry-run) gh release create 3.0.0')
        ->assertExitCode(0);

    assertNotRan('gh repo create');
    assertNotRan('git push');
    File::deleteDirectory($dir);
});
