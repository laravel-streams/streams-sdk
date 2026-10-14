<?php

namespace Streams\Sdk\Tests\Console\Support;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Tests\SdkTestCase;

class PhpEditorTest extends SdkTestCase
{
    public function test_editing_one_array_entry_changes_only_that_entry()
    {
        $original = (string) File::get(__DIR__.'/../../Fixtures/PhpEditor/registry.php');

        $editor = PhpEditor::fromString($original, 'tables.php');

        $changed = $editor->put('posts', ['panel' => true, 'page' => true]);

        $this->assertTrue($changed);

        $diff = $editor->diff($original);

        // The only +/- lines are the single flag line inside the posts entry.
        $this->assertStringContainsString('-        \'panel\' => true,', $diff);
        $this->assertStringContainsString("+        'panel' => true, 'page' => true,", $diff);

        // No other entry, key, or surrounding structure is added or removed:
        // every other +/- line belongs to the posts entry's flag change.
        $this->assertSame(["-        'panel' => true,", "+        'panel' => true, 'page' => true,"], $this->changedLines($diff));
    }

    public function test_noop_edit_prints_the_file_byte_for_byte()
    {
        $original = (string) File::get(__DIR__.'/../../Fixtures/PhpEditor/registry.php');

        $editor = PhpEditor::fromString($original, 'tables.php');

        $changed = $editor->put('posts', ['panel' => true]);

        $this->assertFalse($changed);

        $this->assertSame($original, $editor->print());
    }

    /**
     * The added/removed lines in a unified diff, in order.
     */
    protected function changedLines(string $diff): array
    {
        $lines = [];

        foreach (explode("\n", $diff) as $line) {
            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                $lines[] = $line;
            }

            if (str_starts_with($line, '-') && ! str_starts_with($line, '---')) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
