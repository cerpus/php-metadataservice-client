<?php

namespace Cerpus\MetadataServiceClient\Clients;


use Cerpus\MetadataServiceClient\Contracts\MetadataServiceClientContract;
use Cerpus\MetadataServiceClient\DataObjects\OauthSetup;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Client;

/**
 * Class Oauth1Client
 * @package Cerpus\MetadataServiceClient\Clients
 */
class Oauth1Client implements MetadataServiceClientContract
{

    /**
     * @param OauthSetup $config
     * @return ClientInterface
     */
    public static function getClient(OauthSetup $config): ClientInterface
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1([
            'consumer_key' => $config->authUser,
            'consumer_secret' => $config->authSecret,
            'token' => $config->authToken,
            'token_secret' => $config->authTokenSecret,
        ]);

        $stack->push($middleware);

        return new Client([
            'base_uri' => $config->baseUrl,
            'handler' => $stack,
            RequestOptions::AUTH => 'oauth',
        ]);
    }
}