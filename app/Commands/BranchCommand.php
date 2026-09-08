<?php

namespace App\Commands;

use App\Support\Scaffold;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class BranchCommand extends Command
{
    /**
     * Fully non-interactive, same rule as CreateCommand: no ->ask()/->confirm().
     *
     * @var string
     */
    protected $signature = 'branch
        {vendor-package : vendor/package, e.g. jeffersongoncalves/filament-settings}
        {--branch= : name of the branch to create (any name, e.g. 2.x), required}
        {--filament-version= : Filament major (3, 4 or 5) this branch targets, required}
        {--path= : existing plugin repo, required}
        {--from= : branch to branch off (default: current HEAD)}
        {--dry-run : print planned actions, write nothing}';

    protected $description = 'Add a new Filament-version branch to an existing plugin repo.';

    public function handle(): int
    {
        $vendorPackage = (string) $this->argument('vendor-package');
        if (! str_contains($vendorPackage, '/')) {
            $this->components->error("Expected vendor/package, got: {$vendorPackage}");

            return self::FAILURE;
        }
        [$vendor, $package] = explode('/', $vendorPackage, 2);

        $branch = (string) $this->option('branch');
        $dir = $this->option('path');
        $dryRun = (bool) $this->option('dry-run');

        if ($branch === '') {
            $this->components->error('--branch=NAME is required');

            return self::FAILURE;
        }
        $version = (int) $this->option('filament-version');
        if (! $this->option('filament-version') || $version < Scaffold::MIN_FILAMENT_VERSION || $version > Scaffold::MAX_FILAMENT_VERSION) {
            $this->components->error('--filament-version must be between '.Scaffold::MIN_FILAMENT_VERSION.' and '.Scaffold::MAX_FILAMENT_VERSION);

            return self::FAILURE;
        }
        if (! $dir || ! File::isDirectory($dir.'/.git')) {
            $this->components->error('--path=DIR is required and must be an existing git repo');

            return self::FAILURE;
        }

        $namespace = Scaffold::studly($vendor).'\\'.Scaffold::studly($package);
        $serviceProvider = Scaffold::studly($package).'ServiceProvider';

        $git = [];
        $gitRun = function (string $cmd) use ($dir, $dryRun, &$git): void {
            if ($dryRun) {
                $git[] = "(dry-run) git {$cmd}";

                return;
            }
            $result = Process::path($dir)->run("git {$cmd}");
            $git[] = $result->failed()
                ? "git {$cmd} FAILED: ".trim($result->errorOutput())
                : "git {$cmd} ok";
        };

        if ($from = $this->option('from')) {
            $gitRun("checkout -q {$from}");
        }
        $gitRun("checkout -q -b {$branch}");

        $composerPath = $dir.'/composer.json';
        $composerJson = Scaffold::filamentComposerJson($vendor, $package, $namespace, $serviceProvider, '', $version);

        // A branch is a version bump, not a fresh scaffold: keep whatever the source
        // branch already declared (description, keywords, authors, extra deps) and
        // patch only the constraints that the Filament major actually moves.
        if (File::exists($composerPath) && is_array($existing = json_decode(File::get($composerPath), true))) {
            $composerJson = array_replace_recursive($existing, [
                'require' => array_intersect_key($composerJson['require'], ['php' => 1, 'filament/filament' => 1]),
                'require-dev' => array_intersect_key($composerJson['require-dev'], ['orchestra/testbench' => 1]),
            ]);
        }
        $files = [];
        if ($dryRun) {
            $files[] = 'write (force, dry-run) composer.json';
            $files[] = 'write (force, dry-run) .github/workflows/tests.yml';
        } else {
            File::put($composerPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $files[] = 'write (force) composer.json';
            File::ensureDirectoryExists($dir.'/.github/workflows');
            File::put($dir.'/.github/workflows/tests.yml', Scaffold::testsYml($branch, $version));
            $files[] = 'write (force) .github/workflows/tests.yml';
            File::delete($dir.'/composer.lock');
        }

        $gitRun('add .');
        $gitRun('commit -q -m "feat: upgrade to Filament v'.$version.' compatibility"');

        $this->components->info(($dryRun ? '[dry-run] ' : '')."Branch {$branch} added to {$vendor}/{$package} at {$dir}");
        foreach ($files as $line) {
            $this->line("  {$line}");
        }
        foreach ($git as $line) {
            $this->line("  git: {$line}");
        }
        $this->newLine();
        $this->components->info('Next steps:');
        foreach ([
            'rm -f composer.lock && composer install',
            'vendor/bin/pest',
            "git push -u origin {$branch}",
        ] as $step) {
            $this->line("  - {$step}");
        }

        return self::SUCCESS;
    }
}
