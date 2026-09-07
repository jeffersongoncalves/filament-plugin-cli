<div class="filament-hidden">

![Filament Plugin CLI](art/jeffersongoncalves-filament-plugin-cli.png)

</div>

# Filament Plugin CLI

Scaffold new open-source Filament plugins with **multi-branch** git already configured, built with [Laravel Zero](https://laravel-zero.com/). Fully non-interactive — every input is an argument or a flag, so it's meant to be driven by an AI agent (e.g. Claude Code's `filament-plugin-creator` skill) as much as by a human.

<p align="center">
  <a href="https://github.com/jeffersongoncalves/filament-plugin-cli/actions"><img src="https://github.com/jeffersongoncalves/filament-plugin-cli/actions/workflows/run-tests.yml/badge.svg" alt="Tests" /></a>
  <a href="https://packagist.org/packages/jeffersongoncalves/filament-plugin-cli"><img src="https://img.shields.io/packagist/dt/jeffersongoncalves/filament-plugin-cli" alt="Total Downloads" /></a>
  <a href="https://github.com/jeffersongoncalves/filament-plugin-cli/blob/main/LICENSE"><img src="https://img.shields.io/github/license/jeffersongoncalves/filament-plugin-cli" alt="License" /></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.2-8892BF" alt="PHP 8.2+" />
</p>

## What it does

`filament-plugin create vendor/package "Description"` generates the mechanical, repeatable part of a new Spatie-style Filament plugin, on the version branch(es) it belongs on — **never `main`**:

- Directory skeleton, boilerplate files (`.editorconfig`, `.gitattributes`, `.gitignore`, `LICENSE.md`, `CHANGELOG.md`, `README.md`, `phpstan.neon.dist`, `phpunit.xml.dist`)
- `composer.json` correct per branch (`filament/filament`, PHP floor, `orchestra/testbench` range — see the branch table below)
- Service Provider (`PackageServiceProvider`) and `Filament\Contracts\Plugin` class stubs
- `tests/TestCase.php` + `tests/Fixtures/TestPanelProvider.php` + `tests/Pest.php`
- CI workflows: branch-scoped `tests.yml`, plus `pint.yml`/`phpstan.yml`/`update-changelog.yml` covering every branch scaffolded
- `git init`, branch checkout(s), and a commit per branch

It deliberately does **not** write the plugin's actual logic (Plugin/Service Provider bindings, components, README body, tests, banner) — that's judgment work left to whoever (human or agent) is building the plugin on top of this scaffold.

Branch **naming** is always sequential (`1.x`, `2.x`, `3.x`, ...) — which Filament major each one targets is controlled separately via `--filament-version`/`--to-filament-version`, so a plugin doesn't have to start at Filament 3:

| Filament major | `filament/filament` | PHP | orchestra/testbench |
|-----------------|----------------------|-----|----------------------|
| 3 | `^3.0` | `^8.1` | `^8.0\|^9.0` |
| 4 | `^4.0` | `^8.2` | `^9.0\|^10.0` |
| 5 | `^5.0` | `^8.2` | `^10.0\|^11.0` |

## Requirements

- PHP 8.2+
- Git

## Installation

```bash
composer global require jeffersongoncalves/filament-plugin-cli
```

Or clone and build locally:

```bash
git clone https://github.com/jeffersongoncalves/filament-plugin-cli.git
cd filament-plugin-cli
composer install
php filament-plugin app:build filament-plugin
```

## Usage

Scaffold a single starting branch targeting Filament 3 (the defaults — branch `1.x`, `--filament-version=3`):

```bash
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament"
```

Scaffold a single branch that starts straight at a later Filament major (e.g. the plugin never supported v3):

```bash
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament" --filament-version=4
```

Scaffold every branch from Filament 3 through 5 in one go — `1.x`→v3, `2.x`→v4, `3.x`→v5 (`2.x` built off `1.x`, `3.x` off `2.x`):

```bash
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament" --all-branches
```

Narrow the range — e.g. only `1.x`→v3 and `2.x`→v4 (stop before v5), or only `1.x`→v4 and `2.x`→v5 (skip v3 entirely):

```bash
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament" --all-branches --to-filament-version=4
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament" --all-branches --filament-version=4
```

Add a version branch to a plugin repo that already exists:

```bash
filament-plugin branch jeffersongoncalves/filament-settings --branch=2.x --filament-version=4 --path=./filament-settings --from=1.x
```

### `create` options

| Option | Description |
|--------|-------------|
| `--branch=1.x` | Branch name for the single-branch case (default `1.x`); ignored when `--all-branches` is set |
| `--filament-version=3` | Filament major (3, 4 or 5) for the starting/single branch (default `3`) |
| `--to-filament-version=5` | Filament major to end at when `--all-branches` is set (default `5`); must be `>= --filament-version` |
| `--all-branches` | Scaffold sequential branches (`1.x`, `2.x`, ...) spanning `--filament-version..--to-filament-version` |
| `--path=DIR` | Target directory (default: `./<package>` under the current directory) |
| `--author="Name"` | Defaults to `git config user.name` |
| `--email=EMAIL` | Defaults to `git config user.email` |
| `--no-git` | Skip `git init`/commit |
| `--dry-run` | Print the planned file list and git commands, write nothing |

### `branch` options

| Option | Description |
|--------|-------------|
| `--branch=2.x` | Name of the branch to create (required) |
| `--filament-version=4` | Filament major (3, 4 or 5) this branch targets (required) |
| `--path=DIR` | Existing plugin repo (required) |
| `--from=1.x` | Branch to branch off (default: current `HEAD`) |
| `--dry-run` | Print the planned actions, write nothing |

Every argument/option is designed for scripted, non-interactive invocation — no prompts are ever shown.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security related issues, please see [SECURITY](.github/SECURITY.md).

## Credits

- [Jefferson Goncalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
