# task 140: `streams:make --menu` page menu

Interactive page menu over the app tree. `--menu` lists the pages in
`streams.json` by dot path, asks for one, lists that page's components by
path, and saves or overrides a component's settings into the tree. Save and
override write the node's settings into the tree (existing `TreeEditor::apply`
merge) — overriding a stock blueprint *is* settings on that node, never PHP.
Building stays the separate build step; a second run with the same answers
changes nothing.

Non-interactive equivalents: `--menu --json` (no recipe file) and
`--menu --no-interaction` print the `{pages: [{path, components}]}` listing
without prompting. The non-interactive save/override is the existing
`streams:make <path> --no-interaction --flags` / `--json=recipe.json` path;
the same command three ways writes the same tree. `--menu` rejects a
combined path, recipe, or authoring flags (json field `menu`).

Commands: `streams:make` only — no new command, no `streams:build`/`make:*`.

## Changes (commit 50c0aea)

- `src/Console/Commands/StreamsMake.php` — `--menu` option, dispatch in
  `handle()`, `pageMenu()` (guard, listing, interactive pick, collect via
  `DefinitionCollector`, `TreeEditor::apply`, json payload with `action`).
- `src/Console/Make/PageMenu.php` — `pages()` and `components()` helpers
  (registered-kind fit per node type, stream node excluded, sorted by path).
- `tests/Console/Commands/StreamsMakeMenuTest.php` — 5 tests, 81 assertions:
  json + no-interaction listing parity, faked-prompt save, faked-prompt
  override with a second-run no-op, menu == flags == json-recipe tree parity,
  and path/recipe/flags rejection.
- `docs/commands.md` — streams:make page-menu section.

## Results

- `phpunit-menu-define.txt`: menu tests green (OK, 5 tests, 81 assertions)
  alongside the existing define tests.
- Full suite: `Tests: 148, Assertions: 3857, Skipped: 1` — OK.
- `git-diff.txt`: diff of the command and the new helper against base
  `2d6647c`.