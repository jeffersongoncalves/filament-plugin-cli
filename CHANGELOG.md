# Changelog

All notable changes to this project will be documented in this file.

## [1.0.9] - 2026-09-08

### Bug Fixes

- **ci:** Publish release as draft until PHAR asset is attached

## [1.0.8] - 2026-09-08

### Bug Fixes

- Move the package-scaffold dependency to require-dev

### Documentation

- Document the shared vendornamespace.json in the namespace section

## [1.0.7] - 2026-09-08

### Refactor

- Move the shared scaffold templates into laravel-zero-package-scaffold

## [1.0.6] - 2026-09-08

### Features

- Default to the nested Vendor\Filament\Name namespace

## [1.0.5] - 2026-09-08

### Documentation

- Document self-update command in README

### Features

- Add --namespace, --keywords and --require to the create command

## [1.0.4] - 2026-09-07

### Bug Fixes

- Keep require-dev autoload in the compiled PHAR

## [1.0.3] - 2026-09-07

### Bug Fixes

- Point composer bin to prebuilt PHAR instead of source stub

## [1.0.2] - 2026-09-07

### Features

- Add self-update command

## [1.0.1] - 2026-09-07

### CI/CD

- Add release workflow to build and publish PHAR

## [1.0.0] - 2026-09-07

### Bug Fixes

- Raise php floor to ^8.3, test PHP 8.4 only in CI

### Documentation

- Add portfolio banner

### Features

- Scaffold filament-plugin-cli
- Decouple branch naming from Filament major


