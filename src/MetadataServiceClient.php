<?php

namespace Cerpus\MetadataServiceClient;

use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use Illuminate\Support\Facades\Facade;

/**
 * Class MetadataServiceClient
 * @package Cerpus\MetadataServiceClient
 *
 */
class MetadataServiceClient extends Facade
{

    protected $defer = true;

    /**
     * @var string
     */
    static $alias = "metadataservice-client";

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return MetadataServiceContract::class;
    }

    /**
     * @return string
     */
    public static function getBasePath()
    {
        return dirname(__DIR__);
    }

    /**
     * @return string
     */
    public static function getConfigPath()
    {
        return self::getBasePath() . '/src/Config/' . self::$alias . '.php';
    }
}