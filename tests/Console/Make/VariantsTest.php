<?php

namespace Streams\Sdk\Tests\Console\Make;

use Streams\Sdk\Console\Make\Paths;
use Streams\Sdk\Console\Make\Variants;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Version detection resolves a variant id from the installed Laravel and
 * Streams versions, and each variant may select a different reference set.
 * The variant set stays small: unmapped pairs fall back to the default.
 */
class VariantsTest extends SdkTestCase
{
    public function test_detection_returns_the_variant_id_from_the_version_pair(): void
    {
        $this->assertSame(
            'v1',
            Variants::detect('11.0.0', '2.0.0'),
        );

        $this->assertSame(
            'v1',
            Variants::detect('12.0.0', '2.1.0'),
        );
    }

    public function test_unmapped_version_pairs_fall_back_to_the_default_variant(): void
    {
        $this->assertSame(Variants::DEFAULT_VARIANT, Variants::detect('9.0.0', '1.0.0'));
    }

    public function test_faked_versions_resolve_different_reference_sets(): void
    {
        config([
            'streams.sdk.make.variant_map' => [
                'laravel-11.streams-2' => 'reference',
                'laravel-12.streams-2' => 'legacy',
            ],
            'streams.sdk.make.variants' => [
                'reference' => [],
                'legacy' => [
                    'root' => 'src/Domains',
                    'namespace' => 'Work\\Domains',
                ],
            ],
        ]);

        $reference = Paths::fromConfig(Variants::detect('11.0.0', '2.0.0'));
        $legacy = Paths::fromConfig(Variants::detect('12.0.0', '2.0.0'));

        $this->assertSame(
            'app/Domains/People/Panel/Tables/PeopleTable.php',
            $reference->resolve('table', 'People', 'PeopleTable'),
        );

        $this->assertSame(
            'src/Domains/People/Panel/Tables/PeopleTable.php',
            $legacy->resolve('table', 'People', 'PeopleTable'),
        );

        $this->assertSame(
            'Work\\Domains\\People\\Panel\\Tables',
            $legacy->namespaceFor('src/Domains/People/Panel/Tables/PeopleTable.php'),
        );
    }
}