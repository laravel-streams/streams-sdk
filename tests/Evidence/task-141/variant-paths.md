# Task 141 — Version detection and configurable output locations

**Commit:** `c0748f0` on `ops/run-118-a263` (local, not pushed).

## What was done

- **`src/Console/Make/Variants.php`** — detects the installed Laravel and
  Streams (streams/core) major versions and resolves a small variant id.
  Default map (`streams.sdk.make.variant_map`): `laravel-11.streams-2` and
  `laravel-12.streams-2` → `v1`; unmapped pairs fall back to the default
  variant. Per-variant reference templates live in published config
  (`streams.sdk.make.variants`), so the variant set stays small and apps can
  add variants without touching generator code.
- **`src/Console/Make/Paths.php`** — `Paths::fromConfig(?string $variant)` now
  merges the variant's reference templates over `Paths::DEFAULTS` (GroupVitals
  reference layout) and then the explicit config
  (`streams.sdk.make.paths`) on top, so config always wins and the reference
  app defaults remain the fallback. All output locations resolve from config;
  no hard-coded app paths outside `Paths::DEFAULTS`.
- **`src/Console/Commands/StreamsMake.php`** already builds with
  `Paths::fromConfig()`, so variant-aware resolution flows into `streams:make`
  automatically.

## Acceptance evidence

| Criterion | Result |
| --- | --- |
| Test overrides a path/namespace in config and build writes there | `StreamsMakeTest::test_config_path_overrides_are_where_the_build_writes` — config `streams.sdk.make.paths` root `packages/Domains`, namespace `Packages\Domains`; full `streams:make` run writes `packages/Domains/People/Panel/Tables/PeopleTable.php` with the overridden namespace |
| Version detection returns variant id; faked versions resolve different reference sets | `VariantsTest::test_faked_versions_resolve_different_reference_sets` — Laravel 11 vs 12 with different variant config resolve different roots/namespaces |
| Grep finds no hard-coded app paths in generator code | `NoHardCodedPathsTest` scans `src/Console/Make` + `src/Console/Commands`; only `Paths.php` may contain `app/Domains`/`App\Domains` (it owns the reference defaults) |
| Full suite | `vendor/bin/phpunit` — **148 tests, 3895 assertions, OK** (1 pre-existing skip) |
| PHPStan | Not present in this package (`vendor/bin/phpstan` unavailable); no phpstan config in repo |