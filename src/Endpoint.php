<?php

namespace Academe\XeroPHP;

use Psr\Http\Message\UriInterface;
use XeroPHP\Application;

/**
 * Object for representing a complete endpoint on the Xero APIs.  It also handles special URLs that may be passed in, eg
 * OAuth related ones.
 * Only handles the base part of the URL and not query parameters.
 */
class Endpoint //implements UriInterface
{
    /**
     * Base API values.
     */
    const API_CORE      = 'api.xro';
    const API_PAYROLL   = 'payroll.xro';
    const API_FILE      = 'files.xro';
    const API_ASSET     = 'assets.xro';

    /**
     * API to access the OAUTH functionality.
     * Some docs say this is OAuth, but only lower case works.
     */
    const API_OAUTH     = 'oauth';

    /**
     * Resources for OAuth requests.
     */
    const OAUTH_REQUEST_TOKEN = 'RequestToken';
    const OAUTH_ACCESS_TOKEN  = 'AccessToken';

    /**
     * @var string
     */
    private $baseUrl;
    private $api;
    private $version = '1.0';
    private $resource;

    /**
     * URL in form $baseURL/$api/$version/$resource
     *
     * I'm not actually sure about the naming here, especially wrt "enpoint".
     *
     * @param Config $config
     * @param $resource
     * @param null $api
     * @throws Exception
     */
    public function __construct($baseUrl, $api, $resource = null, $version = null)
    {
        $this->baseUrl = $baseUrl;
        $this->api = $api;

        if ($version !== null) {
            $this->version = $version;
        }

        $this->resource = $resource;
    }

    public function withResource($resource)
    {
        $clone = clone $this;
        $clone->resource = $resource;
        return $clone;
    }

    /**
     * @return string
     */
    public function getURL()
    {
        if ($this->api === static::API_OAUTH) {
            $path = sprintf('%s/%s', $this->api, $this->resource);
        } else {
            $path = sprintf('%s/%s/%s', $this->api, $this->version, $this->resource);
        }

        return sprintf('%s/%s', $this->baseUrl, $path);
    }

    public function __toString()
    {
        return $this->getURL();
    }
}
