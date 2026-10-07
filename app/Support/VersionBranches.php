<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * The `N.x` branches of a multi-branch plugin repo (1.x, 2.x, 3.x...), lowest first.
 */
class VersionBranches
{
    /**
     * @return list<string>
     */
    public static function in(string $dir): array
    {
        $output = Process::path($dir)->run(['git', 'branch', '--format=%(refname:short)'])->output();

        return self::sort(preg_split('/\R/', $output) ?: []);
    }

    /**
     * @param  array<int, string>  $branches
     * @return list<string>
     */
    public static function sort(array $branches): array
    {
        $versions = array_values(array_filter(array_map('trim', $branches), fn (string $b) => preg_match('/^\d+\.x$/', $b) === 1));
        usort($versions, fn (string $a, string $b) => (int) $a <=> (int) $b);

        return $versions;
    }

    /** `3.x` -> 3 */
    public static function major(string $branch): int
    {
        return (int) $branch;
    }

    /**
     * Filament major a branch targets, read from its composer.json `filament/filament` constraint.
     */
    public static function filamentMajor(string $dir, string $branch): ?int
    {
        $result = Process::path($dir)->run(['git', 'show', "{$branch}:composer.json"]);
        $composer = json_decode($result->output(), true);
        $constraint = is_array($composer) ? ($composer['require']['filament/filament'] ?? null) : null;

        return is_string($constraint) && preg_match('/(\d+)/', $constraint, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Lowest version a constraint accepts, used to give a local path repository a matching version:
     * `^1.0` -> 1.0.0, `^2.3|^3.0` -> 2.3.0.
     */
    public static function lowestVersion(string $constraint): string
    {
        if (preg_match('/(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $constraint, $m) !== 1) {
            return '1.0.0';
        }

        return $m[1].'.'.($m[2] ?? '0').'.'.($m[3] ?? '0');
    }
}
