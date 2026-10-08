<?php

namespace App\Commands;

use App\Support\Packagist;
use App\Support\VersionBranches;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use LaravelZero\Framework\Commands\Command;

class PublishCommand extends Command
{
    /**
     * Fully non-interactive, same rule as the other commands. Safe to re-run: existing repo, pushed
     * branches and existing releases are left as they are.
     *
     * @var string
     */
    protected $signature = 'publish
        {vendor-package : vendor/package, e.g. jeffersongoncalves/filament-settings}
        {--path= : plugin repo (default: current directory)}
        {--description= : GitHub description (default: composer.json description of the highest branch)}
        {--topics= : comma-separated GitHub topics}
        {--wait-for=* : vendor/package:version that must be on Packagist before releasing (repeatable)}
        {--wait-timeout=900 : seconds to wait for each --wait-for package}
        {--notes= : release notes body (default: "First release for Filament N.x.")}
        {--private : create a private repository}
        {--no-packagist : skip submitting to Packagist}
        {--dry-run : print the commands, run nothing}';

    protected $description = 'Create the GitHub repo, push every N.x branch, cut the first releases (only the highest one --latest) and submit to Packagist.';

    private bool $dryRun = false;

    public function handle(Packagist $packagist): int
    {
        $package = (string) $this->argument('vendor-package');
        if (! preg_match('#^[\w.-]+/[\w.-]+$#', $package)) {
            $this->components->error("Expected vendor/package, got: {$package}");

            return self::FAILURE;
        }

        $dir = (string) ($this->option('path') ?: getcwd());
        $this->dryRun = (bool) $this->option('dry-run');

        if (! File::isDirectory($dir.'/.git')) {
            $this->components->error("Not a git repo: {$dir}");

            return self::FAILURE;
        }

        $branches = VersionBranches::in($dir);
        if ($branches === []) {
            $this->components->error('No N.x branches found — scaffold them with `create` / `branch` first.');

            return self::FAILURE;
        }
        $highest = $branches[array_key_last($branches)];

        $steps = [
            fn () => $this->ensureRepo($dir, $package, $highest),
            fn () => $this->pushBranches($dir, $branches),
            fn () => $this->exec($dir, ['gh', 'repo', 'edit', $package, '--default-branch', $highest]),
            fn () => $this->waitForDependencies($packagist),
            fn () => $this->release($dir, $package, $branches, $highest),
            fn () => $this->option('no-packagist') || $this->exec($dir, 'packagist submit '.$package),
        ];

        foreach ($steps as $step) {
            if (! $step()) {
                return self::FAILURE;
            }
        }

        $this->components->info(($this->dryRun ? '[dry-run] ' : '')."{$package} published: ".implode(', ', $branches)." ({$highest} is latest).");
        // GitHub's API can't set the social preview image; it has to be uploaded in Settings > Social preview.
        $this->components->warn("Upload the banner as the social preview: https://github.com/{$package}/settings");

        return self::SUCCESS;
    }

    private function ensureRepo(string $dir, string $package, string $highest): bool
    {
        if (! $this->dryRun && Process::path($dir)->run(['git', 'remote', 'get-url', 'origin'])->successful()) {
            $this->components->info('Remote origin already set — skipping repo creation.');
        } else {
            $description = (string) ($this->option('description') ?: $this->composerDescription($dir, $highest));
            $create = ['gh', 'repo', 'create', $package, $this->option('private') ? '--private' : '--public',
                '--source', '.', '--remote', 'origin', ...($description !== '' ? ['--description', $description] : [])];

            if (! $this->exec($dir, $create)) {
                return false;
            }
        }

        // Never --homepage: the Packagist link already lives in the README badges.
        $edit = ['gh', 'repo', 'edit', $package, '--enable-wiki=false', '--enable-projects=false'];
        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('topics')))) as $topic) {
            array_push($edit, '--add-topic', $topic);
        }

        // Release immutability before the first release, so every tag/asset is locked from the start.
        return $this->exec($dir, $edit)
            && $this->exec($dir, ['gh', 'api', '-X', 'PUT', "repos/{$package}/immutable-releases"]);
    }

    /**
     * One push per branch: GitHub fires push workflows (Tests, PHPStan) only for the first ref of a multi-ref push.
     *
     * @param  list<string>  $branches
     */
    private function pushBranches(string $dir, array $branches): bool
    {
        foreach ($branches as $branch) {
            if (! $this->exec($dir, ['git', 'push', '-u', 'origin', $branch])) {
                return false;
            }
        }

        return true;
    }

    private function waitForDependencies(Packagist $packagist): bool
    {
        foreach ((array) $this->option('wait-for') as $spec) {
            if (! preg_match('#^([\w.-]+/[\w.-]+):(.+)$#', (string) $spec, $m)) {
                $this->components->error("--wait-for expects vendor/package:version, got: {$spec}");

                return false;
            }
            [, $dependency, $version] = $m;

            if ($this->dryRun) {
                $this->line("  (dry-run) wait for {$dependency} {$version} on Packagist");

                continue;
            }

            $deadline = time() + (int) $this->option('wait-timeout');
            while (! $packagist->hasVersion($dependency, $version)) {
                if (time() >= $deadline) {
                    $this->components->error("{$dependency} {$version} is not on Packagist yet — releasing now would fail CI. Re-run publish later.");

                    return false;
                }
                $this->line("  waiting for {$dependency} {$version} on Packagist...");
                Sleep::for(20)->seconds();
            }
            $this->components->info("{$dependency} {$version} is on Packagist.");
        }

        return true;
    }

    /**
     * @param  list<string>  $branches
     */
    private function release(string $dir, string $package, array $branches, string $highest): bool
    {
        foreach ($branches as $branch) {
            $tag = VersionBranches::major($branch).'.0.0';

            if (! $this->dryRun && Process::path($dir)->run(['gh', 'release', 'view', $tag, '--repo', $package])->successful()) {
                $this->components->info("Release {$tag} already exists — skipping.");

                continue;
            }

            $filament = VersionBranches::filamentMajor($dir, $branch);
            $notes = (string) ($this->option('notes') ?: 'First release'.($filament ? " for Filament {$filament}.x" : '').'.');

            $ok = $this->exec($dir, ['gh', 'release', 'create', $tag, '--repo', $package, '--target', $branch, '--title', $tag,
                $branch === $highest ? '--latest' : '--latest=false', '--notes', $notes]);

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string|list<string>  $command
     */
    private function exec(string $dir, string|array $command): bool
    {
        $printable = is_array($command) ? implode(' ', $command) : $command;

        if ($this->dryRun) {
            $this->line("  (dry-run) {$printable}");

            return true;
        }

        $result = Process::path($dir)->timeout(600)->run($command);
        $this->line("  {$printable} ".($result->successful() ? '<fg=green>ok</>' : '<fg=red>FAILED</>'));

        if ($result->failed()) {
            $this->line(trim($result->errorOutput() ?: $result->output()));
        }

        return $result->successful();
    }

    private function composerDescription(string $dir, string $branch): string
    {
        $composer = json_decode(Process::path($dir)->run(['git', 'show', "{$branch}:composer.json"])->output(), true);

        return is_array($composer) ? (string) ($composer['description'] ?? '') : '';
    }
}
