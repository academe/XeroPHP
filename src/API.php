<?php

namespace Academe\XeroPHP;

use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;

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
     * @return Endpoint Get the resource endpoint.
     */
    public function getURL($resource = null)
    {
        return $this->config->getEndpoint()
            ->withResource($resource);
    }

    // TODO: getPayrollUrl etc.

    /**
     * Get the OAuth URL Endpoint
     */
    public function getOAuthURL($resource)
    {
        $endpoint = $this->config->endpoint;

        return $endpoint
            ->withApi($endpoint->API_OAUTH)
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
            'base_uri' => (string)$this->getURL(),
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

    public function arrayToObject(array $data)
    {
        return new ResponseData($data);
    }
}
