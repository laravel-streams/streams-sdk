# Front-end types from stream definitions — recommendation

Task: #143 (streams-sdk) · Advisor: OpenCode · Date: 2026-10-14
Branch: ops/run-120-a267-bc59a195 (worktree run-120) · Ships into `1.0` after review.

## Problem being decided

The SDK tree already ships machine-readable *definitions*: per-stream entry JSON
Schemas (`{id}.schema.json`), the app-definition schema (`app.schema.json` plus the
per-node `$ref` schemas), and the committed `resources/schemas` copies. Task #65
(project 17, api-client repo) already generates *api-client types* from
`streams:schema`. This research decides what the SDK emits for front-end types and UI
definitions **without** duplicating #65, using the Spatie
`typescript-transformer` / `laravel-data` model as the reference approach.

## What the SDK emits vs what task #65 owns

**The SDK owns the durable, versioned source of truth** — the JSON Schema artifacts it
already writes through `streams:schema`:

- Per-stream entry schema: `{id}.schema.json` for every registered stream
  (`people.schema.json`, `posts.schema.json`, ...).
- App-definition schema: `app.schema.json` and the per-node `$ref` schemas
  (`node/table.schema.json`, `node/action.schema.json`, `node/page.schema.json`, ...),
  validated by `streams:validate`.
- The committed mirror under `resources/schemas` that ships in the package and is
  served at `https://streams.dev/schema/...` (the `$id`/`$schema` targets).

The SDK must NOT generate front-end types itself. That would fork the source of truth:
two generators producing entry types and drifting apart on the next field change — the
exact failure the SDK already guards against with the app-definition drift check.

**Task #65 owns the type generation** — in the api-client repo, it consumes the schema
output above and produces the front-end type layer for the Streams API client. That
repo is where TypeScript type definitions, generated builders, and client parameter
typing live. Do not re-implement them here.

## Reference model

Spatie's `typescript-transformer` reflect over real PHP classes/DTOs and emit TypeScript
types so front-ends and back-ends agree on one model. But it reflects over **classes**,
whereas Streams definitions are *declarative JSON trees* (`streams.json` + per-stream
`streams/*.json`). There are no PHP classes to reflect over for entry shapes — the entry
shape *is* a JSON Schema produced by Core's `StreamSchema` (`stream->schema()` tag
metadata merged with its object schema). So the SDK's equivalent of a "transformer" is
already complete and is the `streams:schema` command, whose output is exactly the
`json` contract #65's generator reads. The SDK does not need `spatie/laravel-data` or
`spatie/laravel-typescript-transformer` as a dependency: there are no `Data` DTO
classes in the SDK to transform, and adding one would introduce a second source of
truth.

Decision, one line: **the SDK emits only its existing JSON Schemas; #65's generator is
the consumer and is the only front-end type emitter. The SDK makes no code change.**

## Concrete example type

Given a `people.json` stream definition with fields `id` (uuid, required), `name`
(string, required), `email` (email, required, unique), `streams:schema` writes
`people.schema.json`:

```json
{
    "$schema": "https://streams.dev/schema/streams.schema.json",
    "id": "people",
    "type": "object",
    "title": "People",
    "description": "The people stream.",
    "required": ["id", "name", "email"],
    "properties": {
        "id":     { "handle": "id", "type": "string", "format": "uuid" },
        "name":   { "handle": "name", "type": "string" },
        "email":  { "handle": "email", "type": "string", "format": "email", "unique": true }
    }
}
```

Task #65 consumes that schema and emits the api-client's TypeScript type:

```ts
/** people */ // from people.schema.json
export interface People {
    id: string;       // uuid / required
    name: string;     // required
    email: string;    // email / required / unique
}
```

The SDK's contribution ends at the JSON Schema; #65's begins where it converts that
schema into exported types. No streams/ui change is proposed, and no change to #65's
repo is proposed here — the boundary is purely one of ownership and is documented below.

## Follow-up implementation task (for Editor to file)

**Title:** Document the SDK ↔ #65 type-generation boundary in `docs/commands.md`.

**Business goal:** An agent (or human) reading the SDK docs must be able to answer
"who generates front-end entry types?" in one sentence without guessing — without
duplicating generation logic.

**Done when:**

- `docs/commands.md`, in the `streams:schema` section, states that the per-stream entry
  schemas written by `streams:schema` are the machine-readable contract for frontend
  entry types, and that **type/TS generation is owned by the api-client repo (task
  #65/`streams-api` project), not by this SDK**.
- No new command, artifact type, or package dependency is added; no streams/ui change
  and no streams/core change.
- `composer test:affected` passes (the app-definition drift test and schema tests still
  green).
- Evidence: the added sentence plus the passing test output are committed under
  `evidence/task-<n>/` with this task's number.

## Out of scope / accepted

- No change to the streams UI package (streams/ui).
- No change to streams-core; no change to the api-client repo (#65 owns that).
- No production changes, deploys, or releases; no commits pushed (left for review).
- GroupVitals remains read-only reference.

## Verification gate

This deliverable is a recommendation report attached as an artifact on this task. No
code changes were needed; the recommendation passed this research review step and is
ready for the Editor to file the follow-up task above.
