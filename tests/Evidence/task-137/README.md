# Task 137 evidence — streams:capture

Starting revision: `65f42bc` (parent of this task's first commit). Local
commits were added on top (`f50855d`, `7e17ac5`, `86e4687`); nothing was
pushed. No streams/ui or streams-core changes; GroupVitals
(`~/Sites/_gv/app`) was not touched. All app outputs used disposable temp
directories.

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
- `test_literal_settings_including_null_and_negative_numbers_are_captured`
  covers the review finding on literal classification: a literal `null`
  (single-arg call and static property default) and a negative numeric
  literal (`-1`) are captured as data, not opaque; a `make()` call names its
  container item and no longer leaks a fake `make: true` setting; and
  assignment scaffolding (`$name = $this->getName()`) never leaks an
  empty-handle opaque node.
- `test_unreadable_builder_files_are_reported_not_silently_dropped` covers
  the review finding on silent builder loss: a builder file that fails to
  parse, and one with no class, are reported by `streams:capture` (file +
  reason) instead of being dropped silently, while the healthy builders are
  still captured and the tree still validates.
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

Review-finding response (this revision): capture no longer silently drops
builders — unreadable files are tracked with a reason and warned by the
command, and builder calls inside `if`/`foreach` control flow are walked
instead of discarded. Literal classification is exact: literal `null` and
signed numeric literals are data; assignments never produce opaque nodes;
`make()` calls are item markers, not settings. The `TransformGoldenTest`
golden was regenerated from the reference after the fixtures gained the
synthetic classifier edge cases (null / negative / integer array); the
reference fixture imports were left untouched to stay faithful to the
GroupVitals source.

`captured-streams.json` is the actual capture of the people reference
fixtures. `git-diff.patch` is the full implementation/test diff from the
starting revision. `pint.txt` is Laravel Pint scoped to the changed source
and test files (passed). `full-suite.txt` is the raw full-suite run.

Suite notes:

- Full PHPUnit gate with `php -d error_reporting=22527 vendor/bin/phpunit
  --colors=never`: 132 tests, 3,544 assertions, zero errors/failures, one
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
the local commits in the worktree (`git revert 86e4687 7e17ac5 f50855d`).
