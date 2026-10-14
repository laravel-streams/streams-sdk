# Generated tag and streams.lock

`streams:make` tags each generated class with `#[Generated('dot.path')]` and records `dot path -> file -> sha256` in `streams.lock`. A matching hash is the build's. A hand edit is a conflict: the command reports the path and the file and leaves the file unchanged.

## Evidence

- `phpunit.txt` — full `vendor/bin/phpunit` with vendor deprecation lines removed. **69 tests, 2612 assertions, 0 errors, 0 failures, 1 skipped.** The skip is the pre-existing Make Addon test.
- `implementation.diff` — `git diff 0bde6d9..8860f96` (the implementation commit; this evidence directory is not in it).
- `streams.lock` — the People fixture lock after two identical runs. The two files were byte-identical (2012 bytes).
- `PeopleActionsAdd.php` — the built class for `people.actions.add`, including `#[Generated('people.actions.add')]`.
- `conflict.txt` and `conflict.json` — after `label()` was changed to `Added by hand`, apply reported the conflict and left the file and the lock unchanged.

## Acceptance

1. Built files carry `#[Generated(path)]`, and `streams.lock` records path, file, and hash (`StreamsMakeTest::test_dry_run_json_matches_the_real_run_plan`, `StreamLockTest`, `PeopleActionsAdd.php`, `streams.lock`).
2. A hand edit of a tagged member is reported with the dot path and the file, and the file is left unchanged (`StreamsMakeTest::test_a_hand_edit_to_a_tagged_member_is_a_conflict_and_the_file_is_left_unchanged`, `conflict.txt`).
3. `streams.lock` is byte-identical across two runs (`StreamsMakeTest::test_streams_lock_is_byte_identical_across_two_runs`).
4. A file whose hash still matches the lock is rebuilt when the tree changes (`StreamsMakeTest::test_a_matching_lock_hash_is_replaced_when_the_tree_changes`).
5. The attribute class is `Streams\Sdk\Attributes\Generated` in streams-sdk. streams-core is unchanged. `streams:make` stays the only build command.

`vendor/bin/phpstan` is not installed in this package. Changed PHP files were formatted with `resources/bin/pint.phar`.

## Review fix (2026-10-14)

Commit `85b79a3` addresses the blocking review finding: a missing lock entry
returns false from `BuildStage::isHandEdit()`, so a managed tagged file can
regenerate and restore its hash. The command documentation explains that
preserving the lock preserves the baseline for hand-edit detection.

- `phpunit-review-fix.txt` contains the complete full-suite output, including
  existing vendor deprecations: 71 tests, 2690 assertions, zero errors/failures,
  and the same pre-existing Make Addon skip.
- `focused-review-fix.txt` contains the generated attribute, lock, and command
  tests: 17 tests, 713 assertions, all passing.
- `review-fix.diff` contains the complete implementation diff against the task
  base, excluding evidence files.
- Pint passed for both changed PHP files. The requested PHPStan invocation
  could not run because `vendor/bin/phpstan` is absent.

The two new command tests cover deleting the entire lock and removing only
the action file's lock entry. Both verify a dry run leaves files and lock
unchanged, apply regenerates the tagged class with its new label and correct
hash, and the next run leaves the lock byte-identical. The existing hand-edit
test still verifies a recorded hash mismatch reports the dot path and file
and leaves both file and lock unchanged.

Acceptance reconciliation: class tags and path/file/hash mapping remain
covered by the attribute and lock tests and the attached sample files;
hand-edit conflicts and unchanged bytes pass; deterministic lock bytes pass;
the full suite and focused tests pass with output attached. The required
Operations review must approve this updated evidence before task closure.
No second command, streams-core, or streams-ui changes are included.
