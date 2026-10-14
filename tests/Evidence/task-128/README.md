# Task 128 evidence — PhpEditor module (parse, node edits, format-preserving print)

Deliverables:

- `full-suite.txt` — full `vendor/bin/phpunit` run: 31 tests, 1608 assertions,
  **0 errors / 0 failures** (1 pre-existing skip: Make Addon).
- `phpeditor-maketable.txt` — the PhpEditor, PhpFile, and MakeTable tests.
- `minimal-diff-test.txt` — the minimal-diff acceptance test
  (`PhpEditorTest::test_editing_one_array_entry_changes_only_that_entry`).
- `implementation.diff` — the git diff for this task (phar excluded; the
  bundled Pint phar is `resources/bin/pint.phar`, Pint 1.32.1).

Acceptance check:

1. `nikic/php-parser: ^4.14` is a direct `require` in `composer.json`.
2. `make:table` registers tables through `PhpEditor` (the new module);
   its idempotency tests still pass.
3. Editing one array entry in the `tests/Fixtures/PhpEditor/registry.php`
   fixture yields a diff whose only `+`/`-` lines are the edited flag line.
4. The write post-step runs Pint on generated PHP files, and a second Pint
   pass leaves the file byte-for-byte identical.
