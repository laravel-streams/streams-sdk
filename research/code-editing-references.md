# Code-editing references for Streams generators

Advisory note, 2026-10-13. No package here is a dependency. Nothing in this note edits application code.

Checked against published source on that date: nikic/php-parser 5.9.0 (2026-09-13) and 4.19.5 (2025-12-06), rectorphp/rector-src `main`, laravel-shift/cli `main` (archived), laravel-shift/cli-sdk `main`, laravel-shift/blueprint `master`, laracasts/generators 2.0.2, and way/generators 3.0.3. The Operations brief text was not in this workspace, and the artifact endpoint on the host app redirected to login, so the Jeffrey Way sentence is identified from the task wording plus these public packages.

## Pin nikic/php-parser at major 5

Pin `nikic/php-parser` to `^5.9`.

Major 5 is the line that still receives format-preserving printer fixes. 5.9.0 (2026-09-13) fixes attribute insertion on enums and enum cases. 5.8.0 (2026-06-04) fixes `_` as a label character in that printer. 5.6.2 (2025-10-21) fixes a visibility change on a node that already has attributes. 5.3.0 fixed a negative-indent `ValueError` in the same printer. Those are the failure modes below.

The last major 4 release is 4.19.5, on 2025-12-06, the same day as 5.7.0. No 4.x release contains the 5.8 or 5.9 printer fixes. Packagist shows no major 6. `^5.9` takes later 5.x fixes and stops before 6.

5.9.0 requires PHP `>=7.4`, `ext-tokenizer`, and `ext-json`. rector/rector-src on `main` already requires `nikic/php-parser: ^5.9` (and PHP `^8.4`). Matching that floor keeps a later Rector-style editor on the same printer.

The v5 factory is `ParserFactory::createForHostVersion()`, `createForNewestSupportedVersion()`, and `createForVersion()`. Tokens come from `Parser::getTokens()`. Major 4 still has `ParserFactory::ONLY_PHP7` and `create($kind, $lexer)`. The archived Shift CLI SDK still calls that v4 signature (`Lexer\Emulative` plus `ONLY_PHP7`). New Streams edits should follow the v5 docs, not the Shift SDK.

## Format-preserving print

Basic `prettyPrintFile()` reprints the whole tree in the printer's style and drops the original layout. Use it for files the tool is creating. Use `printFormatPreserving()` when the tool is changing a file a person already formatted.

The v5.9 pattern, from `doc/component/Pretty_printing.markdown` and `PhpParser\NodeVisitor\CloningVisitor`:

```php
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

$parser = (new ParserFactory())->createForHostVersion();
$oldStmts = $parser->parse($code);
$oldTokens = $parser->getTokens();

$traverser = new NodeTraverser(new CloningVisitor());
$newStmts = $traverser->traverse($oldStmts);

// Change $newStmts only. Leave $oldStmts and $oldTokens alone.

$printer = new Standard();
$newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
```

`CloningVisitor::enterNode()` clones every node and links the clone to the original:

```php
$node = clone $origNode;
$node->setAttribute('origNode', $origNode);
return $node;
```

The attribute name is `origNode`. `origTokens` is the third argument of `printFormatPreserving()`, not a node attribute. The printer stores it as `$this->origTokens = new TokenStream($origTokens, ...)`.

Two comparisons decide what is reprinted:

- `PrettyPrinterAbstract::p()` reads `$node->getAttribute('origNode')`. A missing `origNode` means the node is pretty-printed from scratch.
- The list differ treats two nodes as the same only when `$a === $b->getAttribute('origNode')` (object identity).

Mutating the parsed tree in place removes the diff. The original statements and the edited statements have to be different objects, tied together by `origNode`. The lexer must also have recorded `startTokenPos` and `endTokenPos`. `createForHostVersion()` does that. The v5 pretty-printing doc also says to turn off `replaceNodes` on the name resolver, so resolved names land in attributes instead of rewriting the tree and showing up as a formatting change.

`printFormatPreserving()` is best effort. If a list edit cannot be spliced, `pArray()` returns null and the printer falls back to a full `<?php` reprint of the file. The PHPDoc still says "getTokens() on the lexer". The current doc and Rector both call `Parser::getTokens()`. Follow that.

The printer options default `phpVersion` to 7.4. Nodes that are reprinted, rather than copied from original tokens, follow that default: short arrays, and parameter attributes laid out so PHP before 8.0 would treat them as comments (changelog 5.5.0). Pass an explicit `phpVersion` of 8.0 or newer when the inserted code is PHP 8.

## Pitfalls

### Comments

Comments sit on the following node. The printer treats them as part of that node's token range.

- Removing a node removes its comments. The removal start moves to the first comment's token.
- If the clone's comment list is not the same array as the original's, the printer runs `pComments()` and reformats them. Unchanged comments are copied from the original tokens.
- A comment that starts at the same position can be attached to more than one node. The printer skips a second print, and can also drop the comment if the positions disagree.
- A new node does not inherit nearby comments. Copy the `comments` attribute when a replacement should keep them.
- Orphan comments often live on `Nop` statements. Rector's printer deletes the blank line those nops leave behind (`EXTRA_SPACE_BEFORE_NOP_REGEX` in `BetterStandardPrinter`).
- A failed preservation falls back to a full reprint, which keeps only comments still attached to nodes and reformats them.

### Attributes

PHP 8 attributes are `attrGroups` lists. Adding one is a list insertion, not a token tweak.

- Classes, interfaces, traits, enums, enum cases, methods, functions, properties, class constants, and `const` statements insert attribute groups with a newline. Parameters, closures, arrow functions, and property hooks insert them with a single space.
- The empty-list map knows how to add the first attribute group on those declaration nodes. It did not do so reliably for enums and enum cases until 5.9.0, or when the visibility of an already-attributed node changed, until 5.6.2.
- The inserted attribute is a new node, so it is pretty-printed. The rest of the declaration can stay on original tokens only while the parent still has `origNode`.
- With the default `phpVersion` of 7.4, reprinted parameter attributes are placed on their own lines. Set `phpVersion` to 8.0 or newer for inline attributes.
- Stripping `origNode` (Rector does this on purpose, under the constant `AttributeKey::ORIGINAL_NODE`, whose value is the string `origNode`) forces a reprint. Do that only when the old tokens would be wrong, for example after a precedence change.

### Long arrays

`Expr\Array_` items use the insertion string `', '`.

- The printer switches that to a comma plus a newline only when every existing item is already separated by a newline, or the new item has a comment, or the parent is a `match`. `isMultiline()` returns false if any gap has no newline. A partially wrapped array is treated as one line, and a new item is appended on that line.
- Adding the first item to an empty `[]` is an explicit TODO. `Expr\Array_->items` and `Expr\List_->items` are absent from `emptyListInsertionMap()`. That edit fails preservation and can reprint the file.
- `kind` chooses `array()` (`KIND_LONG`) or `[]` (`KIND_SHORT`). A clone keeps `kind`. A brand-new item is printed with the printer default, which is short syntax when `phpVersion` is 7.0 or newer. Set `kind` on new items so a long-array file does not gain a short item, and the reverse.
- Rector's `newlined_array_print` node attribute forces a multiline `[]` reprint. That is a workaround for the stock printer's single-line insertion, and it gives up original spacing inside that array.

## How Rector applies edits

Source: `rectorphp/rector-src` `main`, `composer.json` requires `nikic/php-parser: ^5.9`. The printer is `Rector\PhpParser\Printer\BetterStandardPrinter`, which extends `PhpParser\PrettyPrinter\Standard`.

For each file, `Rector\Application\FileProcessor`:

1. `RectorParser::parseFileContentToStmtsAndTokens()` parses with the PHPStan parser and reads `$innerParser->getTokens()`. On a retry it builds a parser with `ParserFactory::createForVersion(PhpVersion::fromString('7.0'))`.
2. A `NameResolver` runs with `replaceNodes` false and `preserveOriginalNames` true, so names are annotated and the printed source does not change just because a name was resolved.
3. Statements are wrapped in Rector's `FileNode` so rules can see the whole file.
4. `NodeScopeAndMetadataDecorator` resolves PHPStan scope, then runs `new NodeTraverser($cloningVisitor)`. The constructor comment says the clone is "needed for format preserving printing". The clone is `newStmts`. The pre-clone tree is `oldStmts`.
5. `RectorNodeTraverser` walks the clone and runs the rules registered for each node. A rule returns a replacement node, or null to leave it. Replacing a node with a different class stops descent into that node.
6. `BetterStandardPrinter::printFormatPreserving($newStmts, $oldStmts, $oldTokens)` unwraps `FileNode` and calls the php-parser printer. The file is written only when the printed string differs and this is not a dry run.

`BetterStandardPrinter` exists because the stock printer still mishandles comments on arrow functions, nop spacing, fluent calls, yield parentheses, heredoc indentation, and multiline arrays. It sometimes clears `origNode` so the parent printer reprints a node whose old tokens would now be the wrong precedence. Treat that class as the record of printer bugs, not as a Streams dependency.

## How Laravel Shift CLI applies edits

https://github.com/laravel-shift/cli is archived. Packagist marks `laravel-shift/cli` abandoned. The README says the tasks moved to the hosted Shift Workbench. Last push 2025-03-20. The published `composer.json` requires only PHP `^8.1`. Development uses `laravel-shift/cli-sdk` (https://github.com/laravel-shift/cli-sdk, last push 2024-06-03).

The CLI does not format-preserving-print. A task finds files, then writes them back with `file_put_contents`. Three patterns are in the tree:

- Regex replace. `ClassStrings` rewrites quoted class names to `::class` with `preg_replace_callback`.
- Parse for offsets, then splice the original string. `AnonymousMigrations` uses `Shift\Cli\Sdk\Parsers\NikicParser` to find the class span, then `substr_replace` and `str_replace` to turn `class Name extends Migration` into `return new class extends Migration`.
- Parse for line ranges, then reorder slices of the original lines. `OrderModel` reprints nothing. It cuts the original member text out of the line array and concatenates it back in a chosen order.

`NikicParser` is the v4 API: `new Lexer\Emulative(['usedAttributes' => ...])` and `ParserFactory::ONLY_PHP7`, then a `NameResolver` with `replaceNodes` false. It returns finder data (lines and offsets). It does not call `printFormatPreserving()`. `ONLY_PHP7` is gone in php-parser 5, so this SDK does not run on the version recommended above.

Shift is the reference for small, textual Laravel refactors where the edited span is a whole statement or a line range and the surrounding bytes should be copied unchanged. It is a weak reference for inserting a method or an attribute into an existing class.

## How Blueprint applies edits

https://github.com/laravel-shift/blueprint (`laravel-shift/blueprint`). Active. Pushed 2026-09-25. README: Laravel 11 or newer. It generates many files from one YAML draft. It does not use php-parser.

- Models, controllers, migrations, factories, tests, and similar outputs are stub strings. `ModelGenerator` fills `model.class.stub` and `AbstractClassGenerator::create()` calls `filesystem->put()` with the whole file. An existing model at that path is replaced by the generated file.
- Routes are appended. `RouteGenerator` builds route lines and calls `filesystem->append()` on `routes/web.php` or `routes/api.php`.
- `bootstrap/app.php` is a string replace, to uncomment or insert the `api:` route entry when `routes/api.php` was missing.

Blueprint is the reference for "one definition, many new files", plus a blunt append when the target is a route file. It is not a reference for editing an existing class in place.

## The Jeffrey Way package

The public package is **laracasts/generators**.

- Packagist: https://packagist.org/packages/laracasts/generators
- Source: https://github.com/laracasts/Laravel-5-Generators-Extended
- Latest stable: 2.0.2 (2023-03-22). Repository last pushed 2024-04-03.
- `composer.json` author is Jeffrey Way, `jeffrey@laracasts.com`. The only runtime requirement is `illuminate/support` for Laravel 6 through 10. There is no php-parser dependency.
- The README says it was created in 2015 by Jeffrey Way as the successor of `JeffreyWay/Laravel-4-Generators`, and that the Backpack for Laravel team has maintained it since 2017.
- The tree is `make:migration:schema` and `make:migration:pivot`, a schema string parser, and stubs. Those commands render a new migration file. `--model=true` asks Laravel to create a new model file. Nothing in the tree parses or patches an existing PHP file.

The predecessor is **way/generators**.

- Packagist: https://packagist.org/packages/way/generators (abandoned: true)
- Source: https://github.com/JeffreyWay/Laravel-4-Generators
- Latest: 3.0.3 (2014-10-01). Last push 2019-10-04.
- `Generator::make()` compiles a template and calls `Filesystem::make()`. `make()` throws `FileAlreadyExists` when the path is already there. There is no append or patch method. `generate:scaffold` only delegates to the model and controller generators, which also create files.

Neither package edits existing code. The schema flags in names such as `add_user_id_to_posts_table` change the contents of a newly generated migration. They do not open the existing `posts` migration or model. Do not take a dependency on either one. Streams generators that only create files can keep using stubs. Generators that must change an existing class should use the php-parser 5 pattern above, with Rector's printer as the bug list, not as a required library.

### Candidates checked

| Candidate | Result |
| --- | --- |
| `laracasts/generators` (`laracasts/Laravel-5-Generators-Extended`) | Jeffrey Way's successor package. New migration files only. |
| `way/generators` (`JeffreyWay/Laravel-4-Generators`) | His Laravel 4 package. Abandoned. Refuses to overwrite an existing file. |
| Packagist search "jeffrey way" | Flash notifiers, presenters, a validator extension, API-controller forks. No editor of existing PHP. |
| Jeffrey Way's public GitHub repositories (67, sorted by update) | No other generator that patches existing PHP. `Laravel-Model-Validation` validates on save. `Laravel-4-Generators` is the last generator, pushed 2019. |
| `laravel-shift/cli` and `laravel-shift/blueprint` | These do touch existing files. They are Jason McCreary's Shift projects, not Jeffrey Way's. |

A brief sentence that names a different package would change this identification. Under the names the task gave (`laracasts/generators`, `way/generators`), the package is identified, and it does not edit existing code.
