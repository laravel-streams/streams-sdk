# Task 130 evidence — Named transforms for reference-based generation

Deliverables:

- `transform-tests.txt` — the new transform unit tests plus the golden test:
  **7 tests, 25 assertions, all passing**.
- `golden-test.txt` — just `TransformGoldenTest` in isolation: 1 test, 6
  assertions. It generates a People table class from the GroupVitals
  reference fixture, checks `php -l`, runs Pint twice (second pass is
  byte-for-byte identical), and compares the result to the golden file.
- `full-suite.txt` — full `vendor/bin/phpunit` run: 48 tests, 1932 assertions,
  **0 errors / 0 failures** (1 pre-existing skip: Make Addon).
- `implementation.diff` — the git diff for this task (15 files, +799).

Acceptance check:

1. Transforms `renameClass`, `setNamespace`, `addMember`, `removeMember`,
   `addAttribute` exist (under `src/Console/Support/Transforms`) and each has
   a unit test that pins the minimal diff the transform produces on the
   format-preserving printer.
2. Generating a People table class from the reference fixture
   (`tests/Fixtures/References/v1/app/Domains/People/Panel/Tables/PeopleTable.php`)
   matches the golden file `tests/Fixtures/Golden/transforms/PeopleTable.php`.
   The recipe: `SetNamespace` + `RenameClass` (idempotent — the reference
   already carries the target identity), `AddAttribute(Table, ['people'])`,
   and `AddMember(heading())`.
3. The generated output passes `php -l` (asserted in `TransformGoldenTest`)
   and passes Pint; a second Pint pass leaves the file byte-for-byte
   identical, so repeated generations converge.
4. All new files pass `resources/bin/pint.phar --test`.

Notes:

- Golden files are reviewed expectations. The golden People table differs from
  the verbatim reference only by the SDK attribute, the added `heading()`
  member, and Pint's canonical import ordering — the reference fixture is
  intentionally not reformatted in place.
- Scope: no changes to `streams/ui`, no changes to the reference fixtures, no
  `streams:build` wiring (#133) and no `stub:publish` (#142), which are
  explicitly out of scope for this task.
