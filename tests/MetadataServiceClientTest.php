<?php

namespace Cerpus\ImageServiceClientTests;


use Cerpus\MetadataServiceClient\MetadataServiceClient;
use Faker\Provider\Uuid;
use PHPUnit\Framework\TestCase;

class MetadataServiceClientTest extends TestCase
{

    /**
     * @test
     */
    public function getBasedir()
    {
        $this->assertEquals(dirname(__DIR__), MetadataServiceClient::getBasePath());
    }

    /**
     * @test
     */
    public function getConfigPath()
    {
        $this->assertEquals(dirname(__DIR__) . '/src/Config/metadataservice-client.php', MetadataServiceClient::getConfigPath());
    }
}