<?php

namespace Streams\Sdk\Console\Support\Transforms;

use Streams\Sdk\Console\Support\PhpEditor;

/**
 * A small, named AST edit applied to a reference file.
 *
 * A transform loads nothing itself: it mutates the editable statements of
 * a PhpEditor and returns the same editor, so transforms compose in order:
 *
 *     $editor = PhpEditor::read($reference);
 *
 *     (new SetNamespace('App\Domains\Posts\Panel\Tables'))->apply($editor);
 *     (new RenameClass('PostsTable'))->apply($editor);
 *     (new AddAttribute('Streams\Sdk\Attributes\Table', ['posts']))->apply($editor);
 *
 *     $output = $editor->print();
 *
 * A transform that has nothing to edit is a no-op: it leaves the statements
 * untouched, so print() stays byte-for-byte identical (idempotent).
 */
interface Transform
{
    public function apply(PhpEditor $editor): PhpEditor;
}
