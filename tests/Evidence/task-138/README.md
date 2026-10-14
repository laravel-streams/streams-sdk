# Task 138 evidence

Starting revision: `65f42bc` (the parent of this task's first commit).
No production files, sibling packages, source checkout, or host processes
were changed. All app outputs used disposable directories under the system
temp.

Review reconciliation (prior review findings):

- "command reporting is incomplete": the command now prints applied edits
  as `reason: dot.path relative/file`, and failed edits (unapplied and
  conflicts) with reason and the provider-suggested change attached, e.g.
  `conflict: people.actions.add app/.../PeopleActionsAdd.php (hand edit;
  hash does not match streams.lock) — regenerate ... from the app tree`.
  `BuildReport::renderText()` renders the same list, and the JSON payload
  carries `report.applied`, `report.fixed`, and `report.failed`.
- "--fix loses the record of applied edits": `--fix` no longer re-plans
  into a fresh, empty report. `BuildReport::recordFixed()` keeps every edit
  a fix solution applied, `setPlan()` swaps in the re-planned tree without
  dropping that record, and the `fixed` list (with path, file, reason, and
  suggested change) is rendered in both text and `--json`. The end-to-end
  command test and `FixRegistryTest::test_recorded_fixes_are_kept_when_the_report_replans`
  cover this.
- evidence packet: this directory attaches the PHPUnit output
  (`feature-tests.txt`, `full-suite.txt`), the sample reports
  (`sample-reports.json`, `preview-report.json`, `fixed-report.json`,
  `fixed-report.txt`), `git-diff.patch`, and the verification tool outputs.

The report lists every applied edit (created + modified), every edit a fix
provider applied (fixed), and every edit that was not applied (unapplied +
conflicts), each with path, file, reason, and suggested change, in text and
`--json`. Fix providers mirror Spatie's `HasSolutionsForThrowable` with
`canSolve(FailedEdit)` / `getSolutions(FailedEdit)`; `FixSolutionContract`
extends Spatie's `Solution` and `RunnableSolution` interfaces only when
spatie/ignition is installed (composer `suggest`, never `require`). The
sample `RegenerateConflictFixProvider` solves the hand-edit conflict by
regenerating the file from the compiled tree and refreshing `streams.lock`.
`--fix` applies the solutions; `--fix --dry-run` previews them without
writing.

Acceptance reconciliation:

- Report lists applied and failed edits with path, file, reason and
  suggested change, in text and `--json`:
  `FixRegistryTest` unit tests plus `StreamsMakeTest` text-output and JSON
  tests; cases cover a clean build, a hand-edit conflict preview, and an
  applied fix.
- Sample provider solves the hand-edit conflict; `--fix` applies it and
  `--dry-run` previews it: `StreamsMakeTest::test_fix_regenerates_a_hand_edited_conflict_and_dry_run_previews_it`
  asserts the file is untouched in preview, regenerated on `--fix`, the
  `streams.lock` hash is refreshed, and `report.fixed` records the edit.
- composer.json does not require spatie packages for the engine: spatie
  appears only under `suggest` (optional Ignition/Flare integration).
- Full PHPUnit gate: 132 tests, 3,413 assertions, zero errors/failures,
  one existing skipped `MakeAddonTest::test_it_makes_addons`.

Verification interfaces:

- This is a package tested with PHPUnit/Testbench; no `artisan` file
  exists. The requested `php artisan test` invocation was attempted and
  recorded in `artisan-requested.txt`.
- `composer test:affected` was attempted; composer.json has no scripts and
  Composer reports no commands in the `test` namespace. It did not run a
  test suite. The full `vendor/bin/phpunit` suite ran instead (see
  `full-suite.txt`).
- `vendor/bin/phpstan` does not exist in this package. The PATH PHPStan
  executable analysed all `src` files: 14 findings, all in files unchanged
  by this task (PhpEditor/new-static parser API drift and similar). No
  changed file appears in its findings; the new report code is clean at
  level 5 with `--level=5`. These are not PHPUnit errors or failures.
- Pint passes on every changed file (`pint.txt`). The supplied PHP runtime
  produces existing dependency deprecation notices; raw output is
  preserved and PHPUnit exits zero.

`sample-reports.json` (dry + real clean build), `preview-report.json`
(`--fix --dry-run` over a hand edit), and `fixed-report.json` /
`fixed-report.txt` (`--fix`, text and JSON) are actual command reports,
reproduced by `capture.php`. `git-diff.patch` contains the implementation,
tests, and evidence diff from the starting revision.

Review boundary: implementation and evidence are prepared for the required
review. No review approval is claimed by the implementing agent. Ryan or
the designated reviewer must approve this evidence before task 138 is Done.
Rollback is to revert the task's local commits; no commits were pushed.