---
title: Command reference
nav_title: Command reference
description: 'Every Artisan command in streams/sdk: what it writes, its arguments and options, JSON output for agents, and the command classes that are not registered.'
section: packages
package: sdk
order: 20
tags: [sdk, commands]
status: ready
---

`streams/sdk` adds Artisan commands to generate and check streams, entries, addons, schemas, and Livewire components. Install it as a dev dependency:

```bash
composer require --dev streams/sdk:1.0.x-dev
```

Commands are registered only when the app runs in the console. Run `php artisan list` to see what your installed version provides.

## Available commands

| Command | What it does |
|---------|--------------|
| [`make:stream`](#makestream) | Writes a validated `streams/{id}.json` |
| [`make:entry`](#makeentry) | Creates or updates an entry in the stream's source |
| [`make:addon`](#makeaddon) | Scaffolds an addon package in `addons/{vendor}/{name}/` |
| [`streams:list`](#streamslist) | Lists registered streams by delegating to `entries:list` |
| [`entries:list`](#entrieslist) | Paginated table of a stream's entries |
| [`entries:show`](#entriesshow) | Shows one entry as a field and value table |
| [`streams:validate`](#streamsvalidate) | Checks definitions against the JSON Schema and the running app |
| [`streams:schema`](#streamsschema) | Writes per-stream `{id}.schema.json` entry schemas and the app-definition schema |
| [`streams:make`](#streamsmake) | Compares the app JSON tree with generated code, prints a plan, then adds or edits files |
| [`streams:livewire`](#streamslivewire) | Generates Livewire 3 index, form, and show components |
| [`streams:tap`](#streamstap) | Calls a tap URL with query-string input |

Agents can call the same operations through the [MCP server](/docs/mcp) (`php artisan mcp:start streams`), which needs `laravel/mcp` and Laravel 11.45+ or 12.41+.

### make:stream

```bash
php artisan make:stream {id} {--name=} {--description=} {--force}
```

Writes `streams/{id}.json`. The ID must be snake_case (lowercase letters, numbers, and underscores, starting with a letter), and it is also the file name. `--name` defaults to the ID word-cased (`blog_posts` becomes "Blog Posts"). The file sets `config.source.format` to `json` without a `source.type`, so the app's default adapter is used, and it has one `id` field of type `uuid` with `config.default: true`.

The definition is validated (the same checks as [`streams:validate`](#streamsvalidate)) before it is written, then registered. The command refuses to replace an existing file unless you pass `--force`, and it refuses an ID that the app or an addon already registers.

```bash
php artisan make:stream blog_posts --description="Articles on the blog."
# Stream created: streams/blog_posts.json
```

The file starts with `"$schema": "https://streams.dev/schema/streams.schema.json"`. That URL is served by this site at [/schema/streams.schema.json](/schema/streams.schema.json), so editors that understand `$schema` validate and autocomplete the file.

### make:entry

```bash
php artisan make:entry {stream} {input?} {--update}
```

Creates an entry in `{stream}`. `input` is query-string formatted (`title=Hello&status=draft`). The command prompts for every field you didn't provide, validates the input against the stream's rules, then saves. With `--update`, validation treats the entry as existing, and if the input includes the stream's key (`key_name`, default `id`) and that entry exists, its attributes are updated.

```bash
php artisan make:entry posts "title=Hello&status=draft"
php artisan make:entry posts "id=hello&status=live" --update
```

Validation errors are printed and nothing is saved. On success the saved entry is printed as JSON.

### make:addon

```bash
php artisan make:addon {vendor/name} {--description=} {--force}
```

Scaffolds a Composer package under `addons/{vendor}/{name}/` with a `composer.json` and a service provider at `src/{Name}Provider.php`. The name must be a valid Composer package name. Without `--description` the command asks for one. It refuses to overwrite an existing addon unless you pass `--force`.

```bash
php artisan make:addon acme/reviews --description="Product reviews."
# Created: addons/acme/reviews/composer.json
# Created: addons/acme/reviews/src/ReviewsProvider.php
```

Add the directory as a Composer path repository to install it. See [Addons](/docs/addons).

### streams:list

```bash
php artisan streams:list {--query=} {--show=id,name,description} {--per-page=15} {--page=}
```

Lists entries on the `core.streams` stream by calling [`entries:list`](#entrieslist) with the same `--query`, `--show`, `--per-page`, and `--page` options. There is no `--json` flag. `--show` is a comma-separated field list and defaults to `id,name,description`.

### entries:list

```bash
php artisan entries:list {stream} {--query=} {--show=} {--per-page=15} {--page=}
```

Prints page totals and a table of entries from `{stream}`. `--query` is a `|`-separated list of `method:arg,arg` criteria calls. With no `--show`, the columns are the stream's field handles. When another page exists, the command asks before continuing.

### entries:show

```bash
php artisan entries:show {stream} {entry} {--show=}
```

Prints one entry as a Field / Value table. `{entry}` is the stream's key. `--show` is a comma-separated field list; with no `--show`, every field is included.

### streams:validate

```bash
php artisan streams:validate {paths?*} {--json}
```

Validates stream definitions. With no paths it checks every `streams/*.json`. Each file is checked in three steps, and later steps run only when earlier ones pass:

1. The [stream definition JSON Schema](/schema/streams.schema.json) (the same file `$schema` points at).
2. The running app: field types must be registered, `extends` must name a registered stream, adapter and model classes must exist, and `related` must sit inside `config`. A related stream that is not registered yet is a warning. So is an `@` import that does not resolve.
3. A real build with `Streams::build()`.

```bash
php artisan streams:validate
php artisan streams:validate streams/posts.json streams/authors.json --json
```

The table output ends with `N checked, M invalid.`. `--json` prints an object keyed by file, each with `valid`, `errors`, and `warnings`. The exit code is non-zero when any file is invalid, so the command works as a CI or pre-commit check.

### streams:schema

```bash
php artisan streams:schema {--include=} {--exclude=} {--path=}
```

Writes a JSON schema (`{id}.schema.json`) for the *entries* of every registered stream, built from Core's `StreamSchema`: the stream's tag metadata merged with its object schema. This is not the definition schema that `$schema` points at. It also writes the *app-definition* schema into `schemas/` under the same path: the root `app.schema.json` and the per-node `$ref` schemas that validate a `streams.json` app tree. `--include` and `--exclude` take comma-separated stream IDs. `--path` is relative to the project root and defaults to it; an absolute path is used as-is. Streams whose entry schema cannot be generated are skipped with a warning.

```bash
mkdir -p storage/schemas
php artisan streams:schema --include=posts,authors --path=storage/schemas
```

The app-definition schema is the same set of committed files that ships in the SDK's `resources/schemas`. Each node type has its own `$ref` schema (`node/table.schema.json`, `node/action.schema.json`, ...) and every node is addressed by a dot path starting at the stream handle: `people`, `people.table`, `people.actions.add`, `people.pages.manage`.

### streams:make

```bash
php artisan streams:make {path?} {--tree=} {--output=} {--dry-run} {--json}
```

`streams:make` is the only build command. Component kinds are nodes in the app tree, addressed by dot path (`people`, `people.table`, `people.pages.manage`, `people.actions.add`). There is no `streams:build` command and no per-component `make:page`, `make:action`, or `make:form` command.

The command loads the app tree (`streams.json` plus its `$ref` files), generates the code into an in-memory file tree, and diffs that tree against the working directory. `--dry-run` prints the unified diff and writes nothing. The plan from a dry run is the plan the real run applies: the same created, modified, skipped, and unapplied files.

```bash
php artisan streams:make --dry-run
php artisan streams:make people.table --dry-run --json
php artisan streams:make --json
```

`--json` prints one object:

| Key | Meaning |
|-----|---------|
| `created` | Files that do not exist yet |
| `modified` | Managed files whose contents differ and can be edited |
| `skipped` | Generated files left in place because their node was removed from the tree |
| `unapplied` | Edits that were not written. Each item has `path` (dot path), `file`, and `reason` |
| `diff` | The unified diff of created and modified files |

A second run with no tree changes reports `created: []` and `modified: []`.

The build stage only adds and edits. It never deletes a file. Removing a node from the tree leaves the generated file where it is and lists that file in `skipped`. An edit that cannot be applied (the file is not managed, it does not parse, or the generated class or `label()` method is missing) is listed in `unapplied` and the file is left untouched.

Generated files are marked `Generated by streams/sdk` and record their node on a `Node:` line. Paths and the namespace come from `config('streams.sdk.make.paths')`. When that config is absent, paths match the reference layout:

```text
app/Domains/{Domain}/Panel/Tables/{Class}.php
app/Domains/{Domain}/Panel/Pages/{Class}.php
app/Domains/{Domain}/Panel/Actions/{Class}.php
app/Domains/{Domain}/Panel/Resources/{Class}.php
app/Domains/{Domain}/Panel/Forms/{Class}.php
app/Domains/{Domain}/Public/Pages/{Class}.php
```

Tokens are `{root}`, `{Domain}`, and `{Class}`. `{root}` defaults to `app/Domains` and the namespace defaults to `App\Domains`. `config('streams.sdk.make.tree')` is the `streams.json` to load (default `streams.json` in the app base). `config('streams.sdk.make.output')` is the directory generated paths are written under (default the app base). `--tree` and `--output` override those for one run.

Class names are the StudlyCase of the full dot path (`people.table` becomes `PeopleTable`), except a panel node, which uses the reference suffix (`people.panel` becomes `PeoplePanelResource`).

### streams:livewire

```bash
php artisan streams:livewire {stream} {--type=all} {--force}
```

Generates Livewire 3 components for a stream. `--type` is `index`, `form`, `show`, or `all` (the default). The components use the stream's repository and criteria, so they work with any source adapter.

| Type | Class | What it does |
|------|-------|--------------|
| `index` | `{Stream}Index` | Paginated, sortable table with a delete action |
| `form` | `{Stream}Form` | Create and edit form, validated with rules taken from the stream's fields |
| `show` | `{Stream}Show` | Read-only view of one entry |

Classes go in `config('livewire.class_namespace')` (default `App\Livewire`, so `app/Livewire/BlogPostsIndex.php`). Views go in `resources/views/livewire/`, named after the class in kebab case (`blog-posts-index.blade.php`). Protected fields are never rendered, and a generated `integer` or `uuid` key is left out of the form.

The command stops without writing anything if any target file exists, unless you pass `--force`. It does not register routes. It prints the routes to add to `routes/web.php`:

```php
Route::get('/blog-posts', \App\Livewire\BlogPostsIndex::class)->name('blog_posts.index');
Route::get('/blog-posts/create', \App\Livewire\BlogPostsForm::class)->name('blog_posts.create');
Route::get('/blog-posts/{entry}/edit', \App\Livewire\BlogPostsForm::class)->name('blog_posts.edit');
Route::get('/blog-posts/{entry}', \App\Livewire\BlogPostsShow::class)->name('blog_posts.show');
```

The components are full-page, so they render inside your Livewire layout (`config('livewire.layout')`). Use [Streams UI](/docs/ui/introduction) instead when you want a configured control panel rather than files you own and edit.

`streams:admin` has been removed. Generate the components with `streams:livewire` and put them behind your own layout and routes.

### streams:tap

```bash
php artisan streams:tap {tap} {input?}
```

Calls `{tap}` with HTTP GET. `{tap}` must be an `http` or `https` URL. `{input}` is an optional query string (`name=Widgets&status=draft`). Those pairs are merged onto the URL's query string, and keys in `{input}` win. The command prints the response body. A non-2xx response, or a URL that cannot be reached, is an error and the command exits non-zero. Redirects are not followed.

```bash
php artisan streams:tap https://example.com/taps/widgets "name=Widgets&status=draft"
```

## Not registered

These command classes exist in the SDK source but are **not registered**, so `php artisan` won't find them:

| Command | Intended purpose | State |
|---------|------------------|-------|
| `streams:show` | Show one stream's attributes | Implemented, registration commented out. Use the MCP `describe-stream` tool. |
| `streams:describe` | Write `streams/{id}.json` by inspecting a URL, JSON, database table, or Eloquent model | Implemented, registration commented out |

## How to extend it

`make:entry` prompts for each missing field through a console input bound as `streams.console.inputs.{type}`. `SdkServiceProvider::registerInputs()` binds string, boolean, select/enum, array, and object inputs. `number`, `decimal`, `date`, `time`, and `datetime` use the string input. `integer` is listed twice in that map; the later entry wins, so `integer` is also prompted as a string and `IntegerConsoleInput` is never bound. To prompt for a custom field type, set the full map in `config('streams.console.inputs')`. The config value replaces the default map, so copy the defaults from `registerInputs()` and add yours:

```php
// config/streams/console.php
return [
    'inputs' => [
        // ...the SDK defaults...
        'money' => \App\Console\Inputs\MoneyConsoleInput::class,
    ],
];
```

## Related

- [SDK introduction](/docs/sdk/introduction)
- [MCP server](/docs/mcp)
- [Stream definition schema](/docs/sdk/stream-schema)
- [Core streams](/docs/core/streams)
