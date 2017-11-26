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
    const API_HQ        = 'xero.hq';

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
     * Other resources: Accountancy API.
     */
    const RESOURCE_ORGANISATION = 'Organisation';

    /**
     * Other resources: Payroll UK API.
     */
    const RESOURCE_UK_PAYRUN = 'payruns';

    /**
     * @var string
     */
    private $baseUrl    = self::BASE_URL;
    private $api        = self::API_CORE;
    private $version    = self::VERSION_20;
    private $resource;

    /**
     * URL in form $baseURL/$api/[$version/[$resource]]
     *
     * @param string|null $baseUrl
     * @param string|null $api
     * @param string|null $resource
     * @param string|null $version
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
     * For switching to a new API version.
     *
     * @parame string $api One of self::VERSION_*
     */
    public function withVersion($version)
    {
        $clone = clone $this;
        $clone->version = $version;
        return $clone;
    }

    /**
     * @param string|array $resource The default resource can be overridden.
     * @return string
     */
    public function getUrl($resource = null)
    {
        // Default the resource if not set.
        $resource = $resource ?: $this->resource;

        if (is_array($resource)) {
            $resource = implode('/', $resource);
        }

        // Include the API version only for the non-OAuth API.
        if ($this->api === static::API_OAUTH) {
            $path = sprintf('%s/%s', $this->api, $resource);
        } else {
            $path = sprintf('%s/%s/%s', $this->api, $this->version, $resource);
        }

        return sprintf('%s/%s', $this->baseUrl, $path);
    }

    public function __toString()
    {
        return $this->getUrl();
    }

    /**
     * Get the default endpoint, overriding the resource, api and version as necessary.
     *
     * @return Endpoint
     */
    public static function create($resource = null, $api = null, $version = null)
    {
        $endpoint = new static();

        if ($resource !== null) {
            $endpoint = $endpoint->withResource($resource);
        }

        if ($api !== null) {
            $endpoint = $endpoint->withApi($api);
        }

        if ($version !== null) {
            $endpoint = $endpoint->withVersion($version);
        }

        return $endpoint;
    }

    // The following convenience methods make switching between endpoints
    // quick and convenient.

    /**
     * @return Endpoint The accounting Endpoint
     */
    public static function createAccounting($resource = null, $version = self::VERSION_20)
    {
        return static::create($resource, static::API_CORE, $version);
    }

    /**
     * @return Endpoint The file Endpoint
     */
    public static function createFile($resource = null, $version = self::VERSION_10)
    {
        return static::create($resource, static::API_FILE, $version);
    }

    /**
     * @return Endpoint The asset Endpoint
     */
    public static function createAsset($resource = null, $version = self::VERSION_10)
    {
        return static::create($resource, static::API_ASSET, $version);
    }

    /**
     * @return Endpoint The payroll Endpoint
     */
    public static function createPayroll($resource = null, $version = null)
    {
        return static::create($resource, static::API_PAYROLL, $version);
    }

    /**
     * @return Endpoint The GB payroll Endpoint
     */
    public static function createGbPayroll($resource = null, $version = self::VERSION_20)
    {
        return static::createPayroll(
            $resource,
            $version
        );
    }

    /**
     * @return Endpoint The AU payroll Endpoint
     */
    public static function createAuPayroll($resource = null, $version = self::VERSION_10)
    {
        return static::createPayroll(
            $resource,
            $version
        );
    }

    /**
     * @return Endpoint The NZ payroll Endpoint
     */
    public static function createNzPayroll($resource = null, $version = self::VERSION_10)
    {
        return static::getPayroll(
            $resource,
            $version
        );
    }

    /**
     * @return Endpoint The Xero HQ Endpoint
     */
    public function createHq($resource = null, $version = self::VERSION_10)
    {
        return $this->create($resource, static::API_HQ, $version);
    }

    /**
     * Get the OAuth Endpoint
     */
    public static function createOAuth($resource)
    {
        return static::create($resource, static::API_OAUTH);
    }

    /**
     * Get the OAuth Refresh Endpoint
     */
    public static function createOAuthRefresh()
    {
        return static::create(static::OAUTH_ACCESS_TOKEN, static::API_OAUTH);
    }
}
