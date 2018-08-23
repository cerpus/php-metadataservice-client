# Cerpus Metadata Service Client

PHP library to communicate with the Cerpus Metadata Service


## Installation
Use composer to require the package
```bash
composer require cerpus/metadataserviceclient
```


### Laravel
When composer has finished, add the service provider to the `providers` array in `config/app.php`

```php
Cerpus\MetadataServiceClient\Providers\MetadataServiceClientServiceProvider::class,
```

Add the following to the `alias` array in `config/app.php`
```php
'MetadataService' => \Cerpus\MetadataServiceClient\MetadataServiceClient::class,
```

Publish the config file from the package
```bash
php artisan vendor:publish --provider="Cerpus\MetadataServiceClient\Providers\MetadataServiceClientServiceProvider" --tag=config
```


### Lumen
*Not tested, but should work. You are welcome to update this documentation if this does not work!*

Add service provider in `app/Providers/AppServiceProvider.php`
```php
public function register()
{
    $this->app->register(Cerpus\MetadataServiceClient\Providers\MetadataServiceClientServiceProvider::class);
}

```

Uncomment the line that loads the service providers in `bootstrap/app.php`
```php
$app->register(App\Providers\AppServiceProvider::class);
```


### Edit the configuration file

Edit `config/metadataservice-client.php`
```php
<?php
return [
    "adapters" => [
        "cerpus-metadata" => [
            "handler" => \Cerpus\MetadataServiceClient\Adapters\MetadataServiceAdapter::class,
            "base-url" => '<url to service>',
        ],
    ],
];
```

Example for a developer setup:
```php
<?php

return [
    "adapters" => [
        "cerpus-metadata" => [
            "handler" => \Cerpus\MetadataServiceClient\Adapters\MetadataServiceAdapter::class,
	    "base-url" => env('METADATA_SERVER'),
        ],
    ],
];
```

## Usage
Resolve from the Laravel Container
```php
$cerpusMetadata = app(Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract::class)
```
or alias
```php
$cerpusMetadata = MetadataService::<Class Method>
```
or directly
```php
$cerpusMetadata = new Cerpus\MetadataServiceClient\Adapters\MetadataServiceAdapter(Client $client);
```
The last one is _not_ recommended.

## Class methods
Method calls return an object or throws exceptions on failure. 

 ## More info
 See the [Confluence Metadata storage service API documentation](https://confluence.cerpus.com/x/hIMJAg)


