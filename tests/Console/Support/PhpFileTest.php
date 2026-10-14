<?php

namespace Streams\Sdk\Tests\Console\Support;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Console\Support\PhpFile;
use Streams\Sdk\Console\Support\Stub;

class PhpFileTest extends SdkTestCase
{
    public function test_puts_an_item_into_the_returned_array()
    {
        $path = $this->writeRegistry("<?php\n\nreturn [\n];\n");

        $file = PhpFile::read($path);

        $changed = $file->put('posts', ['panel' => true, 'page' => true]);

        $this->assertTrue($changed);

        $this->assertSame(
            "<?php\n\nreturn ['posts' => ['panel' => true, 'page' => true]];\n",
            $file->contents()
        );
    }

    public function test_second_identical_put_changes_nothing()
    {
        $path = $this->writeRegistry("<?php\nreturn ['posts' => ['panel' => true]];\n");

        $file = PhpFile::read($path);

        $this->assertFalse($file->put('posts', ['panel' => true]));

        $this->assertSame(
            "<?php\nreturn ['posts' => ['panel' => true]];\n",
            $file->contents()
        );
    }

    public function test_updates_flags_in_place_without_duplicates()
    {
        $path = $this->writeRegistry("<?php\nreturn ['posts' => ['panel' => true]];\n");

        $file = PhpFile::read($path);

        $file->put('posts', ['page' => true]);

        $this->assertSame(
            "<?php\nreturn ['posts' => ['panel' => true, 'page' => true]];\n",
            $file->contents()
        );

        $file->put('posts', ['panel' => false]);

        $this->assertSame(
            "<?php\nreturn ['posts' => ['panel' => false, 'page' => true]];\n",
            $file->contents()
        );
    }

    public function test_preserves_hand_written_entries()
    {
        $path = $this->writeRegistry("<?php\nreturn ['theme' => 'dark'];\n");

        $file = PhpFile::read($path);

        $file->put('posts', ['panel' => true]);

        $this->assertSame(
            "<?php\nreturn ['theme' => 'dark', 'posts' => ['panel' => true]];\n",
            $file->contents()
        );
    }

    public function test_diff_matches_the_change_the_save_applies()
    {
        $original = "<?php\nreturn [\n];\n";

        $path = $this->writeRegistry($original);

        $file = PhpFile::read($path);

        $file->put('posts', ['panel' => true]);

        $diff = $file->diff($original);

        $this->assertStringContainsString("+return ['posts' => ['panel' => true]];", $diff);
        $this->assertStringContainsString('-return [', $diff);
    }

    public function test_renders_the_versioned_table_stub()
    {
        $content = Stub::make('table', [
            'id' => 'blog_posts',
            'name' => 'Blog Posts',
            'description' => 'Articles.',
        ])->render();

        $this->assertStringContainsString('"handle": "blog_posts"', $content);
        $this->assertStringContainsString('"name": "Blog Posts"', $content);
        $this->assertStringContainsString('"description": "Articles."', $content);
        $this->assertStringContainsString('(table stub v1)', $content);
    }

    protected function writeRegistry(string $contents): string
    {
        $path = sys_get_temp_dir() . '/streams-sdk-registry-' . uniqid() . '.php';

        File::put($path, $contents);

        return $path;
    }
}