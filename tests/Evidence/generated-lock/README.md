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
