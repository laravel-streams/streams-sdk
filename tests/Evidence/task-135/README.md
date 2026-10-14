# Task 135 evidence

Starting revision: `a04a0ff` (the actual parent of this task's first commit).
The packet's historical `ee78ea04` object is absent from this worktree.
No production files, sibling packages, source checkout, or host processes
were changed. All app outputs used disposable directories.

Acceptance reconciliation:

- `PeoplePagesMakeTest::test_command_input_modes_match_the_people_golden_files`
  invokes the registered command in whole-tree, stream-path, and manage-path
  modes. All four PHP files (manage page, table, add modal, form) compare
  byte-for-byte with the committed golden directory. The configured/default
  and explicit path options are both covered.
- `test_add_action_is_attached_to_an_existing_manage_page_with_a_minimal_ast_diff`
  checks the complete existing page against exactly two inserted statements,
  reuses an imported alias, preserves comments/state/unrelated methods, and
  checks attachment dry-run parity and a no-op rerun.
- `test_second_run_preserves_every_byte_and_dry_run_matches_the_real_change_list`
  compares full reports and snapshots every output file including the lock.
- `test_unapplied_page_edits_are_reported_and_no_existing_file_is_deleted`
  covers missing methods, invalid PHP, ambiguous heading action arrays, and
  an empty commented array that the printer cannot safely edit. It asserts
  node path, file, reason, and preservation of every pre-existing app file.
- `test_hand_edits_to_an_attached_page_are_preserved_and_reported_as_conflicts`
  checks the lock protection for app-owned pages.
- Pint checks the changed implementation, tests, fixtures, and golden PHP.
- Full PHPUnit gate: 120 tests, 3,215 assertions, zero errors/failures,
  one existing skipped `MakeAddonTest::test_it_makes_addons`.

Verification interfaces:

- This is a package tested with PHPUnit/Testbench; no `artisan` file exists.
  The requested `php artisan test` invocation was attempted and recorded.
- `composer test:affected` was attempted; composer.json has no scripts and
  Composer reports no commands in the `test` namespace. It did not run a
  test suite. The full `vendor/bin/phpunit` suite ran instead.
- `vendor/bin/phpstan` does not exist in this package. The available PATH
  PHPStan executable analysed all 53 `src` files and reported 13 findings
  in unchanged files. Several are caused by the host tool loading a newer
  php-parser API than the package's installed v4 parser. No changed file
  appears in its findings. These are not PHPUnit errors or failures.
- The supplied PHP runtime produces existing dependency deprecation notices;
  raw output is preserved. PHPUnit exits zero.

`sample-reports.json`, `attachment-report.json`, and `unapplied-report.json`
are actual command reports, reproduced by `capture.php`. `git-diff.patch`
contains the implementation/test/docs diff from the starting revision.

Review boundary: implementation and evidence are prepared for the required
review. No review approval is claimed by the implementing agent. Ryan or the
designated reviewer must approve this evidence before task 135 is Done.
Rollback is to revert the task's local commits; no commits were pushed.
