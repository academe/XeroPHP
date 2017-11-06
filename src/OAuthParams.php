<?php

namespace Academe\XeroPHP;

/**
 * Simple value object for the OAuth response parameters.
 * A little more intelligent than an array.
 */

class OAuthParams implements \JsonSerializable
{
    /**
     * @var array Parsed OAuth parameters returned from the remote server
     */
    protected $params = [];

    /**
     * Unix timestamp
     */
    protected $createdTimestamp;

    /**
     * Field name for OAuth expiry period/token life (in seconds).
     */
    protected $fieldOauthExpiresIn = 'oauth_expires_in';

    /**
     * Expects an array.
     */
    public function __construct($data)
    {
        // CHECKME: any other array-like interfaces we could take account of?

        if (is_array($data)) {
            $this->params = $data;
        } else {
            $this->params = (array)$data;
        }

        // The created timestamp can be set if, for example, we are extracting
        // and hydrating from storage.

        if ($this->created_at) {
            $this->createdTimestamp = $this->created_at;
        } else {
            $this->createdTimestamp = time();
        }
    }

    public function __get($name)
    {
        if ($this->__isset($name)) {
            return $this->params[$name];
        }
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->params);
    }

    public function getAll()
    {
        return $this->params;
    }

    /**
     * Convenience function to convert the "expires_in" value to the
     * local timestamp it expires at.
     */
    public function expiresAt()
    {
        // If an expiry time has already been set explicitly, then that
        // is authoritive.

        if ($this->oauth_expires_at) {
            return $this->oauth_expires_at;
        }

        // Otherwise use the time this object was created (the created_at timestamp
        // given to it at instantiation).

        if ($this->oauth_expires_in) {
            return $this->createdTimestamp + (int)$this->oauth_expires_in;
        }
    }

    /**
     * Tells us if the parameters indicates that tokens have expired
     * or the expiry time has ellapsed.
     */
    public function isExpired($guardTime = 0)
    {
        if ($this->oauth_problem === 'token_expired') {
            // The server has indicated the token we just tried to
            // use has expired.

            return true;
        }

        if ($this->expiresAt() && $this->remainingTime() <= $guardTime) {
            // The oauth_expires_at is defined/known and we have gone past it.
            return true;
        }

        // Not expired, so far as we know given the information we have.
        return false;
    }

    /**
     * Tells us if there is a token here, signalling perhaps a successful
     * token refresh.
     */
    public function hasToken()
    {
        return ! empty($this->oauth_token);
    }

    /**
     * The remaining time before the token is expected to expire.
     */
    public function remainingTime()
    {
        return $this->expiresAt() - time();
    }

    public function toArray()
    {
        $params = $this->params;

        if (! array_key_exists('oauth_expires_at', $params)) {
            // We don't have an expiry time, set add it to the export.

            $params['oauth_expires_at'] = $this->$this->expiresAt();
        }

        return $params;
    }

    /**
     * For interface \JsonSerializable
     * When serialising, we want to make sure we include the creation time if
     * the oauth_expires_at time is not set. The OAuth server does not provide the expiry
     * time - that is something that must be tracked and set locally.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
