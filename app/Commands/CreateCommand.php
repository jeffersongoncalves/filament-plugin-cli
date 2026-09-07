<?php

namespace App\Commands;

use App\Support\Scaffold;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class CreateCommand extends Command
{
    /**
     * Fully non-interactive: every input comes from arguments/options so an
     * AI agent (or any script) can call this without a TTY. Never add ->ask()
     * or ->confirm() here.
     *
     * @var string
     */
    protected $signature = 'create
        {vendor-package : vendor/package, e.g. jeffersongoncalves/filament-settings}
        {description? : short description of the plugin}
        {--branch=1.x : starting branch (1.x, 2.x or 3.x) when --all-branches is not set}
        {--all-branches : scaffold 1.x, 2.x and 3.x in sequence (2.x built off 1.x, 3.x off 2.x)}
        {--path= : target directory (default: ./<package> under cwd)}
        {--author= : defaults to `git config user.name`}
        {--email= : defaults to `git config user.email`}
        {--no-git : skip git init/commit}
        {--dry-run : print planned actions, write nothing}';

    protected $description = 'Scaffold a new open-source Filament plugin with multi-branch (1.x/2.x/3.x) git already configured.';

    public function handle(): int
    {
        $vendorPackage = (string) $this->argument('vendor-package');
        if (! str_contains($vendorPackage, '/')) {
            $this->components->error("Expected vendor/package, got: {$vendorPackage}");

            return self::FAILURE;
        }
        [$vendor, $package] = explode('/', $vendorPackage, 2);
        $description = (string) ($this->argument('description') ?? '');

        $dryRun = (bool) $this->option('dry-run');
        $noGit = (bool) $this->option('no-git');

        $branches = $this->option('all-branches') ? Scaffold::ALL_BRANCHES : [(string) $this->option('branch')];
        foreach ($branches as $b) {
            if (! isset(Scaffold::BRANCHES[$b])) {
                $this->components->error("Unknown branch: {$b} (expected 1.x, 2.x or 3.x)");

                return self::FAILURE;
            }
        }

        $namespace = Scaffold::studly($vendor).'\\'.Scaffold::studly($package);
        $serviceProvider = Scaffold::studly($package).'ServiceProvider';
        $pluginClass = Scaffold::studly($package).'Plugin';
        $title = Scaffold::studly($package);

        $author = $this->option('author') ?: trim((string) Process::run('git config --get user.name')->output()) ?: 'Jefferson Gonçalves';
        $year = date('Y');

        $dir = $this->option('path') ?: getcwd().DIRECTORY_SEPARATOR.$package;

        $files = [];
        $git = [];

        $write = function (string $relative, string $content, bool $force = false) use ($dir, $dryRun, &$files): void {
            $path = $dir.'/'.$relative;
            if (! $force && File::exists($path)) {
                $files[] = "skip (exists) {$relative}";

                return;
            }
            if ($dryRun) {
                $files[] = ($force ? 'write (force, dry-run) ' : 'write (dry-run) ')."{$relative}";

                return;
            }
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
            $files[] = ($force ? 'write (force) ' : 'write ')."{$relative}";
        };

        $gitRun = function (string $cmd) use ($dir, $dryRun, &$git): void {
            if ($dryRun) {
                $git[] = "(dry-run) git {$cmd}";

                return;
            }
            File::ensureDirectoryExists($dir);
            $result = Process::path($dir)->run("git {$cmd}");
            $git[] = $result->failed()
                ? "git {$cmd} FAILED: ".trim($result->errorOutput())
                : "git {$cmd} ok";
        };

        foreach ($branches as $i => $branch) {
            $version = Scaffold::BRANCHES[$branch]['version'];

            if ($i === 0) {
                $this->scaffoldSharedFiles($dir, $vendor, $package, $namespace, $serviceProvider, $pluginClass, $title, $description, $author, $year, $write);
                if (! $noGit) {
                    $gitRun('init -q');
                    $gitRun("checkout -q -B {$branch}");
                }
            } elseif (! $noGit) {
                $gitRun("checkout -q -b {$branch}");
            }

            $write('composer.json', json_encode(Scaffold::filamentComposerJson($vendor, $package, $namespace, $serviceProvider, $description, $branch), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", force: true);
            $write('.github/workflows/tests.yml', Scaffold::testsYml($branch), force: true);

            if (! $dryRun) {
                File::delete($dir.'/composer.lock');
            }

            if (! $noGit) {
                $gitRun('add .');
                $msg = $i === 0
                    ? "feat: scaffold plugin structure ({$branch}, Filament v{$version})"
                    : "feat: upgrade to Filament v{$version} compatibility";
                $gitRun('commit -q -m "'.$msg.'"');
            }
        }

        $this->components->info(($dryRun ? '[dry-run] ' : '')."Scaffolded {$vendor}/{$package} at {$dir} (branches: ".implode(', ', $branches).')');
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
            'vendor/bin/phpstan analyse',
            'vendor/bin/pint',
            "gh repo create {$vendor}/{$package} --public --source={$dir} --remote=origin --push",
            'set the default branch on GitHub to the lowest scaffolded branch (e.g. 1.x)',
        ] as $step) {
            $this->line("  - {$step}");
        }

        return self::SUCCESS;
    }

    private function scaffoldSharedFiles(string $dir, string $vendor, string $package, string $namespace, string $serviceProvider, string $pluginClass, string $title, string $description, string $author, string $year, \Closure $write): void
    {
        $write('.editorconfig', Scaffold::editorconfig());
        $write('.gitattributes', Scaffold::gitattributes());
        $write('.gitignore', Scaffold::gitignore());
        $write('LICENSE.md', Scaffold::license($author, $year));
        $write('CHANGELOG.md', Scaffold::changelog());
        $write('phpstan.neon.dist', Scaffold::phpstanNeon());
        $write('phpunit.xml.dist', Scaffold::phpunitXml("$title Test Suite"));
        $write('README.md', Scaffold::readme($title, $vendor, $package, $description, "$vendor/$package"));
        $write("config/{$package}.php", "<?php\n\nreturn [\n];\n");

        $write('tests/Pest.php', <<<PHP
        <?php

        uses({$namespace}\Tests\TestCase::class)->in('Feature');

        PHP);

        $write('tests/Fixtures/TestPanelProvider.php', <<<PHP
        <?php

        namespace {$namespace}\Tests\Fixtures;

        use Filament\Panel;
        use Filament\PanelProvider;

        class TestPanelProvider extends PanelProvider
        {
            public function panel(Panel \$panel): Panel
            {
                return \$panel
                    ->default()
                    ->id('admin')
                    ->path('admin')
                    ->login();
            }
        }

        PHP);

        $write('tests/TestCase.php', <<<PHP
        <?php

        namespace {$namespace}\Tests;

        use {$namespace}\Tests\Fixtures\TestPanelProvider;
        use {$namespace}\\{$serviceProvider};
        use Filament\FilamentServiceProvider;
        use Filament\Support\SupportServiceProvider;
        use Livewire\LivewireServiceProvider;
        use Orchestra\Testbench\TestCase as Orchestra;

        abstract class TestCase extends Orchestra
        {
            protected function getPackageProviders(\$app): array
            {
                return [
                    LivewireServiceProvider::class,
                    SupportServiceProvider::class,
                    FilamentServiceProvider::class,
                    TestPanelProvider::class,
                    {$serviceProvider}::class,
                ];
            }

            protected function getEnvironmentSetUp(\$app): void
            {
                config()->set('database.default', 'testing');
                config()->set('database.connections.testing', [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                ]);
                config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
            }
        }

        PHP);

        $write('src/'.$serviceProvider.'.php', <<<PHP
        <?php

        namespace {$namespace};

        use Spatie\LaravelPackageTools\Package;
        use Spatie\LaravelPackageTools\PackageServiceProvider;

        class {$serviceProvider} extends PackageServiceProvider
        {
            public function configurePackage(Package \$package): void
            {
                \$package
                    ->name('{$package}')
                    ->hasConfigFile()
                    ->hasViews()
                    ->hasMigrations();
            }
        }

        PHP);

        $write('src/'.$pluginClass.'.php', <<<PHP
        <?php

        namespace {$namespace};

        use Filament\Contracts\Plugin;
        use Filament\Panel;

        class {$pluginClass} implements Plugin
        {
            public function getId(): string
            {
                return '{$package}';
            }

            public function register(Panel \$panel): void
            {
            }

            public function boot(Panel \$panel): void
            {
            }

            public static function make(): static
            {
                return app(static::class);
            }

            public static function get(): static
            {
                return filament(app(static::class)->getId());
            }
        }

        PHP);

        $write('.github/workflows/pint.yml', Scaffold::workflowPint([...Scaffold::ALL_BRANCHES, 'main']));
        $write('.github/workflows/phpstan.yml', Scaffold::workflowPhpstan([...Scaffold::ALL_BRANCHES, 'main']));
        $write('.github/workflows/update-changelog.yml', Scaffold::workflowChangelog());
    }
}
