<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;

class PeoplePagesMakeTest extends SdkTestCase
{
    protected string $fixture;

    protected string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = __DIR__.'/../../Fixtures/PeopleMake/streams.json';
        $this->output = sys_get_temp_dir().'/streams-sdk-people-pages-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->output);

        config([
            'streams.sdk.make.tree' => $this->fixture,
            'streams.sdk.make.output' => $this->output,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->output);

        parent::tearDown();
    }

    public function test_people_fixture_resolves_the_manage_table_and_modal_form(): void
    {
        $tree = AppTree::load($this->fixture);

        $this->assertSame('page', $tree->type('people.pages.manage'));
        $this->assertSame('table', $tree->type('people.table'));
        $this->assertSame('modal', $tree->node('people.actions.add')['type']);
        $this->assertSame('form', $tree->type('people.actions.add.form'));
        $this->assertSame([], File::allFiles($this->output));
    }

    protected function make(array $arguments = []): array
    {
        $exit = Artisan::call('streams:make', array_merge(['--json' => true], $arguments));
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($report);

        return $report;
    }
}
