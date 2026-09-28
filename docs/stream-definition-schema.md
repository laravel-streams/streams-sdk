# Stream definition JSON Schema

Stream definitions are the JSON files in `streams/` (for example `streams/posts.json`). The SDK publishes a [JSON Schema](https://json-schema.org) (draft-07) for them at:

```
vendor/streams/sdk/resources/schemas/streams.schema.json
```

Its `$id` is `https://streams.dev/schema/streams.schema.json`. That is also the `$schema` URL that `make:stream` and the `make-stream` MCP tool write into new definitions.

## What it checks

- Top-level keys: `id`, `name`, `description`, `extends`, `config`, `fields`, `rules`, `route`, `routes`, `ui`.
- `config.source`: known source types (`filebase`, `file`, `self`, `database`, `eloquent`, `filesystem`, `collection`, `elasticsearch`, `opensearch`, or an adapter class) and formats (`json`, `yaml`, `md`, `html`, `tpl`, `csv`).
- Both field forms: a list of field objects with `handle`, or an object keyed by handle whose values are a field object or a type string.
- Field types: core types are offered for completion. Other lowercase names are allowed because apps and addons can register their own types.
- Type-specific config: `relationship` fields need `config.related` (one stream ID, or a list of IDs for polymorphic and multi-target fields); `select`, `enum`, and `multiselect` fields need `config.options`; `eloquent` sources need `config.source.model`.
- `data`: inline entries for a `self` source.
- `input` on a field: a form hint for streams/ui. streams/core does not read it.

Unknown top-level keys are allowed because addons extend definitions.

Two shapes show up in older examples and are ignored by streams/core. `streams:validate` reports them:

- `"related"` next to `"type"`. The value has to be `config.related`. `streams/ui`'s `cp.navigation` stream still uses the root-level key.
- `"default"` next to `"type"`. The value has to be `config.default` (for a uuid field, `"default": true` generates a UUID).

## Validate from the command line

```bash
php artisan streams:validate                       # every streams/*.json
php artisan streams:validate streams/posts.json    # specific files
php artisan streams:validate --json                # machine-readable output
```

The command exits non-zero if any file is invalid. Besides the schema, it checks the running app:

- field types are registered,
- `extends` streams exist,
- `config.related` streams exist (a warning, since you may be creating them together),
- model and adapter classes exist, and
- streams/core can build the definition.

Agents connected to the [MCP server](mcp.md) get the same checks from `validate-stream-definition`, and the schema from `definition-schema`.

## Use it in your editor

Add `$schema` to a definition:

```json
{
    "$schema": "https://streams.dev/schema/streams.schema.json",
    "name": "Posts",
    "fields": []
}
```

Until the schema is hosted at that URL, map it to the local file. In VS Code, add this to `.vscode/settings.json`:

```json
{
    "json.schemas": [
        {
            "fileMatch": ["**/streams/*.json"],
            "url": "./vendor/streams/sdk/resources/schemas/streams.schema.json"
        }
    ]
}
```
