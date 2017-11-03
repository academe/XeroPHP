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
     * The default base URL.
     */
    const BASE_URL = 'https://api.xero.com';

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
     * API versions.
     */
    const VERSION_10 = '1.0';
    const VERSION_20 = '2.0';

    /**
     * Resources for OAuth requests.
     */
    const OAUTH_REQUEST_TOKEN = 'RequestToken';
    const OAUTH_ACCESS_TOKEN  = 'AccessToken';

    /**
     * @var string
     */
    private $baseUrl = self::BASE_URL;
    private $api = self::API_CORE;
    private $version = self::VERSION_20;
    private $resource;

    /**
     * URL in form $baseURL/$api/$version/$resource
     *
     * @param Config $config
     * @param $resource
     * @param null $api
     * @throws Exception
     */
    public function __construct($baseUrl = null, $api = null, $resource = null, $version = null)
    {
        if ($baseUrl) {
            $this->baseUrl = $baseUrl;
        }

        if ($api) {
            $this->api = $api;
        }

        if ($resource) {
            $this->resource = $resource;
        }

        if ($version !== null) {
            $this->version = $version;
        }
    }

    /**
     * For switching to a new resource.
     *
     * @parame string $resource The resource endpoint, including resource ID if needed
     */
    public function withResource($resource)
    {
        $clone = clone $this;
        $clone->resource = $resource;
        return $clone;
    }

    /**
     * For switching to a new API, for example from a resource URL
     * to an OAuth URL.
     *
     * @parame string $api One of self::API_*
     */
    public function withApi($api)
    {
        $clone = clone $this;
        $clone->api = $api;
        return $clone;
    }

    /**
     * @param string $resource The default resource can be overridden.
     * @return string
     */
    public function getURL($resource = null)
    {
        // Include the API version only for the non-OAuth API.
        if ($this->api === static::API_OAUTH) {
            $path = sprintf('%s/%s', $this->api, $resource ?: $this->resource);
        } else {
            $path = sprintf('%s/%s/%s', $this->api, $this->version, $resource ?: $this->resource);
        }

        return sprintf('%s/%s', $this->baseUrl, $path);
    }

    public function __toString()
    {
        return $this->getURL();
    }
}
