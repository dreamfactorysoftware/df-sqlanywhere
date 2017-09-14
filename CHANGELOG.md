# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Fixed
- DF-1160 Correct resource name usage for procedures and functions when pulling parameters

## [0.12.0] - 2017-08-17
### Changed
- Reworked API doc usage and generation

## [0.11.0] - 2017-07-27
- Separating base schema from SQL schema

## [0.10.0] - 2017-06-05
### Changed
- Cleanup - removal of php-utils dependency

## [0.9.0] - 2017-04-21
### Changed
- Use new service config handling for database configuration

## [0.8.0] - 2017-03-03
- Major restructuring to upgrade to Laravel 5.4 and be more dynamically available

## [0.7.0] - 2017-01-16
### Changed
- Adhere to refactored df-core, see df-database
- Cleanup schema management issues

## [0.6.0] - 2016-11-17
### Changed
- Virtual relationships rework to support all relationship types
- DB base class changes to support field configuration across all database types.

### Fixed
- Correct Params on select for column discovery

## [0.5.0] - 2016-10-03
- Update to latest df-core and df-sqldb changes and cleanup

## [0.4.0] - 2016-08-21
### Changed
- General cleanup from declaration changes in df-core for service doc and providers

## [0.3.1] - 2016-07-08
### Added
- DF-636 Adding ability using 'ids' parameter to return the schema of a stored procedure or function

### Fixed
- Bug fixes on stored procedures and functions

## 0.3.0 - 2016-05-27
First official release working with the new [df-core](https://github.com/dreamfactorysoftware/df-core) library.

[Unreleased]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.12.0...HEAD
[0.12.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.11.0...0.12.0
[0.11.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.9.0...0.10.0
[0.9.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.8.0...0.9.0
[0.8.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.3.1...0.4.0
[0.3.1]: https://github.com/dreamfactorysoftware/df-sqlanywhere/compare/0.3.0...0.3.1
