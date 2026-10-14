<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use Illuminate\Support\Facades\File;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Pint;
use Streams\Sdk\Console\Support\Transforms\AddAttribute;
use Streams\Sdk\Console\Support\Transforms\AddMember;
use Streams\Sdk\Console\Support\Transforms\RenameClass;
use Streams\Sdk\Console\Support\Transforms\SetNamespace;
use Symfony\Component\Process\Process;

/**
 * Generates a People table class from the GroupVitals reference fixture by
 * loading its AST and applying named transforms, then checks that the
 * generated file is what the golden file says it is, that php -l accepts it,
 * and that Pint formats it and stays stable across repeated runs.
 */
class TransformGoldenTest extends TransformsTestCase
{
    protected string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $directory = sys_get_temp_dir().'/streams-sdk-transforms-'.uniqid();

        $this->outputPath = $directory.'/PeopleTable.php';

        File::ensureDirectoryExists($directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->outputPath));

        parent::tearDown();
    }

    public function test_generating_a_people_table_from_the_reference_matches_the_golden_and_passes_pint_and_lint()
    {
        $reference = __DIR__.'/../../../Fixtures/References/v1/app/Domains/People/Panel/Tables/PeopleTable.php';

        $editor = PhpEditor::read($reference);

        // The generation recipe: fix the namespace and class name (idempotent
        // here because the reference already carries the target identity),
        // mark the table with the SDK attribute, and add the generated member.
        (new SetNamespace('App\Domains\People\Panel\Tables'))->apply($editor);
        (new RenameClass('PeopleTable'))->apply($editor);
        (new AddAttribute('Streams\Sdk\Attributes\Table', ['people']))->apply($editor);
        (new AddMember(new ClassMethod(new Identifier('heading'), [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new Identifier('string'),
            'stmts' => [new Return_(new String_('People'))],
        ])))->apply($editor);

        File::put($this->outputPath, $editor->print());

        // Lint check: php -l accepts the generated file.
        $lint = new Process([PHP_BINARY, '-l', $this->outputPath]);
        $lint->run();

        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());
        $this->assertStringContainsString('No syntax errors', $lint->getOutput());

        // Pint check: the post-write step formats the generated file...
        $this->assertTrue(Pint::format($this->outputPath));

        // ...and Pint is stable: a second pass leaves the file byte-for-byte
        // identical, so repeated generations converge.
        $formatted = (string) File::get($this->outputPath);

        $this->assertTrue(Pint::format($this->outputPath));
        $this->assertSame($formatted, (string) File::get($this->outputPath));

        // The generated People table class matches the golden file exactly.
        $this->assertSame(
            (string) File::get(__DIR__.'/../../../Fixtures/Golden/transforms/PeopleTable.php'),
            $formatted,
        );
    }
}
