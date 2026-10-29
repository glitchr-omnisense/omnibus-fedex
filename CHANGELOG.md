# Changelog

## [Unreleased]

### Fixed
- A bare language is sent as an existing FedEx locale (`en` as `en_US`, `de` as `de_DE`...; an
  unknown one as `en_US`); it was doubled in capitals (`en_EN`).
