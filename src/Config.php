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

    // The base endpoint used to build other endpoints from.
    protected $endpoint;

    // Callable.
    protected $tokenRefreshCallback;

    public function __construct(array $params = [])
    {
        foreach($params as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * Set a property, using a setter method if there is one.
     * $name will be in lowerCamelCase, or converted if not.
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    protected function set($name, $value)
    {
        $property = $this->snakeToCamel($name);
        $setterName = 'set' . ucfirst($property);

        if (method_exists($this, $setterName)) {
            return $this->$setterName($value);
        }

        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }

    /**
     * Return a property value using a getter if available, or directly.
     * Returns null if the property does not exist.
     *
     * @param string $name The property name, camel or snake case.
     * @return mixed property name or null if no such property
     */
    public function __get($name)
    {
        $property = $this->snakeToCamel($name);
        $getterName = 'get' . ucfirst($property);

        if (method_exists($this, $getterName)) {
            return $this->$getterName();
        }

        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    /**
     * Support public get/with methods.
     */
    public function __call($method, $args)
    {
        if (substr($method, 0, 3) === 'get') {
            $property = $this->snakeToCamel(substr($method, 3));

            return $this->$property;
        }

        if (substr($method, 0, 4) === 'with') {
            $property = $this->snakeToCamel(substr($method, 4));

            $clone  = clone $this;
            $clone->$property = $args[0];
            return $clone;
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
        return $this;
    }

    protected function setTokenRefreshCallback(callable $value)
    {
        $this->tokenRefreshCallback = $value;
        return $this;
    }

    /**
     *
     */
    protected function setEndpoint(Endpoint $value)
    {
        $this->endpoint = $value;
        return $this;
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
