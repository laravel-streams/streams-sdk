<?php

namespace Streams\Sdk\Tests\Console\Make;

use Streams\Sdk\Console\Make\StreamLock;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * streams.lock is path -> file -> sha256, and the bytes do not depend on
 * insertion order.
 */
class StreamLockTest extends SdkTestCase
{
    protected string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = sys_get_temp_dir().'/streams-sdk-lock-'.uniqid();
        mkdir($this->output);
    }

    protected function tearDown(): void
    {
        $lock = StreamLock::path($this->output);

        if (is_file($lock)) {
            unlink($lock);
        }

        if (is_dir($this->output)) {
            rmdir($this->output);
        }

        parent::tearDown();
    }

    public function test_round_trip_is_path_file_hash_and_a_second_write_matches(): void
    {
        $lock = new StreamLock;
        $lock->put('people.table', 'app/Domains/People/Panel/Tables/PeopleTable.php', 'bbb');
        $lock->put('people.actions.add', 'app/Domains/People/Panel/Actions/PeopleActionsAdd.php', 'aaa');
        $lock->write($this->output);

        $first = (string) file_get_contents(StreamLock::path($this->output));
        $read = StreamLock::read($this->output);

        $this->assertSame('aaa', $read->hash('people.actions.add', 'app/Domains/People/Panel/Actions/PeopleActionsAdd.php'));
        $this->assertSame('bbb', $read->hash('people.table', 'app/Domains/People/Panel/Tables/PeopleTable.php'));
        $this->assertNull($read->hash('people.missing', 'missing.php'));
        $this->assertStringStartsWith('{', $first);
        $this->assertStringEndsWith("}\n", $first);
        $this->assertStringContainsString('app/Domains/People/Panel/Actions/PeopleActionsAdd.php', $first);
        $this->assertStringNotContainsString('\\/', $first);
        $this->assertLessThan(strpos($first, 'people.table'), strpos($first, 'people.actions.add'));

        $read->write($this->output);
        $second = (string) file_get_contents(StreamLock::path($this->output));

        $this->assertSame($first, $second);
    }

    public function test_encode_is_identical_when_entries_are_inserted_in_a_different_order(): void
    {
        $forward = new StreamLock;
        $forward->put('people.table', 'b.php', '2');
        $forward->put('people.table', 'a.php', '1');
        $forward->put('people.actions.add', 'c.php', '3');

        $backward = new StreamLock;
        $backward->put('people.actions.add', 'c.php', '3');
        $backward->put('people.table', 'a.php', '1');
        $backward->put('people.table', 'b.php', '2');

        $this->assertSame($forward->encode(), $backward->encode());
    }

    public function test_hash_is_sha256_of_the_file_bytes(): void
    {
        $this->assertSame(hash('sha256', 'generated'), StreamLock::hashContents('generated'));
    }

    public function test_a_missing_or_invalid_lock_reads_as_empty(): void
    {
        $this->assertSame([], StreamLock::read($this->output)->entries);

        file_put_contents(StreamLock::path($this->output), "not json\n");

        $this->assertSame([], StreamLock::read($this->output)->entries);
    }
}
