<?php

namespace Cerpus\MetadataServiceClient\Clients;

use Cerpus\MetadataServiceClient\DataObjects\OauthSetup;
use Cerpus\MetadataServiceClient\Contracts\MetadataServiceClientContract;
use GuzzleHttp\ClientInterface;

/**
 * Class Client
 * @package Cerpus\MetadataServiceClient\Clients
 */
class Client implements MetadataServiceClientContract
{

    /**
     * @param OauthSetup $config
     * @return ClientInterface
     */
    public static function getClient(OauthSetup $config): ClientInterface
    {
        return new \GuzzleHttp\Client([
            'base_uri' => $config->baseUrl,
        ]);
    }
}