<?php

namespace Cerpus\MetadataServiceClient\Providers;


use Cerpus\MetadataServiceClient\Clients\Client;
use Cerpus\MetadataServiceClient\Clients\Oauth1Client;
use Cerpus\MetadataServiceClient\Clients\Oauth2Client;
use Cerpus\MetadataServiceClient\Contracts\MetadataServiceClientContract;
use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use Cerpus\MetadataServiceClient\Exceptions\InvalidConfigException;
use Cerpus\MetadataServiceClient\MetadataServiceClient;
use Cerpus\MetadataServiceClient\DataObjects\OauthSetup;
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
                'baseUrl' => $adapterConfig['base-url'],
                'authUrl' => $adapterConfig['auth-url'],
                'authUser' => $adapterConfig['auth-user'],
                'authSecret' => $adapterConfig['auth-secret'],
                'authToken' => $adapterConfig['auth-token'],
                'authTokenSecret' => $adapterConfig['auth-token_secret'],
            ]));
        });

        $this->app->bind(MetadataServiceContract::class, function ($app) {
            $client = $app->make(MetadataServiceClientContract::class);
            $MetadataServiceClientConfig = $app['config']->get(MetadataServiceClient::$alias);
            $adapter = $MetadataServiceClientConfig['default'];

            $this->checkConfig($MetadataServiceClientConfig, $adapter);

            $adapterConfig = $MetadataServiceClientConfig["adapters"][$adapter];
            return new $adapterConfig['handler']($client, $adapterConfig['prefix']);
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