<?php

namespace Academe\XeroPHP;

/**
 * This is not a value object.
 * It may need to stay this way, so we know when tokens have
 * been refreshed somewhere in the stack.
 */

class Config
{
    /**
     * Supported "Accept" header values.
     */
     const HEADER_ACCEPT_JSON   = 'application/json';
     const HEADER_ACCEPT_PDF    = 'application/pdf';
     const HEADER_ACCEPT_XML    = 'application/xml';

    // Pre-shared API access.
    protected $consumerKey;
    protected $consumerSecret;

    // Authorised token and secret.
    protected $oauthToken;
    protected $oauthTokenSecret;

    // Token expiry time and refresh token.
    // expires_at as the local unix timestamp, calculated when the
    // token was first obtained or last refreshed.
    protected $expiresAt;
    protected $oauthSessionHandle;

    // OAuth app path.
    protected $oauthBasePath = 'oauth';

    // OAuth resources.
    protected $oauthRequestTokenResource = 'RequestToken';
    protected $oauthAccessTokenResource = 'AccessToken';

    // Additional OAuth1 parameters.
    protected $oauth1Additional = [];

    // Additional Client parameters.
    protected $clientAdditional = [];

    // The API, version of the API, and base URL.
    protected $baseUrl = 'https://api.xero.com';
    protected $api = 'api.xro';
    protected $version = '2.0';

    // Callable.
    protected $tokenRefreshCallback;

    public function __construct(array $params = [])
    {
        foreach($params as $name => $value) {
            $this->__set($name, $value);
        }
    }

    /**
     * $name will be in lowerCamelCase, or converted if not..
     */
    public function __set($name, $value)
    {
        $property = $this->snakeToCamel($name);
        $setter_name = 'set' . ucfirst($property);

        if (method_exists($this, $setter_name)) {
            return $this->$setter_name($value);
        }

        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }

    public function __get($name)
    {
        $property = $this->snakeToCamel($name);

        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    protected function snakeToCamel($name)
    {
        return lcfirst(
            str_replace(
                '_',
                '',
                ucwords($name, '_')
            )
        );
    }

    protected function setOauth1Additional(array $value)
    {
        $this->oauth1Additional = $value;
    }

    protected function setTokenRefreshCallback(callable $value)
    {
        $this->tokenRefreshCallback = $value;
    }

    /**
     * Set new tokens after a refresh.
     */
    public function withFreshTokens(OAuthParams $params)
    {
        $clone = clone $this;

        if ($params->oauth_token) {
            $clone->oauthToken = $params->oauth_token;
        }

        if ($params->oauth_token_secret) {
            $clone->oauthTokenSecret = $params->oauth_token_secret;
        }

        if ($params->oauth_expires_in) {
            $clone->oauthExpiresIn = $params->oauth_expires_in;
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
