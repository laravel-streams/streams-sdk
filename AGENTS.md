# Agent notes

Guidance for agents working in this package. Everything here is advisory
except the verification steps at the bottom, which run before a task is
declared done.

## Where things live

- Commands: `src/Console/Commands/` (`streams:tui`, `streams:make`, `streams:capture`,
  `streams:validate`, `streams:schema`, `streams:tap`, `streams:list`,
  `entries:*`). `streams:tui` is the human menu (Streams, then a stream landing). `streams:make` is the **only** build command. There is no
  `streams:build` command and no per-component `make:*` command.
- Component kinds: `src/Console/Make/Kinds/` — registry data, not commands.
- Build reports and fix providers: `src/Console/Make/Reports/` (`BuildReport`,
  `FixRegistry`, `Fixes/RegenerateConflictFixProvider`).
- Command reference: `docs/commands.md`. If you change a command signature or
  output, update that file in the same change and run the doc example test
  (`tests/Console/Commands/DocExamplesTest.php`), which executes the
  copy-pasteable examples from the docs and must stay green.

## The JSON recipe flow

There is one data artifact, the app tree (`streams.json` plus its `$ref`
files), and two commands that move it:

1. `php artisan streams:capture` reads existing builder settings
   (`app/Domains/*/Panel/{Tables,Pages,Actions,Resources,Forms,Public}`) and
   writes `streams.json`. Literal settings become data; closures, permission
   callbacks, class resolvers, and computed options become **opaque nodes**
   (with `file` and `line`) that `streams:make` never rewrites.
2. `php artisan streams:make` edits that tree (prompts, flags, `--json=file`
   recipe, `--menu`, `rename` mode) and builds generated PHP from it.

For a build preview use `--dry-run --json`: `--json` alone selects JSON
output and still writes PHP and `streams.lock`. Capture's `--json` prints
the captured tree without writing. Prefer
`--no-interaction` and flags or a recipe over interactive prompts in scripts
and agent tool calls.

Keep the tree and `streams.lock` together. Build never deletes files;
inspect `unapplied`, `conflicts`, and `report.failed` even on exit code zero.
`--fix --dry-run` previews solutions; `--fix` regenerates conflicted managed
files and discards their hand edits. Preserve wanted edits before applying.

## Style

- Follow [Spatie's Laravel/PHP guidelines](https://github.com/spatie/guidelines.spatie.be/blob/master/content/code-style/laravel-php.md)
  and the [Spatie agent skills](https://github.com/spatie/guidelines-skills).
  Pint enforces the automated formatting rules on changed PHP files; review
  Laravel conventions and design guidance separately.
- Docs use the front-matter and heading conventions in `docs/`.
- Keep changes in the reference layout (`app/Domains/{Domain}/Panel/...`),
  the dot-path addressing (`people.actions.add`), and the report keys
  (`created`, `modified`, `fixed`, `skipped`, `unapplied`, `conflicts`).

## Verify before done

- `php artisan test` (or the focused file) for the touched tests, then the
  affected suite when the package CI gate requires it.
- `vendor/bin/phpstan analyse --memory-limit=1G` on the whole package when it
  is available, and Pint on changed files.
- Attach test output and the git diff to the task evidence.
