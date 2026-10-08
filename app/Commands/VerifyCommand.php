<?php

namespace App\Commands;

use App\Support\VersionBranches;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class VerifyCommand extends Command
{
    /**
     * Fully non-interactive, same rule as the other commands.
     *
     * @var string
     */
    protected $signature = 'verify
        {--path= : plugin repo (default: current directory)}
        {--branches= : comma-separated branches to check (default: every N.x branch)}
        {--local=* : unpublished dependency installed from a folder, as vendor/package=PATH (repeatable)}
        {--fix : run Pint in fix mode instead of --test}';

    protected $description = 'Run Pint, PHPStan (cleared cache) and Pest on every version branch of a plugin.';

    private const STEPS = ['composer', 'pint', 'phpstan', 'pest'];

    public function handle(): int
    {
        $dir = (string) ($this->option('path') ?: getcwd());
        if (! File::isDirectory($dir.'/.git')) {
            $this->components->error("Not a git repo: {$dir}");

            return self::FAILURE;
        }

        if (trim(Process::path($dir)->run(['git', 'status', '--porcelain'])->output()) !== '') {
            $this->components->error('The working tree has uncommitted changes — commit or stash them first (verify checks out each branch).');

            return self::FAILURE;
        }

        $branches = $this->option('branches')
            ? VersionBranches::sort(explode(',', (string) $this->option('branches')))
            : VersionBranches::in($dir);
        if ($branches === []) {
            $this->components->error('No N.x branches found.');

            return self::FAILURE;
        }

        $locals = $this->locals();
        if ($locals === null) {
            return self::FAILURE;
        }

        $original = trim(Process::path($dir)->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->output());
        $rows = [];
        $ok = true;

        try {
            foreach ($branches as $branch) {
                $results = $this->verifyBranch($dir, $branch, $locals);
                $ok = $ok && ! in_array(false, $results, true);
                $rows[] = [$branch, ...array_map(fn (?bool $r) => match ($r) {
                    true => '<fg=green>ok</>',
                    false => '<fg=red>FAIL</>',
                    null => '<fg=gray>skipped</>',
                }, $results)];
            }
        } finally {
            if ($original !== '') {
                Process::path($dir)->run(['git', 'checkout', '-q', $original]);
            }
        }

        $this->table(['Branch', 'Composer', 'Pint', 'PHPStan', 'Pest'], $rows);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, string>  $locals
     * @return array<string, bool|null> step => passed (null = not run because an earlier step failed)
     */
    private function verifyBranch(string $dir, string $branch, array $locals): array
    {
        $this->components->info("Verifying {$branch}");
        $results = array_fill_keys(self::STEPS, null);

        if (Process::path($dir)->run(['git', 'checkout', '-q', $branch])->failed()) {
            $this->components->error("Could not check out {$branch}");

            return array_merge($results, ['composer' => false]);
        }

        $env = $locals === [] ? [] : ['COMPOSER' => $this->writeVerifyComposer($dir, $locals)];
        $php = PHP_BINARY;

        // composer is a .bat shim on Windows, so it goes through the shell (no user input in this line).
        $results['composer'] = $this->step($dir, 'composer update --no-interaction --no-progress --quiet', $env);

        if ($results['composer']) {
            // Testbench compiles views into vendor/: after switching branches they belong to another Filament.
            File::delete(File::glob($dir.'/vendor/orchestra/testbench-core/laravel/storage/framework/views/*.php') ?: []);

            if ($this->option('fix')) {
                $before = $this->changedFiles($dir);
                $results['pint'] = $this->step($dir, [$php, 'vendor/bin/pint'], $env);
                $this->commitPintFixes($dir, $branch, array_values(array_diff($this->changedFiles($dir), $before)));
            } else {
                $results['pint'] = $this->step($dir, [$php, 'vendor/bin/pint', '--test'], $env);
            }
            Process::path($dir)->env($env)->run([$php, 'vendor/bin/phpstan', 'clear-result-cache', '-q']);
            $results['phpstan'] = $this->step($dir, [$php, 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G'], $env);
            $results['pest'] = $this->step($dir, [$php, 'vendor/bin/pest'], $env);
        }

        if ($env !== []) {
            File::delete([$dir.'/composer.verify.json', $dir.'/composer.verify.lock']);
        }

        return $results;
    }

    /**
     * @return list<string> tracked files with uncommitted changes
     */
    private function changedFiles(string $dir): array
    {
        return array_values(array_filter(explode("\n", trim(Process::path($dir)->run(['git', 'diff', '--name-only'])->output()))));
    }

    /**
     * Pint --fix rewrites files on this branch: commit them here, otherwise the next checkout would carry
     * them over to the other branches.
     *
     * @param  list<string>  $files
     */
    private function commitPintFixes(string $dir, string $branch, array $files): void
    {
        if ($files === []) {
            return;
        }

        Process::path($dir)->run(['git', 'add', '--', ...$files]);
        Process::path($dir)->run(['git', 'commit', '-q', '-m', 'style: apply Pint']);
        $this->components->info('Committed Pint fixes on '.$branch.' ('.count($files).' files, not pushed)');
    }

    /**
     * @param  string|list<string>  $command
     * @param  array<string, string>  $env
     */
    private function step(string $dir, string|array $command, array $env): bool
    {
        $result = Process::path($dir)->env($env)->timeout(1800)->run($command);

        if ($result->failed()) {
            $this->line(trim($result->output()."\n".$result->errorOutput()));
        }

        return $result->successful();
    }

    /**
     * Copy of the branch's composer.json with path repositories for the --local packages, used through
     * COMPOSER=composer.verify.json so the tracked composer.json is never touched.
     *
     * @param  array<string, string>  $locals
     */
    private function writeVerifyComposer(string $dir, array $locals): string
    {
        $composer = json_decode(File::get($dir.'/composer.json'), true) ?: [];
        $composer['repositories'] = [];

        foreach ($locals as $package => $path) {
            $constraint = (string) ($composer['require'][$package] ?? $composer['require-dev'][$package] ?? '^1.0');
            $composer['repositories'][] = [
                'type' => 'path',
                'url' => str_replace('\\', '/', $path),
                'options' => ['symlink' => false, 'versions' => [$package => VersionBranches::lowestVersion($constraint)]],
            ];
        }

        File::put($dir.'/composer.verify.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 'composer.verify.json';
    }

    /**
     * @return array<string, string>|null package => path, or null after reporting a malformed option
     */
    private function locals(): ?array
    {
        $locals = [];

        foreach ((array) $this->option('local') as $spec) {
            if (! preg_match('#^([\w.-]+/[\w.-]+)=(.+)$#', (string) $spec, $m) || ! File::isDirectory($m[2])) {
                $this->components->error("--local expects vendor/package=EXISTING_PATH, got: {$spec}");

                return null;
            }
            $locals[$m[1]] = $m[2];
        }

        return $locals;
    }
}
