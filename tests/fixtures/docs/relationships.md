---
title: Relationship Fields
---

Relationship fields store the key of an entry in another stream.

Set the related stream with `config.related`:

```json
{"handle": "author_id", "type": "relationship", "config": {"related": "authors"}}
```
