<?php

namespace Academe\XeroPHP;

use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use Carbon\Carbon;

class API
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the default endpoint, overriding the resource, api and version as necessary.
     *
     * @return Endpoint
     */
    public function getEndpoint($resource = null, $api = null, $version = null)
    {
        $endpoint = $this->config->getEndpoint();

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
    public function getAccountingEndpoint($resource = null, $version = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_CORE, $version);
    }

    /**
     * @return Endpoint The file Endpoint
     */
    public function getFileEndpoint($resource = null, $version = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_FILE, $version);
    }

    /**
     * @return Endpoint The asset Endpoint
     */
    public function getAssetEndpoint($resource = null, $version = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_ASSET, $version);
    }

    /**
     * @return Endpoint The payroll Endpoint
     */
    public function getPayrollEndpoint($resource = null, $version = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_PAYROLL, $version);
    }

    /**
     * @return Endpoint The GB payroll Endpoint
     */
    public function getGbPayrollEndpoint($resource = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getPayrollEndpoint(
            $resource,
            $endpoint::VERSION_20
        );
    }

    /**
     * @return Endpoint The AU payroll Endpoint
     */
    public function getAuPayrollEndpoint($resource = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getPayrollEndpoint(
            $resource,
            $endpoint::VERSION_10
        );
    }

    /**
     * @return Endpoint The NZ payroll Endpoint
     */
    public function getNzPayrollEndpoint($resource = null)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getPayrollEndpoint(
            $resource,
            $endpoint::VERSION_10
        );
    }

    /**
     * Get the OAuth Endpoint
     */
    public function getOAuthEndpoint($resource)
    {
        $endpoint = $this->config->endpoint;

        return $endpoint
            ->withApi($endpoint::API_OAUTH)
            ->withResource($resource);
    }

    public function createOAuth1Handler(array $overrideConfig = [])
    {
        $oauth1Config = [
            'consumer_key'    => $this->config->consumerKey,
            'consumer_secret' => $this->config->consumerSecret,
            'token'           => $this->config->oauthToken,
            'token_secret'    => $this->config->oauthTokenSecret,
        ];

        $oauth1Config = array_merge(
            $oauth1Config,
            $this->config->oauth1Additional,
            $overrideConfig
        );

        $oauth1 = new Oauth1($oauth1Config);

        return $oauth1;
    }

    /**
     * Create a handler stack and add the OAuth1 signer.
     */
    public function createStack(Oauth1 $oauth1)
    {
        $stack = HandlerStack::create();

        $stack->push($oauth1, 'oauth1');

        return $stack;
    }

    /**
     * A simple Guzzle client.
     */
    public function createClient(array $additionalConfig = [])
    {
        $clientConfig = [
            'base_uri' => (string)$this->getEndpoint(),
            'auth' => 'oauth',
        ];

        // FIXME: we probably want a recursive merge to slip in the override options nicely.
        $clientConfig = array_merge(
            $clientConfig,
            $this->config->clientAdditional,
            $additionalConfig
        );

        $client = new Client($clientConfig);

        return $client;
    }

    /**
     * A client decorator to add automatic token refreshing.
     */
    public function createRefreshableClient(ClientInterface $client)
    {
        $client = new RefreshableClient($client, $this);

        return $client;
    }

    /**
     * Parse a response body into an array.
     */
    public static function parseResponse($response)
    {
        // Strip off the character encoding, e.g. "application/json; charset=utf-8"
        list($contentType) = explode(';', $response->getHeaderLine('content-type'));

        switch ($contentType) {
            case 'application/json':
                return json_decode((string)$response->getBody(), true);
                break;
            case 'text/xml':
                // This conversion is not so good for navigating due to the way lists
                // of items are converted. Best to avoid if possible.
                return json_decode(
                    json_encode(
                        simplexml_load_string(
                            (string)$response->getBody(),
                            null,
                            LIBXML_NOCDATA
                        )
                    ),
                    true
                );
                break;
            default:
                return (string)$response->getBody();
                break;
        }
    }

    /**
     * Convert a snake_case string to camelCase.
     * Static helper method.
     * 
     * @param string $name
     * @return string
     */
    public static function snakeToCamel($name)
    {
        return lcfirst(
            str_replace(
                '_',
                '',
                ucwords($name, '_')
            )
        );
    }

    /**
     * Convert a persisted and retreived timestamp item to a UTC Carbon object.
     *
     * @param mixed $item
     * @return Carbon
     */
    public static function toCarbon($item)
    {
        if ($item instanceof Carbon) {
            return $item->setTimezone('UTC');
        }

        if ($item instanceof DateTime) {
            return Carbon::instance($item)->setTimezone('UTC');
        }

        if (is_integer($item)) {
            return Carbon::createFromTimestamp($item);
        }

        if (is_string($item)) {
            return Carbon::parse($item)->setTimezone('UTC');
        }
    }

    /**
     * Convert a parsed response array to a nested ResponseData instance.
     */
    public static function arrayToModel(array $data)
    {
        return new ResponseData($data);
    }

}
