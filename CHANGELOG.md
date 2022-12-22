# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/orisai/nette-monolog/compare/1.2.5...HEAD)

### Changed

- `Logtail`
  - sends json pretty formatted
  - setUrl() instead of setUri() (old method is deprecated)
  - `dt` field containing datetime is always properly formatted

## [1.2.5](https://github.com/orisai/nette-monolog/compare/1.2.4...1.2.5) - 2022-12-14

### Fixed

- Configuring `Tracy\Logger` via `TracyExtension` works correctly in case `MonologExtension` is loaded
  before `TracyExtension` (opposite order worked before and is unaffected by this change)

## [1.2.4](https://github.com/orisai/nette-monolog/compare/1.2.3...1.2.4) - 2022-12-09

### Changed

- Composer
	- allows PHP 8.2

## [1.2.3](https://github.com/orisai/nette-monolog/compare/1.2.2...1.2.3) - 2022-06-12

### Fixed

- LogtailClient throws on HTTP error

## [1.2.2](https://github.com/orisai/nette-monolog/compare/1.2.1...1.2.2) - 2022-06-06

### Fixed

- LogtailHandler Monolog v3 compatiblity

## [1.2.1](https://github.com/orisai/nette-monolog/compare/1.2.0...1.2.1) - 2022-05-27

### Added

- Tracy panel design

## [1.2.0](https://github.com/orisai/nette-monolog/compare/1.1.0...1.2.0) - 2022-05-14

### Added

- Support for `monolog/monolog ^3.0.0`

## [1.1.0](https://github.com/orisai/nette-monolog/compare/1.0.2...1.1.0) - 2021-11-09

### Added

- Support for `psr/log ^3.0.0`
- *nette/application* bridge
	- `LogFlusher->reset()` call on `Application::$onShutdown` event

## [1.0.2](https://github.com/orisai/nette-monolog/compare/1.0.1...1.0.2) - 2021-09-17

### Fixed

- Logtail - client was sending invalid authentication header

## [1.0.1](https://github.com/orisai/nette-monolog/compare/1.0.0...1.0.1) - 2021-09-14

### Added

- Support for `psr/log ^2.0.0`

## [1.0.0](https://github.com/orisai/nette-monolog/releases/tag/1.0.0) - 2021-09-09

### Added

- `MonologExtension`
- Logtail
    - `LogtailHandler`
    - `LogtailClient`
- Tracy integration (via extension)
- `LogFlusher`
- `StaticLoggerGetter`
