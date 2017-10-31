<?php

namespace Academe\XeroPHP;

/**
 * Simple value object for the OAuth response parameters.
 * Just a little more convenient than an array.
 */

class OAuthParams
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
     * Expects an array.
     */
    public function __construct($data)
    {
        // CHECKME: any other array-able types we could take account of?
        if (is_array($data)) {
            $this->params = $data;
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
     * Convenience function to convert the "expirres_in" value to the
     * local timestamp it expires at.
     */
    public function expiresAt()
    {
        // If an expiry time has already been set explicitly, then return that.
        if ($this->expires_at) {
            return $this->expires_at;
        }

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

        if ($this->expiresAt() && ($time() - $guardTime) > $this->expiresAt()) {
            // The expires_at is defined/known and we have gone past it.
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
}
