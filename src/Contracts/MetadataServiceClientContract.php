<?php

namespace Cerpus\MetadataServiceClient\Contracts;

use Cerpus\MetadataServiceClient\DataObjects\OauthSetup;
use GuzzleHttp\ClientInterface;

/**
 * Interface ImageServiceClientContract
 * @package Cerpus\CoreClient\Contracts
 */
interface MetadataServiceClientContract
{
    /**
     * @param OauthSetup $config
     * @return ClientInterface
     */
    public static function getClient(OauthSetup $config): ClientInterface;
}