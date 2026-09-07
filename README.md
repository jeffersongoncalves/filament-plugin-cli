<div class="filament-hidden">

<!-- banner: art/jeffersongoncalves-filament-plugin-cli.png (generate via portfolio-banner skill) -->

</div>

# Filament Plugin CLI

Scaffold new open-source Filament plugins with **multi-branch** git already configured (`1.x`/`2.x`/`3.x` for Filament v3/v4/v5), built with [Laravel Zero](https://laravel-zero.com/). Fully non-interactive — every input is an argument or a flag, so it's meant to be driven by an AI agent (e.g. Claude Code's `filament-plugin-creator` skill) as much as by a human.

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
- CI workflows: branch-scoped `tests.yml`, plus `pint.yml`/`phpstan.yml`/`update-changelog.yml` covering all three branches
- `git init`, branch checkout(s), and a commit per branch

It deliberately does **not** write the plugin's actual logic (Plugin/Service Provider bindings, components, README body, tests, banner) — that's judgment work left to whoever (human or agent) is building the plugin on top of this scaffold.

| Branch | Filament | PHP | orchestra/testbench |
|--------|----------|-----|----------------------|
| `1.x` | `^3.0` | `^8.1` | `^8.0\|^9.0` |
| `2.x` | `^4.0` | `^8.2` | `^9.0\|^10.0` |
| `3.x` | `^5.0` | `^8.2` | `^10.0\|^11.0` |

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

Scaffold a single starting branch (default `1.x`):

```bash
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament" --branch=3.x
```

Scaffold all three version branches in one go (`2.x` built off `1.x`, `3.x` off `2.x`):

```bash
filament-plugin create jeffersongoncalves/filament-settings "Runtime settings panel for Filament" --all-branches
```

Add a version branch to a plugin repo that already exists:

```bash
filament-plugin branch jeffersongoncalves/filament-settings --branch=2.x --path=./filament-settings --from=1.x
```

### `create` options

| Option | Description |
|--------|-------------|
| `--branch=1.x` | Single starting branch (default `1.x`); ignored when `--all-branches` is set |
| `--all-branches` | Scaffold `1.x`, `2.x` and `3.x` in sequence |
| `--path=DIR` | Target directory (default: `./<package>` under the current directory) |
| `--author="Name"` | Defaults to `git config user.name` |
| `--email=EMAIL` | Defaults to `git config user.email` |
| `--no-git` | Skip `git init`/commit |
| `--dry-run` | Print the planned file list and git commands, write nothing |

### `branch` options

| Option | Description |
|--------|-------------|
| `--branch=2.x` | Branch to create (required) |
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
