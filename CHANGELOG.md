# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.3] - 2026-11-13

### Fixed

- The back office no longer sends you to the shop's home page after this module is
  installed from its release ZIP. The index.php file in every folder of the package
  redirected and stopped the process also when PrestaShop loaded it while scanning
  `src/ApiPlatform/Resources`, so the back office could not be built.

## [0.1.2] - 2026-11-10

### Changed

- Admin API descriptions no longer use long dashes.

## [0.1.1] - 2026-11-10

### Added

- Polish translation of every text the module shows, in the back office and in the shop.

## [0.1.0] - 2026-11-10

First public release.

### Added

- Admin API resources for merchandise returns (RMA): a list filtered by update date,
  order and state with paging, a detail view with line items and prices from the
  order, and a state change through the PrestaShop core command.
- A list of return states with names in the shop's language.
- `/quince-api/info` for capability discovery.
- OAuth2 scopes `quince_api_order_returns_read` and `quince_api_order_returns_write`.
