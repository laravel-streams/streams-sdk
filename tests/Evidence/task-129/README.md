# Task 129 acceptance evidence — round-trip golden tests on real GroupVitals files

Implementation is committed locally on the assigned worktree branch; nothing was pushed.

- `golden-tests.txt` — the new `PhpEditorRoundTripTest`: 4 tests, 33 assertions, all green.
  The no-op round-trip test proves every GroupVitals reference PHP snapshot plus the
  `PhpEditor/registry.php` fixture parses and prints **byte-identical** with no edit.
- `edit-diffs.txt` — the three real-file edit diffs, generated through `PhpEditor`:
  1. Add a mountable action (`ExportPeopleModal::resolve()`) to the page's `actions([...])`
     array in `ListPeople.php`. Diff is a single `+` line; the commented-out
     `// UploadPeopleModal::resolve(),` line is preserved.
  2. Add an HTML attribute (`'x-cloak' => true`) to `mergeHtmlAttributes([...])` in
     `AddPersonModal.php`. Only that array's lines move.
  3. Append a route entry to the long `getPages()` array in `PeoplePanelResource.php`.
     Diff is a single `+` line; all seven existing page entries are untouched.
  Each test asserts the second identical edit is a no-op (`appendToArray` returns false,
  print stays byte-identical), and the no-op round trip asserts repeated prints are stable.
- `full-suite.txt` — the full `vendor/bin/phpunit` run: 45 tests, 1,940 assertions,
  **0 errors / 0 failures** (1 pre-existing skip: Make Addon).
- `implementation.diff` — the git diff for this task (commit `33f457f`), evidence directory
  excluded to avoid recursive inclusion.

## What changed

- `src/Console/Support/PhpEditor.php` — added `appendToArray(callable $locate, Node $item)`:
  an idempotent array append keyed by a caller-supplied locator that receives the candidate
  `Array_` and its ancestor-node stack. This is the minimal editor primitive the three
  real-file edits (and the upcoming `make:action` work) share.
- `tests/Console/Support/PhpEditorRoundTripTest.php` — the golden round-trip tests above.

## Scope

Changes are limited to the streams-sdk `PhpEditor` module and its tests. No streams/ui,
streams-core, production, or release changes. GroupVitals reference files are read-only
and were not written.

## Review gate

The review step must approve this evidence before Done. No approval is claimed here.
