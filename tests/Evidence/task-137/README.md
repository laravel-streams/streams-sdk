# Task 137 evidence — streams:capture

Starting revision: `65f42bc` (parent of this task's first commit). Two local
commits were added on top (`f50855d`, `7e17ac5`); nothing was pushed.
No streams/ui or streams-core changes; GroupVitals (`~/Sites/_gv/app`) was
not touched. All app outputs used disposable temp directories.

Acceptance reconciliation:

- `StreamsCaptureTest::test_capturing_the_people_fixtures_produces_a_streams_json_that_validates`
  runs `streams:capture` against the Reference v1 people fixtures, then
  validates the written `streams.json` with `SchemaValidator` against
  `https://streams.dev/schema/app.schema.json` (#125 app schema), and loads
  it with `AppTree` to confirm the nodes are addressable
  (`people.table`, `people.panel`, `people.pages.list_people`,
  `people.actions.add_person_modal`).
- `test_closures_and_callbacks_become_opaque_nodes_with_file_and_line` proves
  the core behavior: translation calls (`__()`), permission callbacks,
  class resolvers, `route()`, and computed closures are captured as opaque
  entries `{handle, file, line}` and never as JSON data; literal builder
  settings (`->sortable()`, `->searchable([...])`, `->emptyStateIcon(...)`)
  become data in the node's `settings` map. Every opaque entry carries the
  source file and a real line number.
- `test_capture_then_build_produces_zero_changes_because_opaque_nodes_are_never_rewritten`
  is the capture→build 0-change gate. After capture, `streams:make` on the
  captured tree reports empty `created`, `modified`, `skipped`, `unapplied`,
  `conflicts`, and `diff` — the build writes only the ownership lock. With
  the original builder PHP files copied into the output first, a second full
  build still rewrites nothing: every file stays byte-for-byte intact
  (`BuildStage::isOpaqueSubtree` skips any code-bearing node whose subtree
  carries a non-empty `opaque` list).
- The captured tree nests nodes under each stream's `ui` container
  (table/panel single nodes; pages/actions/forms maps) so `AppTree::load`
  can address them, and plays nicely with the existing make/build pipeline.

`captured-streams.json` is the actual capture of the people reference
fixtures. `git-diff.patch` is the full implementation/test diff from the
starting revision. `pint.txt` is Laravel Pint scoped to the changed files
(passed). `full-suite.txt` is the raw full-suite run.

Suite notes:

- Full PHPUnit gate with `php -d error_reporting=22527 vendor/bin/phpunit
  --colors=never`: 130 tests, 3,516 assertions, zero errors/failures, one
  pre-existing skip.
- No streams/ui or streams-core edits; no pushes; local commits only.
- Repo has no `artisan` entrypoint and composer.json defines no scripts, so
  `composer test:affected` reports "There are no commands defined in the
  'test' namespace"; the full `vendor/bin/phpunit` suite is the gate.
- This package ships no `vendor/bin/phpstan` or `vendor/bin/pint`. PHPStan
  was run via the host binary with a scoped temp config against the changed
  files; its remaining findings are pre-existing (`SdkServiceProvider`,
  `BuildStage::scope()`) or artifacts of the host phar resolving a different
  php-parser API than the package's installed v4 parser. PHPStan is not a
  gate for this repository, and none of its real findings touch new code.

Review boundary: implementation and evidence are prepared for review; no
review approval is claimed by the implementing agent. Rollback is to revert
the two local commits (`git revert 7e17ac5 f50855d` in the worktree).