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
    public function getAccountingEndpoint($resource = null, $version = Endpoint::VERSION_20)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_CORE, $version);
    }

    /**
     * @return Endpoint The file Endpoint
     */
    public function getFileEndpoint($resource = null, $version = Endpoint::VERSION_10)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_FILE, $version);
    }

    /**
     * @return Endpoint The asset Endpoint
     */
    public function getAssetEndpoint($resource = null, $version = Endpoint::VERSION_10)
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
    public function getGbPayrollEndpoint($resource = null, $version = Endpoint::VERSION_20)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getPayrollEndpoint(
            $resource,
            $version
        );
    }

    /**
     * @return Endpoint The AU payroll Endpoint
     */
    public function getAuPayrollEndpoint($resource = null, $version = Endpoint::VERSION_10)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getPayrollEndpoint(
            $resource,
            $version
        );
    }

    /**
     * @return Endpoint The NZ payroll Endpoint
     */
    public function getNzPayrollEndpoint($resource = null, $version = Endpoint::VERSION_10)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getPayrollEndpoint(
            $resource,
            $version
        );
    }

    /**
     * @return Endpoint The Xero HQ Endpoint
     */
    public function getHqEndpoint($resource = null, $version = Endpoint::VERSION_10)
    {
        $endpoint = $this->config->getEndpoint();
        return $this->getEndpoint($resource, $endpoint::API_HQ, $version);
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
}
