<?php

namespace Cerpus\MetadataServiceClient\Providers;


use Cerpus\Helper\Clients\Client;
use Cerpus\Helper\Clients\Oauth1Client;
use Cerpus\Helper\Clients\Oauth2Client;
use Cerpus\Helper\DataObjects\OauthSetup;
use Cerpus\MetadataServiceClient\Contracts\MetadataServiceClientContract;
use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use Cerpus\MetadataServiceClient\Exceptions\InvalidConfigException;
use Cerpus\MetadataServiceClient\MetadataServiceClient;
use Illuminate\Support\ServiceProvider;

class MetadataServiceClientServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function boot()
    {
        $this->publishConfig();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->bind(MetadataServiceClientContract::class, function ($app) {
            $MetadataServiceClientConfig = $app['config']->get(MetadataServiceClient::$alias);
            $adapter = $MetadataServiceClientConfig['default'];

            $this->checkConfig($MetadataServiceClientConfig, $adapter);

            $adapterConfig = array_merge($this->getDefaultClientStructure(), $MetadataServiceClientConfig["adapters"][$adapter]);
            $client = strtolower($adapterConfig['auth-client']);
            /** @var MetadataServiceClientContract $clientClass */
            switch ($client) {
                case "oauth1":
                    $clientClass = Oauth1Client::class;
                    break;
                case "oauth2":
                    $clientClass = Oauth2Client::class;
                    break;
                default:
                    $clientClass = Client::class;
                    break;
            }

            return $clientClass::getClient(OauthSetup::create([
                'coreUrl' => $adapterConfig['base-url'],
                'authUrl' => $adapterConfig['auth-url'],
                'key' => $adapterConfig['auth-user'],
                'secret' => $adapterConfig['auth-secret'],
                'token' => $adapterConfig['auth-token'],
                'tokenSecret' => $adapterConfig['auth-token_secret'],
            ]));
        });

        $this->app->bind(MetadataServiceContract::class, function ($app, $params) {
            $client = $app->make(MetadataServiceClientContract::class);
            $MetadataServiceClientConfig = $app['config']->get(MetadataServiceClient::$alias);
            $adapter = $MetadataServiceClientConfig['default'];

            $this->checkConfig($MetadataServiceClientConfig, $adapter);

            $adapterConfig = $MetadataServiceClientConfig["adapters"][$adapter];
            $entityType = !empty($params['entityType']) ? $params['entityType'] : null;
            $entityId = !empty($params['entityId']) ? $params['entityId'] : null;
            return new $adapterConfig['handler']($client, $adapterConfig['prefix'], $entityType, $entityId);
        });

        $this->mergeConfigFrom(MetadataServiceClient::getConfigPath(), MetadataServiceClient::$alias);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            MetadataServiceContract::class,
            MetadataServiceClientContract::class,
        ];
    }

    private function getDefaultClientStructure()
    {
        return [
            "handler" => null,
            "base-url" => "",
            "auth-client" => "none",
            "auth-url" => "",
            "auth-user" => "",
            "auth-secret" => "",
            "auth-token" => "",
            "auth-token_secret" => "",
        ];
    }

    /**
     * @param $config
     * @param $adapter
     * @throws InvalidConfigException
     */
    private function checkConfig($config, $adapter)
    {
        if (!array_key_exists($adapter, $config['adapters']) || !is_array($config['adapters'][$adapter])) {
            throw new InvalidConfigException(sprintf("Could not find the config for the adapter '%s'", $adapter));
        }
    }

    private function publishConfig()
    {
        $path = MetadataServiceClient::getConfigPath();
        $this->publishes([$path => config_path(MetadataServiceClient::$alias . ".php")], 'config');
    }
}