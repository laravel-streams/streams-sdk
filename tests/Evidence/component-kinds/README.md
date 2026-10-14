# Component kinds for streams:make

`streams:make` is the only registered build command. Component kinds are registry data (`KindRegistry`), not commands.

Built-in kinds:

- `stream` at `people`
- `page` at `people.pages.manage`
- `table` at `people.table`
- `action` at `people.actions.add` (`type` `modal` requires `form`)
- `manage` expands to the page, the table, and a modal add action

The SDK, an app, and a package register kinds with `registerFromSdk`, `registerFromApp`, and `registerFromPackage` (or `config('streams.sdk.make.kinds')`). A package kind placed on the tree is reached by `streams:make {dot.path} --json` in `kinds`.

`make:stream`, `make:entry`, `make:addon`, and `make:table` are no longer registered. `MakeTableTest` registers `MakeTable` for its own run so the generator class still has coverage.

Evidence:

- `phpunit.txt` — full suite, 108 tests, 3003 assertions, 1 pre-existing skip, exit 0
- `artisan-list.txt` — `streams:make` is listed; no streams-sdk `make:*` command
- `implementation.diff` — the code diff before this evidence directory was added
