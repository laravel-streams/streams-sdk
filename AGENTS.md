# Streams SDK

`streams/sdk` is the development toolkit for Laravel apps built on [Streams](https://streams.dev): Artisan generators, stream-definition validation, and a local MCP server for AI agents. Install it as a dev dependency. It is not needed in production.

**SDK** means this package (PHP tooling plus the MCP server). **Client** means the JavaScript package [`@laravel-streams/api-client`](https://www.npmjs.com/package/@laravel-streams/api-client), which talks to the HTTP API from `streams/api`.

## Layout

- `src/Console` — Artisan commands. Generators delegate to `src/Support` so the CLI and the MCP tools share one implementation.
- `src/Mcp` — the local MCP server (`php artisan mcp:start streams`). Registered only when `laravel/mcp` is installed and the app is not in production.
- `src/Support` — stream inspection, definition validation, entry reads and writes, docs search, and generators.
- `resources/schemas/streams.schema.json` — JSON Schema (draft-07) for `streams/*.json`. Its `$id` is `https://streams.dev/schema/streams.schema.json`.
- `resources/config/sdk.php` — MCP settings, published with the `streams-sdk-config` tag.
- `tests/fixtures` — streams, entries, and docs copied into a temporary Testbench app for each test.
- `docs/mcp.md` and `docs/stream-definition-schema.md` — how to connect an agent, and how to validate definitions.

## Runtime constraints

- `laravel/mcp` stays in `suggest`, not `require`. Laravel 10 apps must still be able to install this package. `SdkServiceProvider::registerMcpServer` skips registration when `Laravel\Mcp\Server` is missing, when `streams.sdk.mcp.enabled` is false, or when `APP_ENV=production` unless `streams.sdk.mcp.allow_production` is set.
- `opis/json-schema` is a runtime dependency because `streams:validate` works without MCP.
- Package tests use `orchestra/testbench-core` ^10 (Laravel 12), `laravel/framework` ^12.41, and `laravel/mcp`. Do not point them back at `streams/testing`: that package pins `orchestra/testbench` ^8, which cannot install `laravel/mcp`. `testbench-core` is used instead of the `orchestra/testbench` meta package because the meta package requires `symfony/yaml` ^7 and `streams/core` requires `symfony/yaml` ^6.
- MCP tools must not read stdin. The stdio transport owns it, so generators cannot prompt.
- Tools that write entries or files use the `WritesToApplication` trait. `STREAMS_MCP_READ_ONLY=true` hides them.
- Expected failures (unknown stream, missing entry, invalid definition) must come back as a tool error the agent can read. `StreamsTool` turns `InvalidArgumentException` into `Response::error` and lets `ValidationException` propagate, which `laravel/mcp` renders as a tool error. Other throwables are reported and returned as an error message even when `app.debug` is off.

## Commands

| Command | Use it for |
|---|---|
| `streams:list --json` | Registered streams. Same data as the `list-streams` tool. |
| `streams:validate [paths...] --json` | Schema check, then the running app (field types, related streams, a test build). Works on Laravel 10 without MCP. |
| `streams:schema` | Write each stream's entry JSON Schema to disk. The `entry-schema` tool returns one schema. |
| `make:stream {id} [--name=] [--description=] [--force]` | Validated `streams/{id}.json`. Refuses to overwrite unless `--force` is passed. |
| `make:addon {vendor/name} [--description=] [--force]` | Addon scaffold. Non-interactive when `--description` is passed. |
| `make:entry {stream}` | Interactive entry prompt. Agents should use the `create-entry` tool instead. |

`streams:describe` and `streams:tap` live under `src/Console/Commands` and are not registered. `streams:describe` introspects a database table or Eloquent model and was written against an older API. The supported way to inspect a registered stream is the MCP `describe-stream` tool (and the `streams://streams/{id}` resource). Do not register that command without rewriting it.

## MCP server

With `laravel/mcp` installed:

```bash
php artisan mcp:start streams
```

Tools: `list-streams`, `describe-stream`, `entry-schema`, `definition-schema`, `validate-stream-definition`, `list-entries`, `read-entry`, `create-entry`, `update-entry`, `delete-entry`, `search-docs`, `read-doc`, `make-stream`, `make-addon`.

Resources: `streams://schemas/streams.schema.json`, `streams://streams/{id}`. Prompt: `design-stream`.

Client configuration (Claude Code, Cursor, VS Code, Codex) is in `docs/mcp.md`. Do not write this server into a personal global agent config from automation; register it per app.

`search-docs` indexes local markdown only (`docs/`, `vendor/streams/*/docs`, `streams/data/*docs`). It makes no network requests. `read-doc` only reads a document the index already returned.

## Stream definitions

Validate before writing `streams/*.json`, with `php artisan streams:validate` or `validate-stream-definition`.

- Relationship targets go in `config.related`: one stream ID, or a list of IDs. A `related` key beside `type` is ignored by streams/core, and validation fails the definition.
- Defaults go in `config.default`. A `default` key beside `type` is ignored (validation warns). For a uuid key, `"config": {"default": true}` generates a UUID.
- Field types must be registered. The schema lists core types and also allows other lowercase names, because addons register their own; the app check is what rejects an unknown type.
- New stream IDs are snake_case. `make-stream` will not write `../` or other paths.

## Tests

```bash
./vendor/bin/phpunit
```

Each test copies the Testbench skeleton to a temp directory and seeds it from `tests/fixtures`. Tests may write files there. Do not point them at a shared `streams/` directory or at `streams/testing` fixtures.

A throwaway Laravel 12 app that path-repositories this package and `streams/core` is the place to try `php artisan mcp:start streams` against a booted application. Do not use port 8000.
