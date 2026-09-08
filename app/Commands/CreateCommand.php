<?php

namespace App\Commands;

use App\Support\Scaffold;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
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
        {--branch=1.x : branch name for the single-branch case (ignored when --all-branches is set)}
        {--filament-version=3 : Filament major (3, 4 or 5) for the starting/single branch}
        {--to-filament-version= : Filament major to end at when --all-branches is set (default: 5)}
        {--all-branches : scaffold sequential branches spanning --filament-version..--to-filament-version (2.x built off 1.x, 3.x off 2.x, ...) — e.g. --filament-version=3 covers 1.x->5.x, --filament-version=4 --to-filament-version=4 covers only 1.x->4.x}
        {--path= : target directory (default: ./<package> under cwd)}
        {--namespace= : PSR-4 root namespace, e.g. "JeffersonGoncalves\Filament\Ban" (default: StudlyVendor\StudlyPackage)}
        {--keywords= : comma-separated composer keywords (default: laravel,filament,filament-plugin,<package>)}
        {--require= : extra runtime deps, comma-separated name:constraint}
        {--author= : defaults to `git config user.name`}
        {--email= : defaults to `git config user.email`}
        {--no-git : skip git init/commit}
        {--dry-run : print planned actions, write nothing}';

    protected $description = 'Scaffold a new open-source Filament plugin with multi-branch git already configured (branch-to-Filament-major mapping controlled by --filament-version/--to-filament-version).';

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

        $filamentVersion = (int) $this->option('filament-version');
        if ($filamentVersion < Scaffold::MIN_FILAMENT_VERSION || $filamentVersion > Scaffold::MAX_FILAMENT_VERSION) {
            $this->components->error('--filament-version must be between '.Scaffold::MIN_FILAMENT_VERSION.' and '.Scaffold::MAX_FILAMENT_VERSION.", got: {$filamentVersion}");

            return self::FAILURE;
        }

        $toOption = $this->option('to-filament-version');
        $toFilamentVersion = $toOption !== null ? (int) $toOption : Scaffold::MAX_FILAMENT_VERSION;
        if ($toFilamentVersion < Scaffold::MIN_FILAMENT_VERSION || $toFilamentVersion > Scaffold::MAX_FILAMENT_VERSION) {
            $this->components->error('--to-filament-version must be between '.Scaffold::MIN_FILAMENT_VERSION.' and '.Scaffold::MAX_FILAMENT_VERSION.", got: {$toFilamentVersion}");

            return self::FAILURE;
        }
        if ($toFilamentVersion < $filamentVersion) {
            $this->components->error('--to-filament-version must be >= --filament-version');

            return self::FAILURE;
        }

        if ($this->option('all-branches')) {
            $plan = [];
            foreach (range($filamentVersion, $toFilamentVersion) as $i => $major) {
                $plan[] = ['branch' => Scaffold::branchName($i), 'filament' => $major];
            }
        } else {
            $plan = [['branch' => (string) $this->option('branch'), 'filament' => $filamentVersion]];
        }
        $branchNames = array_column($plan, 'branch');

        // Defaults to the standard nested shape (filament-ban =>
        // JeffersonGoncalves\Filament\Ban); --namespace overrides it for repos
        // that predate the convention. Either way the last segment drives the
        // class names.
        $namespace = trim((string) $this->option('namespace'), '\\')
            ?: Scaffold::rootNamespace($vendor, $package);
        $class = Str::afterLast($namespace, '\\');
        $serviceProvider = $class.'ServiceProvider';
        $pluginClass = $class.'Plugin';
        $title = $class;

        $keywords = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('keywords')))));

        $extraRequire = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('require')))) as $dep) {
            [$name, $constraint] = array_pad(explode(':', $dep, 2), 2, '*');
            $extraRequire[trim($name)] = trim($constraint);
        }

        $author = $this->option('author') ?: trim((string) Process::run('git config --get user.name')->output()) ?: 'Jefferson Gonçalves';
        $email = $this->option('email') ?: trim((string) Process::run('git config --get user.email')->output());
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

        foreach ($plan as $i => $step) {
            $branch = $step['branch'];
            $major = $step['filament'];

            if ($i === 0) {
                $this->scaffoldSharedFiles($dir, $vendor, $package, $namespace, $serviceProvider, $pluginClass, $title, $description, $author, $year, $branchNames, $write);
                if (! $noGit) {
                    $gitRun('init -q');
                    $gitRun("checkout -q -B {$branch}");
                }
            } elseif (! $noGit) {
                $gitRun("checkout -q -b {$branch}");
            }

            $composerJson = Scaffold::filamentComposerJson(
                $vendor, $package, $namespace, $serviceProvider, $description, $major,
                author: $author, email: $email, keywords: $keywords, extraRequire: $extraRequire,
            );
            $write('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", force: true);
            $write('.github/workflows/tests.yml', Scaffold::testsYml($branch, $major), force: true);

            if (! $dryRun) {
                File::delete($dir.'/composer.lock');
            }

            if (! $noGit) {
                $gitRun('add .');
                $msg = $i === 0
                    ? "feat: scaffold plugin structure ({$branch}, Filament v{$major})"
                    : "feat: upgrade to Filament v{$major} compatibility";
                $gitRun('commit -q -m "'.$msg.'"');
            }
        }

        $this->components->info(($dryRun ? '[dry-run] ' : '')."Scaffolded {$vendor}/{$package} at {$dir} (branches: ".implode(', ', $branchNames).')');
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
            'set the default branch on GitHub to the lowest scaffolded branch (e.g. '.$branchNames[0].')',
        ] as $step) {
            $this->line("  - {$step}");
        }

        return self::SUCCESS;
    }

    private function scaffoldSharedFiles(string $dir, string $vendor, string $package, string $namespace, string $serviceProvider, string $pluginClass, string $title, string $description, string $author, string $year, array $branchNames, \Closure $write): void
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

        $write('.github/workflows/pint.yml', Scaffold::workflowPint([...$branchNames, 'main']));
        $write('.github/workflows/phpstan.yml', Scaffold::workflowPhpstan([...$branchNames, 'main']));
        $write('.github/workflows/update-changelog.yml', Scaffold::workflowChangelog());
    }
}
