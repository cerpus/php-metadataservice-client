<?php

namespace Cerpus\MetadataServiceClientTests\Adapters;

use Cerpus\MetadataServiceClient\Adapters\CerpusMetadataServiceAdapter;
use Cerpus\MetadataServiceClientTests\Utils\MetadataServiceTestCase;
use Cerpus\MetadataServiceClientTests\Utils\Traits\WithFaker;

/**
 * Class MetadataServiceAdapterTest
 * @package Cerpus\MetadataServiceClientTests\Adapters
 */
class MetadataServiceAdapterTest extends MetadataServiceTestCase
{
    use WithFaker;

    /**
     * @test
     */
    public function dummyTest()
    {
        $this->assertTrue(true);
    }
}
