<?php

namespace Academe\XeroPHP;

/**
 * Builds HTTP clients for various contexts.
 * Builds and caches clients.
 * The client used in different contexts needs different middleware to be set.
 * However, much of what goes into a request is defined in the Request message.
 * CHECKME: can we avoid the middleware (which is a bit clumsy) by building all
 * the messages here and injecting what we want into those? That way we keep using
 * the same client. That would be nice if guzzlehttp/oauth-subscriber included
 * public methods to sign a resuest, but it hides all that stuff internally.
 *
 * TODO: define an interface contract.
 */

//use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
//use Carbon\Carbon;

class ClientProvider
{
    /**
     * Supported "Accept" header values.
     */
     const HEADER_ACCEPT_JSON   = 'application/json';
     const HEADER_ACCEPT_PDF    = 'application/pdf';
     const HEADER_ACCEPT_XML    = 'application/xml';

    /**
     * @var Pre-shared key and secret for signing.
     */
    protected $consumerKey;
    protected $consumerSecret;

    /**
     * @var Current active token and local expected expiry time.
     */
    protected $oauthToken;
    protected $oauthTokenSecret;
    protected $oauthExpiresAt;

    /**
     * @var Callable
     */
    protected $tokenRefreshCallback;

    /**
     * @var Additional parameters for the oauth1 middleware subscriber for Guzzle.
     */
    protected $oauth1Options = [];

    /**
     * @var Additional options for the client.
     */
    protected $clientOptions = [];

    /**
     * @var Cached clients.
     */
    protected $clients = [];

    /**
     *
     */
    public function __construct(array $oauth1Options = [], array $clientOptions = [])
    {
        foreach($oauth1Options as $name => $option) {
            $this->set($name, $option, 'oauth1Options');
        }

        foreach($clientOptions as $name => $option) {
            $this->set($name, $option, 'clientOptions');
        }
    }

    /**
     * Clear the client cache in a clone, because cloning is done when
     * new parameters are needed.
     */
    public function __clone()
    {
        $this->clients = [];
    }

    /**
     * Set a property, using a setter if there is one.
     */
    protected function set($name, $value, $fallback = 'oauth1Options')
    {
        $property = Helper::snakeToCamel($name);
        $setterName = 'set' . ucfirst($property);

        if (method_exists($this, $setterName)) {
            return $this->$setterName($value);
        }

        if (property_exists($this, $property)) {
            $this->$property = $value;
            return $this;
        }

        if (property_exists($this, $fallback)) {
            // The fallback option array uses the raw option name.
            $this->$fallback[$name] = $value;
        }

        return $this;
    }

    protected function setOauthExpiresAt($expiresAt)
    {
        $this->oauthExpiresAt = Helper::toCarbon($expiresAt);
        return $this;
    }

    /**
     *
     */
    public function getRenewableClient(Client $accessClient = null)
    {
        if ($accessClient === null) {
            $accessClient = $this->getAccessClient();
        }

        $renewableClient = new RefreshableClient($accessClient, $this);
        return $renewableClient;
    }

    /**
     * The refresh client is similar to the access client, but with the
     * tokens in the GET parameters.
     */
    public function getRefreshClient()
    {
    }

    /**
     * Get a client for accessing the API endpoints.
     * It will have AOuth1 tokens injected into the header.
     * This client is not renewable, so is suitable for the Private
     * and Public applications.
     *
     * @param array $options Override any default client options.
     * @param array $options Override any default OAuth1 handler options.
     * @return Client
     */
    public function getAccessClient(array $options = [], array $oauth1Options = [])
    {
        // The cache key is unique to the arguments passed in.
        $index = md5('getAccessClient' . json_encode(func_get_args()));

        if (array_key_exists($index, $this->clients)) {
            return $this->clients[$index];
        }

        $oauth1 = $this->createOAuth1Handler($oauth1Options);

        // CHECKME: Is there any way to hook into any handler stack that may already be
        // injected into the client?

        $stack = $this->createStack($oauth1);

        $clientOptions = [
            // Include and enable the guzzlehttp/oauth-subscriber middleware.
            'auth' => 'oauth',
            'handler' => $stack,
            // Keep exceptions on non 20x responses out of the way.
            'exceptions' => false,
            // JSON response will be needed in the majority of cases.
            'headers' => [
                'Accept' => static::HEADER_ACCEPT_JSON,
            ],
        ];

        $clientOptions = array_merge_recursive(
            $clientOptions,
            $this->clientOptions,
            $options
        );

        $client = new Client($clientOptions);

        $this->clients[$index] = $client;

        return $client;
    }

    /**
     * Create an oauth1 middleware subscriber for Guzzle.
     */
    public function createOAuth1Handler(array $options = [])
    {
        $oauth1Options = [
            // The key and secret are needed for signing.
            'consumer_key'    => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            // The token and secret is the current active token.
            'token'           => $this->oauthToken,
            'token_secret'    => $this->oauthTokenSecret,
            // RSA is required for Xero.
            'signature_method' => Oauth1::SIGNATURE_METHOD_RSA,
            // The subscriber needs this key even if the cert does not have
            // a passphrase.
            'private_key_passphrase' => null,
        ];

        $oauth1Options = array_merge_recursive(
            $oauth1Options,
            $this->oauth1Options,
            $options
        );

        $oauth1 = new Oauth1($oauth1Options);

        return $oauth1;
    }

    /**
     * Create a handler stack and add the OAuth1 signer.
     * TODO: allow options, maybe a callback to add other handlers.
     */
    public function createStack(Oauth1 $oauth1)
    {
        $stack = HandlerStack::create();

        $stack->push($oauth1, 'oauth1');

        return $stack;
    }

    /**
     * Set new token after a refresh.
     * Also invokes the callback to notify the application of the freshed tokens.
     *
     * @param OAuthParams $params The OAuth refrssh response message.
     * @return self Clone of $this, with new 
     */
    public function withFreshToken(OAuthParams $params)
    {
        $clone = clone $this;

        if ($params->oauthToken) {
            $clone->set('oauthToken', $params->oauthToken);
        }

        if ($params->oauthTokenSecret) {
            $clone->set('oauthTokenSecret', $params->oauthTokenSecret);
        }

        if ($params->oauthExpiresAt) {
            $clone->set('oauthExpiresAt', $params->oauthExpiresAt);
        }

        // Trigger the persistence handler.
        // The callback is given the new and the old configuration, and it's the
        // job of the callback to persist it and to signal the remainder of the
        // page that it has changed.

        if (is_callable($clone->tokenRefreshCallback)) {
            $clone->tokenRefreshCallback->__invoke($clone, $this);
        }

        return $clone;
    }
}
