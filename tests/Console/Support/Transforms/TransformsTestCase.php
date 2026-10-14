<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use Streams\Sdk\Tests\SdkTestCase;

abstract class TransformsTestCase extends SdkTestCase
{
    /**
     * A small self-contained class file every transform test edits.
     */
    protected function fixture(): string
    {
        return <<<'PHP'
<?php

namespace App\Widgets;

use App\Base\Widget;

class WidgetsTable extends Widget
{
    protected function setUp(): void
    {
        $this->heading('Widgets');
    }
}
PHP;
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
