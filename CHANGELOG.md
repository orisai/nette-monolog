# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/orisai/nette-monolog/compare/1.2.0...HEAD)

## [1.2.0](https://github.com/orisai/nette-monolog/compare/1.1.0...1.2.0)

### Added

- Support for `monolog/monolog ^3.0.0`

## [1.1.0](https://github.com/orisai/nette-monolog/compare/1.0.2...1.1.0)

### Added

- Support for `psr/log ^3.0.0`
- *nette/application* bridge
	- `LogFlusher->reset()` call on `Application::$onShutdown` event

## [1.0.2](https://github.com/orisai/nette-monolog/compare/1.0.1...1.0.2)

### Fixed

- Logtail - client was sending invalid authentication header

## [1.0.1](https://github.com/orisai/nette-monolog/compare/1.0.0...1.0.1)

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
