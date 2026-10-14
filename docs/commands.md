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
| [`streams:list`](#streamslist) | Lists registered streams by delegating to `entries:list` |
| [`entries:list`](#entrieslist) | Paginated table of a stream's entries |
| [`entries:show`](#entriesshow) | Shows one entry as a field and value table |
| [`streams:validate`](#streamsvalidate) | Checks definitions against the JSON Schema and the running app |
| [`streams:schema`](#streamsschema) | Writes per-stream `{id}.schema.json` entry schemas and the app-definition schema |
| [`streams:make`](#streamsmake) | Compares the app JSON tree with generated code, prints a plan, then adds or edits files |
| [`streams:livewire`](#streamslivewire) | Generates Livewire 3 index, form, and show components |
| [`streams:tap`](#streamstap) | Calls a tap URL with query-string input |

Agents can call the same operations through the [MCP server](/docs/mcp) (`php artisan mcp:start streams`), which needs `laravel/mcp` and Laravel 11.45+ or 12.41+.

`make:stream`, `make:entry`, `make:addon`, and `make:table` are documented here so the class behavior stays findable. They are not registered. `php artisan` does not list them.

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
php artisan streams:make {path?} {--list} {--json=file} {--menu} {--tree=} {--output=} {--dry-run}
php artisan streams:make --list
php artisan streams:make --menu
php artisan streams:make people.actions.add --label="Add person" --type=modal --form=people.actions.add.form
php artisan streams:make people.actions.add --json=recipe.json
```

`streams:make` is the only registered command for making and building. Component kinds are nodes in the app tree, addressed by dot path (`people`, `people.table`, `people.pages.manage`, `people.actions.add`). There is no `streams:build` command and no per-component `make:page`, `make:action`, or `make:form` command. `make:stream`, `make:entry`, `make:addon`, and `make:table` are command classes in this package and are not registered. `php artisan list` shows `streams:make` and no `make:*` command from streams-sdk. Laravel's own `make:*` commands are unchanged.

### What can be made

With no path, or with `--list`, the command prints each makeable kind and the dot paths where it fits. Patterns use the stream handle from the loaded tree (`people.actions.{handle}`, `people.pages.{handle}`, `people.table`). Existing nodes are listed too (`people.actions.add`, `people.pages.manage`). `--list --json` prints one object with a `kinds` array. Each entry has `kind`, `source`, `description`, `paths`, `nodes`, and `inputs` (`field`, `flag`, `required`, `default`, `prompt`).

### Defining a node

Interactive prompts, flags, and a JSON recipe fill one validated definition and edit the JSON tree at that dot path. They do not write PHP. The build below runs only when you are not authoring: `--dry-run`, or `--json` with no recipe file, and no field flags.

Every prompt comes from the kind's input schema. The same definition supplies the JSON key, the prompt label, the flag, and the default. The `handle` default is the last segment of the dot path (`people.actions.add` defaults handle to `add`). Omit a flag to keep that default. `--no-interaction` never prompts. A missing required value exits non-zero and prints JSON naming the field:

```json
{
    "message": "Invalid component definition.",
    "field": "label",
    "errors": [
        {"field": "label", "message": "The label field is required."}
    ]
}
```

`--json=recipe.json` is the recipe. The file is either the node object or `{"path": "people.actions.add", "input": { ... }}`. Field flags override recipe keys. Unknown fields, invalid JSON objects, and a recipe path that differs from the command path produce a JSON field error before writing. `--dry-run` with a recipe or flags prints the edit and does not write the JSON file.

```bash
php artisan streams:make people.actions.add --no-interaction --label="Add person" --type=modal --form=people.actions.add.form
php artisan streams:make people.actions.add --json=add.json --dry-run
```

### The page menu

```bash
php artisan streams:make --menu
php artisan streams:make --menu --json
php artisan streams:make --menu --no-interaction
```

`--menu` opens an interactive menu over the page nodes in `streams.json`. It lists the pages by dot path, asks for one, lists that page's components by path (every node under the page's stream that a registered kind fits, including the page itself), asks for one, and asks whether to save or override the component's settings into the tree. `save` and `override` both write the component's settings into the tree at that dot path — overriding a stock blueprint *is* putting settings on that node, not generating code.

After the component is chosen, the menu prompts exactly like defining a node, validates the answer, and writes only the JSON tree (`streams.json` and its `$ref` files). It never writes PHP: building remains the separate build step, and a second run with the same answers leaves the tree unchanged.

`--menu --json` (no recipe file) and `--menu --no-interaction` print the same listing as machine-readable JSON without prompting: one object with a `pages` array, each entry holding `path` and `components` (`path`, `type`, `kind`). The non-interactive equivalent of a menu save/override is `streams:make <path> --no-interaction --flags` or `--json=recipe.json` on the same command; all three write the same tree. `--menu` cannot be combined with a path, a recipe, or authoring flags.

### Component kinds

### Renaming a node

```bash
php artisan streams:make rename people.actions.add people.actions.create
php artisan streams:make rename people.actions.add people.actions.create --dry-run --json
```

Rename is a mode of `streams:make`; it registers no command. It accepts one pair of distinct sibling map-entry paths. The source must exist and the destination must be absent. It changes the map key while retaining its `$ref`, then edits the existing PHP `#[Generated]` tag, generated `nodePath()` return, and `Node:` comment through the format-preserving editor. Nested generated nodes (such as the action's form) follow the new prefix. Files and class names stay in place; the lock maps the new dot paths to those existing files and their updated hashes. Subsequent builds use that mapping.

All edits are planned before any write. Missing lock entries, destination collisions, missing tags, parse errors, or hand edits reject the rename without writing. On a write failure, original bytes are restored. `--dry-run` returns the planned paths without changing the tree, code, or lock. Recipes, authoring flags, and `--list` cannot be combined with rename. Moving between parents and bulk renames are not supported; references inside node content are retained as authored.

The build report includes `still in code, no longer in the config`, a list of objects with `path` and `file`, alongside the compatible `skipped` file list. The text report uses the same heading. Removing a node by hand leaves its PHP bytes untouched; no orphan is deleted.

### Component kind registration

Kinds are definitions in `Streams\Sdk\Console\Make\Kinds\KindRegistry`. They are not commands. `php artisan list` shows `streams:make` and does not gain a `make:*` command when a kind is registered.

The SDK registers five kinds:

| Kind | Dot path | What it is |
|------|----------|------------|
| `stream` | `people` | Stream handle, name, and fields |
| `page` | `people.pages.manage` | Control panel page |
| `table` | `people.table` | Table |
| `action` | `people.actions.add` | Action. `type` is `button`, `link`, `modal`, or `dropdown`. `modal` requires `form` |
| `manage` | `people.pages.manage` + `people.table` + `people.actions.add` | Chained manage page: the page, the table it shows, and a modal add action |

Each kind has an input schema. The same definition supplies the JSON key, the prompt, and the flag. `Kind::validate()` returns a list of errors, and each error names its `field`.

An app or a package adds a kind without a new command:

```php
use Streams\Sdk\Console\Make\Kinds\InputField;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;

$kind = Kind::define('badge', 'A badge on the stream.', [
    InputField::string('handle')->required()->prompt('Badge handle')->flag('handle'),
    InputField::string('label')->required()->prompt('Badge label')->flag('label'),
])->placedOn('ui', 'badges', map: true, mapOf: 'badge');

app(KindRegistry::class)->registerFromPackage($kind, 'example/badges');
```

`registerFromSdk()`, `registerFromApp()`, and `registerFromPackage()` are the registry API. Config does the same when the registry is first resolved:

```php
// config: streams.sdk.make.kinds
return [
    'app' => [$appKind],
    'packages' => [
        'example/badges' => [$packageKind],
    ],
];
```

`placedOn()` adds a tree container. `streams:make people.badges.star` then resolves that dot path to the `badge` kind. `--json` includes a `kinds` object keyed by dot path: `kind`, `source`, `package` when the kind came from a package, and `errors` from the input schema. Kind errors are reported there. They do not by themselves change the file plan.

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
| `conflicts` | Tagged files whose hash does not match `streams.lock`. Each item has `path`, `file`, and `reason`. The file is left unchanged |
| `diff` | The unified diff of created and modified files |
| `kinds` | Component kinds for the nodes in scope. Each value has `kind`, `source`, `errors`, and `package` when a package registered the kind |

A second run with no tree changes reports `created: []` and `modified: []`.

The build stage only adds and edits. It never deletes a file. Removing a node from the tree leaves the generated file where it is and lists that file in `skipped`. An edit that cannot be applied (the file is not managed, it does not parse, or the generated class or `label()` method is missing) is listed in `unapplied` and the file is left untouched.

Each generated class carries `#[Generated('people.actions.add')]` (`Streams\Sdk\Attributes\Generated`), keyed on that node's dot path, plus the `Generated by streams/sdk` marker and a `Node:` line. Apply writes `streams.lock` at the output root. The lock maps dot path to relative file to the sha256 of the file bytes. Keys are sorted, so two runs with the same tree write the same bytes. A file whose hash matches the lock still belongs to the build and can be edited when the tree changes. A hand edit changes the hash: `streams:make` reports `conflict:` with the dot path and the file, and leaves that file unchanged. It does not update the lock hash for a conflict. `--dry-run` does not write `streams.lock`.

If the lock or a file's lock entry is missing, the build can regenerate that managed file and restore its hash. Preserve `streams.lock` to retain hand-edit detection; deleting it removes the recorded baseline.

Paths and the namespace come from `config('streams.sdk.make.paths')`. When that config is absent, paths match the reference layout:

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

#### People manage pages

The people thin slice is pinned in `tests/Fixtures/PeopleMake/streams.json` and
`tests/Fixtures/Golden/people-make`. It emits a `PanelPage` with a heading and
table, a `Table` with text columns and an `entries('people')` query, and a
`MountableAction` that opens the generated stream form in a modal. The
GroupVitals snapshots in `tests/Fixtures/References/v1` supply the builder
shape; organization-specific models, authorization, stats, and business
actions are not copied into the scaffold. Add submission and authorization
behavior for your application before using the scaffold in production.

The command accepts the same tree via configured defaults or explicit
`--tree`/`--output`. Three invocation scopes are tested against identical
goldens: the whole tree, `people`, and `people.pages.manage`. A manage-page
scope includes its table and the stream's add action/form dependencies.
`people.table` generates only the table. `people.actions.add` generates the
modal and its form, and attaches the modal to an existing manage page if one
is present; it does not create a page implicitly.

For an existing app page, attachment uses the format-preserving PHP AST
editor. It appends a registration to `boot()` and a resolution to the single
Heading `actions` array in `getComponents()`, reusing the action import or
alias when present and otherwise using its fully qualified class name. The
page must already use `InteractsWithActions`. Unrelated statements and
comments stay untouched. Missing methods, ambiguous headings, parse failures,
and empty action arrays with comments are reported as `unapplied`; the page
is left unchanged. A lock hash protects the edited page on subsequent runs,
including pages that do not carry the SDK ownership marker. This does not
change the no-delete rule or the command registration surface.

Generated builders expose `generatedLabel()` for metadata rather than
overriding their parent builder's `label()` setter.

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
| `make:stream` | Write a stub `streams/{id}.json` | Implemented, not registered. Use `streams:make` and the `stream` kind. |
| `make:entry` | Create or update an entry | Implemented, not registered. |
| `make:addon` | Scaffold an addon package | Implemented, not registered. |
| `make:table` | Make a table stream and register it in a panel or page | Implemented, not registered. The `table` kind is data on `streams:make`. |
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
