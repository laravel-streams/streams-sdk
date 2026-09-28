# Streams SDK

`streams/sdk` is the development toolkit for Laravel apps built on [Streams](https://streams.dev): Artisan generators, stubs, stream definition validation, and a local MCP server that gives AI agents a machine interface to your app.

Install it as a dev dependency. It is not needed in production.

> **Naming:** in Streams, **SDK** means this PHP package: Artisan tooling plus the MCP server. The **client** is the JavaScript API client, [`@laravel-streams/api-client`](https://www.npmjs.com/package/@laravel-streams/api-client), which talks to the HTTP API from `streams/api`. The SDK is not a client library.

# Installation

## Downloading

This package is added to Laravel projects as a dependency by requiring it with Composer.

```bash
composer require --dev streams/sdk:1.0.x-dev
```

The Streams SDK comes pre-configured with the [Streams starter application](/docs/installation) and some of the [examples](/docs/examples).

To use the MCP server, also install [`laravel/mcp`](https://github.com/laravel/mcp) (Laravel 11.45+ or 12.41+):

```bash
composer require --dev laravel/mcp
```

Everything except the MCP server works on Laravel 10 through 12.

## Updating

From within your project, use Composer to update this individual package:

```bash
composer update streams/sdk --with-dependencies
```

You can also update your entire project using `composer update`.

# Commands

| Command | Description |
|---------|-------------|
| `make:stream {id} [--name=] [--description=] [--force]` | Write a validated stream definition to `streams/{id}.json`. Refuses to overwrite unless `--force` is passed. |
| `make:entry {stream} {input?}` | Create an entry, prompting for missing fields. |
| `make:addon {vendor/name} [--description=] [--force]` | Scaffold an addon package in `addons/`. |
| `streams:list [--json]` | List registered streams. |
| `streams:schema [--include=] [--exclude=] [--path=]` | Write a JSON Schema for each stream's entries. |
| `streams:validate [paths...] [--json]` | Validate stream definitions. Defaults to `streams/*.json`. |
| `streams:livewire {stream} [--type=all\|index\|form\|show] [--force]` | Generate Livewire 3 index, form, and show components and views for a stream, and print the routes to add. Needs `livewire/livewire` ^3 in the app. |

## Livewire components

`php artisan streams:livewire posts` writes `app/Livewire/PostsIndex.php`, `PostsForm.php`, and `PostsShow.php` (the namespace follows `livewire.class_namespace`) and matching views under `resources/views/livewire/`. The components use the stream's repository, so they work with any source. Form rules come from the stream's field rules and types; protected fields are not shown or bound. Nothing is registered for you: add the printed routes (named `posts.index`, `posts.create`, `posts.edit`, `posts.show`) to `routes/web.php`. Full-page components render inside the Livewire layout (`livewire.layout`). Existing files are kept unless `--force` is passed.

## Example streams

`php artisan vendor:publish --tag=examples` copies the example definitions in [`streams/`](streams) (`blog_posts`, `products`, `contacts`, `docs`, `files`) into your app. Single examples publish with `--tag=blog-example`, `ecommerce-example`, `crm-example`, `docs-example`, or `files-example`. They use flat-file JSON storage so they boot without models or migrations; point `config.source` at Eloquent when you add a model. Some relationship fields name streams the examples do not ship (for example `users`), which `streams:validate` reports as warnings.

# MCP server for AI agents

With `laravel/mcp` installed, the SDK registers a local MCP server that agents (Claude Code, Cursor, Codex, and others) can use to inspect streams, read and write entries, validate and create stream definitions, search the docs, and run the generators:

```bash
php artisan mcp:start streams
```

For example, with Claude Code, run this from the app root:

```bash
claude mcp add streams -- php artisan mcp:start streams
```

See [docs/mcp.md](docs/mcp.md) for the tool list, client configuration, and settings.

# Stream definition JSON Schema

[`resources/schemas/streams.schema.json`](resources/schemas/streams.schema.json) describes `streams/*.json`. Point your editor at it, or validate with `php artisan streams:validate`. See [docs/stream-definition-schema.md](docs/stream-definition-schema.md).

# Templates

Publish templates with the `vendor:publish` command:

```bash
php artisan vendor:publish --tag=examples
```

Or target specific example templates:

```bash
php artisan vendor:publish --tag=files-example
```
