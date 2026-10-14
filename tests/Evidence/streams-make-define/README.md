# streams:make tree editing

`streams:make` is still the only registered command. Prompts, flags, and a JSON recipe fill one definition and write it at a dot path such as `people.actions.add`. Those three modes produce the same JSON tree. `--list` prints makeable kinds and the dot paths where they fit. `--list --json` is one JSON object. `--no-interaction` with a missing required value exits non-zero and names the field. Editing the tree does not write PHP.

The build path is unchanged: `--dry-run`, or `--json` with no recipe file and no field flags, still plans and applies generated files.

Evidence:

- `phpunit.txt` — final full suite, 115 tests, 3128 assertions, exit 0, zero errors/failures. One pre-existing skip: `MakeAddonTest::it_makes_addons`.
- `targeted.txt` — all 7 command-definition tests pass, 125 assertions, exit 0.
- `list.txt` — `streams:make --list` against the people fixture.
- `list.json` — `streams:make --list --json` against the same fixture.
- `error.json` — `streams:make people.actions.add --no-interaction` (exit 1). The field is `label`.
- `implementation.diff` — the code and command-doc diff before this evidence directory.
- `lint.txt` — every changed PHP file passes `php -l`.
- `capture.php` — reproduces discovery and error samples against the fixture without editing it. `capture.txt` records list exit 0, JSON list exit 0, missing-field exit 1.

Pint and PHPStan were attempted and exited 127: `vendor/bin/pint` and `vendor/bin/phpstan` are absent from this package. No dependency changes were made. PHPUnit and PHP lint passed; dependency PHP deprecation notices are retained in the raw logs.

Acceptance reconciliation:

1. `test_prompts_flags_and_a_json_recipe_write_the_same_tree` removes the existing add node, creates `people.actions.add` in all three modes, and compares both the resolved tree and the written JSON bytes.
2. `test_list_prints_kinds_and_dot_paths_and_json_is_machine_readable` covers no-path discovery, `--list`, and parsed `--list --json`. Samples are attached above.
3. `test_no_interaction_with_a_missing_required_value_exits_with_a_json_field_error` asserts a nonzero status, the `label` field error, and unchanged JSON. Invalid recipe/type/conditional form errors are covered too.
4. `test_tree_editing_does_not_change_php_files` checks a PHP sentinel, the complete PHP file list, and absence of a build lock. Recipe dry runs also leave JSON untouched.
5. Final full PHPUnit suite and task tests pass. The implementation diff and raw output are attached. Existing command-registration tests still assert no per-component or second build command.
6. A separate read-only reviewer found no blocking issues; final evidence approval is recorded in `review.txt`.
