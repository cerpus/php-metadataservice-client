<?php

namespace Cerpus\MetadataServiceClient\Adapters;

use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use GuzzleHttp\Client;

/**
 * Class MetadataServiceAdapter
 * @package Cerpus\MetadataServiceClient\Adapters
 */
class MetadataServiceAdapter implements MetadataServiceContract
{
    /** @var Client */
    private $client;

    /**
     * QuestionBankAdapter constructor.
     * @param Client $client
     * @param string $containerName
     */
    public function __construct(Client $client, $containerName)
    {
        $this->client = $client;
    }

}