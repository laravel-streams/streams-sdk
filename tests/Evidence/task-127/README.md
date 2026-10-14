# Task 127 acceptance evidence

Implementation is committed locally on the assigned worktree branch; nothing was pushed.

- Versioned fixtures: five verbatim people-slice files under `tests/Fixtures/References/v1`, with a README naming each source path, source commit, role, and SHA-256. Each was compared against both the committed source and working copy before copying.
- Golden harness: `tests/Support/GoldenFiles.php` runs a callable in an isolated temporary directory, compares recursive file sets and exact contents, and reports unified diffs. It cleans up after passing output, mismatches, and generator exceptions. Missing/extra empty files fail; symlinks are rejected without following their targets.
- Focused tests: `golden-tests.txt` records 8 passing tests and 132 assertions.
- Full suite: `full-suite.txt` records 28 tests, 1,596 assertions, 0 errors/failures, and 1 existing skipped `MakeAddonTest::it_makes_addons` test. Command: `php -d error_reporting=22527 vendor/bin/phpunit --colors=never`. The PHP 8.4 dependency deprecations remain visible where existing suite setup re-enables them. The initial unadjusted command produced deprecations without a test summary; that output is preserved in `golden-tests-default-php.txt` and is not claimed as a test pass.
- GroupVitals: `groupvitals-before.txt` and `groupvitals-after.txt` are identical. Existing unrelated modifications were preserved. No GroupVitals files were written.
- Additional checks: `additional-checks.txt` records PHP syntax checks passing for both new PHP files. This SDK checkout has no `artisan` entrypoint and no installed Pint or PHPStan executables, so those requested checks could not run. No dependencies were changed to introduce these tools.
- Diff: `implementation.diff` contains the complete fixture and harness implementation diff against base `16c62f85ee0513b796e5d87982e822429df8eee4`, excluding this evidence directory to avoid recursive evidence inclusion.
- Scope: changes are limited to SDK tests, fixture documentation, and evidence; no streams/ui, streams-core, production, or release changes.
- Review gate: evidence approval is still required before Done. No approval is claimed. The installed OpenCode adapter returned no models from `opencode models`; no affordable review model was exposed through that command. An authorized reviewer must approve this evidence.

No preview server or other persistent process was started by this attempt.
