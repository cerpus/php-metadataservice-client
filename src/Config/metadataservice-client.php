<?php

return [
    //default adapter for questionsets
    "default" => "cerpus-metadata",

    "adapters" => [

        "cerpus-metadata" => [
            "handler" => \Cerpus\MetadataServiceClient\Adapters\CerpusMetadataServiceAdapter::class,
            "base-url" => "",
            "auth-client" => "none",
            "auth-url" => "",
            "auth-user" => "",
            "auth-secret" => "",
            "auth-token" => "",
            "auth-token_secret" => "",
        ],

    ],
];