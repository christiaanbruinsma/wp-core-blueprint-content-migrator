# Release tooling

## Conformance

```bash
php tools/conformance.php
```

Checks the standalone contract, required runtime files, migration safety primitives and absence of private Core Blueprint Base dependencies.

## Build

```bash
bash tools/build-release
```

Requirements: `php`, `rsync`, `zip`.

The builder recreates `build/`, stages the plugin under the canonical `core-blueprint-content-migrator/` folder, lints staged PHP, runs conformance and creates `build/core-blueprint-content-migrator-<version>.zip`.

A lint or conformance failure stops the build before packaging.
