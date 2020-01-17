<?php

namespace Cerpus\MetadataServiceClient\Exceptions;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException as GuzzleInvalidArgumentException;
use GuzzleHttp\Exception\TransferException;

class MetadataServiceException extends Exception
{
    public static function fromGuzzleException(GuzzleException $e): self
    {
        // bad HTTP request - may or may not have a status code
        if ($e instanceof TransferException) {
            return new HttpException($e->getMessage(), $e->getCode(), $e);
        }

        // handle GuzzleHttp\json_decode
        if ($e instanceof GuzzleInvalidArgumentException && strpos($e->getMessage(), 'json_decode') === 0) {
            return new MalformedJsonException('json_decode failed', 0, $e);
        }

        // unknown Guzzle error that we don't handle
        throw $e;
    }
}
