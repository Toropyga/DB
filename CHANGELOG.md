# Changelog

All notable changes to this project are documented here.

## [3.2.0] - 2026-09-14

### Added

- Added `DBAPI` as a unified factory and facade for selecting database adapters.
- Added `sqlsrv`, `dblib`, and `firebird` PDO driver support to `PDOLIB`.
- Added Microsoft SQL Server, Sybase, and Firebird metadata discovery.
- Added a minimal PHPUnit test suite for the `DBAPI` factory and PDO alias resolution.

### Fixed

- Fixed `DBAPI` PDO alias normalization for both `pdo_*` and `*_pdo` forms, so aliases like `pdo_mysql`, `mysql_pdo`, `pdo_pgsql`, and `pgsql_pdo` resolve to the correct backend driver consistently.
- Fixed `Oracle::getPackageQuery()` to validate package and procedure names
  before interpolating them into PL/SQL.
- Fixed `PDOLIB` silently falling back to MySQL for an unsupported driver name;
  invalid driver types now raise `InvalidArgumentException`.
- Fixed `DBAPI` dropping adapter runtime options. Connection parameters now
  forward `storage`, `use_transaction`, `debug`, `error_exit`, `log_name`, and
  `log_all` to the adapters that support them; `PDOLIB` also honors transient
  connection mode and PDO logging/error settings.
- `PostgreSQL::processResult()` no longer silently drops rows: the `1`/`'one'`
  and `2`/`'row'` scalar/row shortcuts checked column count only, so a
  multi-row, single-column result returned just the first row's value instead
  of the documented array of values.
- `PostgreSQL::quoteConnectionValue()` now escapes a literal backslash before
  escaping a quote, matching libpq conninfo string syntax; a value containing
  a backslash could previously break or escape its quoted section.
- `Oracle::getProcedureQuery()` now validates `$package`/`$procedure` through
  the shared `validateIdentifier()`, closing a PL/SQL injection gap that every
  other schema-object-accepting method in this codebase already closes.
- `PDOLIB::getListFields()` case-insensitive table matching (added for `oci`
  in 3.0.2) now also covers `firebird`, which has the same upper-cased
  unquoted-identifier convention as Oracle.
- `PDOLIB::quoteField()` now quotes `sqlsrv` identifiers with square brackets
  instead of double quotes, and leaves `dblib` identifiers unquoted (its
  double-quote support depends on a server setting that isn't guaranteed on;
  `dblib` field names are always drawn from a validated whitelist already).
- Updated PHPUnit to a PHP 8.4 compatible version and removed the remaining
  deprecation warning from the test run.

## [3.1.1] - 2026-09-12

### Documentation

- Updated the English and Russian README files to document all four adapters.
- Added PostgreSQL constructor, metadata, and SQL builder examples.
- Documented the `ext-pdo_pgsql` and `ext-pdo_sqlite` requirements.

## [3.1.0] - 2026-09-12

### Added

- Added the standalone `PostgreSQL` adapter using `ext-pgsql`.
- Added PostgreSQL table metadata and parameterized CRUD operations.
- Added SQLite support to `PDOLIB` through the `sqlite` PDO driver.
- Added SQLite table and column metadata discovery.

## [3.0.2] - 2026-09-12

### Breaking Changes

- Public methods now use strict parameter types. Calls that previously passed
  invalid values and received `false` may now throw `TypeError`.
- The affected methods are `MySQL::getListFields()`,
  `MySQL::setInsert()`, `PDOLIB::prepare()`, `PDOLIB::getListFields()`, and
  `Oracle::getProcedureQuery()`.
- Update callers to validate arguments before calling these methods. This
  change is intentionally documented as backwards-incompatible.

### Fixed

- Restored `MySQL` `res2array()` handling of `6`/`'dub_all'`, lost in 3.0.1.
- `sanitizeErrorMessage()` no longer erases the underlying driver error text
  (the `ERROR: ...` portion) when stripping SQL/query text from log messages.
- `PDOLIB::getListFields()` now compares and caches Oracle (`oci`) table names
  case-insensitively, matching `Oracle.php`'s own handling of Oracle's
  upper-cased unquoted identifiers.

## [3.0.1] - 2026-09-11

### Added

- Added parameterized `setInsert()`, `setUpdate()`, and `setDelete()` methods to the Oracle adapter.

### Changed

- Unified `TableList` field-name handling across the MySQL, Oracle, and PDOLIB adapters.
- Oracle and PDOLIB now match MySQL result-shape behavior for scalar, row, column, and multi-row queries.
- Oracle `getQuery()` now supports named OCI8 bind parameters and ordinary non-cursor SQL statements.
- SQL text builders now reject empty `INSERT` and `UPDATE` statements when no valid table fields are supplied.
- `getQuerySQL()` parameter substitution now preserves placeholder order for repeated and interleaved parameters.

### Fixed

- Fixed MySQL `getInsertSQL()`, `getDeleteSQL()`, and `lastID()` handling of `SHOW COLUMNS` metadata.
- Fixed Oracle and PDOLIB handling of empty or invalid field lists.

### Added

- Added `DatabaseAdapterInterface` as the common adapter contract.
- Added `DatabaseException` for database failures when error-exit mode is enabled.
- Added the temporary `PDO_LIB extends PDOLIB` compatibility wrapper for v2.x applications.
- Added the PHP and extension support matrix to the README files.

### Changed

- Moved shared identifier validation, error sanitization, and error handling into `AbstractDB`.
- Separated parameterized execution from SQL text generation.
- MySQL `setInsert()`, `setUpdate()`, and `setDelete()` now use bound parameters.
- Database errors are logged without rendering HTML or terminating the process with `exit`.
- Full SQL statements and bound values are no longer written to logs.
- `Oracle::getTableList()` and `Oracle::getListFields()` are available as public Oracle metadata methods.
- Cleaned up source comments and documented the `PDO_LIB` to `PDOLIB` migration.
- Oracle normal SELECT statements now use the parsed statement directly; cursors are created only for `:res` output cursors.
- PDOLIB metadata lookup now handles scalar column-list results and failed metadata queries safely.
- PDOLIB clears stale statements and rejects query/prepare calls without an active PDO connection.
- PDOLIB SQL builders quote field identifiers according to the selected driver.

### Security

- Removed the MySQL fallback that interpolated values into SQL when `mysqlnd` was unavailable.
- Added validation for dynamic table and column identifiers.
- Sanitized SQL text from database error messages before it is stored in logs.
- Fixed OCI error handling so structured `oci_error()` results are converted to messages without type errors or SQL leakage.

## [3.0.0]

### Changed

- Preferred PDO adapter name is `PDOLIB`.
- `PDO_LIB` remains available as a temporary compatibility wrapper.
- Minimum supported PHP version is 8.1.

[3.2.0]: https://github.com/Toropyga/DB/releases/tag/v3.2.0
[3.1.1]: https://github.com/Toropyga/DB/releases/tag/v3.1.1
[3.1.0]: https://github.com/Toropyga/DB/releases/tag/v3.1.0
[3.0.2]: https://github.com/Toropyga/DB/releases/tag/v3.0.2
[3.0.1]: https://github.com/Toropyga/DB/releases/tag/v3.0.1
[3.0.0]: https://github.com/Toropyga/DB/releases/tag/v3.0.0
