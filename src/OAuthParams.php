<?php

namespace Academe\XeroPHP;

/**
 * Simple value object for the OAuth response parameters.
 * A little more intelligent than an array.
 *
 * More methods will be added to interpret more OAuth return parameters.
 *
 * OAuth registration and renewal will give us:
 * - oauth_expires_in - expiry time in seconds for a token from its creation
 * The oauth_expires_in is no use without knowing the creation time.
 * The creation time is only really known at the moment the token is created or
 * refreshed. So this is where an oauth_expires_at value can be calculated.
 * Once calculated, it can be returned as a Carbon/DateTime object, and set (by
 * retrieval as a unix timestamp (integer), a Carbon/DateTime object or a parsable
 * string.
 * We should probably have an oauth_created_at which operates in a similat way.
 * When ititialising, we only create an oauth_created_at in the object if one was
 * not supplied in the setup data.
 */

use Carbon\Carbon;

class OAuthParams implements \JsonSerializable
{
    /**
     * @var string Values for the PARAM_OAUTH_PROBLEM parameter
     */
    const OAUTH_PROBLEM_TOKEN_EXPIRED = 'token_expired';

    /**
     * @var string The name of the oauth problem code parameter.
     */
    const PARAM_OAUTH_PROBLEM = 'oauthProblem';
    const PARAM_OAUTH_PROBLEM_ADVICE = 'oauthProblemAdvice';

    const OAUTH_EXPIRES_IN = 'oauthExpiresIn';
    const OAUTH_EXPIRES_AT = 'oauthExpiresAt';
    const OAUTH_CREATED_AT = 'oauthCreatedAt';
    const CREATED_AT = 'createdAt';

    /**
     * @var array Parsed OAuth parameters returned from the remote server
     */
    protected $params = [];

    /**
     * This is the fallback if no token creation time or expiry time is passed in.
     * @var Carbon UTC time this object was created.
     */
    protected $objectCreatedAt;

    /**
     * Expects an array.
     * CHECKME: any other array-like interfaces we could take account of
     * TODO: accept the expected expiry time (with guard time added by the application).
     */
    public function __construct($data)
    {
        foreach ((array)$data as $name => $value) {
            $this->set($name, $value);
        }

        $this->objectCreatedAt = Carbon::now('UTC');
    }

    public function get($name)
    {
        $property = Helper::snakeToCamel($name);
        $getterName = 'get' . ucfirst($property);

        if (method_exists($this, $getterName)) {
            return $this->$getterName();
        }

        if (array_key_exists($property, $this->params)) {
            return $this->params[$property];
        }
    }

    protected function set($name, $value)
    {
        $property = Helper::snakeToCamel($name);
        $setterName = 'set' . ucfirst($property);

        if (method_exists($this, $setterName)) {
            return $this->$setterName($value);
        }

        $this->params[$property] = $value;

        return $this;
    }

    /**
     * The oauth_expires_in parameter is measured in integer seconds.
     *
     * @param string|int $expirySeconds Expiry castable to integer seconds.
     * @return $this
     */
    protected function setOauthExpiresIn($expirySeconds)
    {
        $this->params[static::OAUTH_EXPIRES_IN] = (int)$expirySeconds;
        return $this;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->params);
    }

    /**
     * Calculate the expiry time.
     * For any non-successfulk response without an expires_in parameter, the
     * expiry time will always be calculated as now.
     *
     * @return Carbon UTC time the token is expected to expire.
     */
    public function getOauthExpiresAt()
    {
        if (array_key_exists(static::OAUTH_EXPIRES_AT, $this->params)) {
            return $this->params[static::OAUTH_EXPIRES_AT];
        }

        return $this->get(static::CREATED_AT)
            ->copy()->addSeconds($this->get(static::OAUTH_EXPIRES_IN));
    }

    /**
     * The oauthExpiresAt parameter is parsed to a Carbon datetime.
     *
     * @param mixed $createdAtTime
     * $return $this
     */
    protected function setOauthExpiresAt($value)
    {
        $this->params[static::OAUTH_EXPIRES_AT] = Helper::toCarbon($value);
        return $this;
    }

    /**
     * The token created time may be supplied as oauth_created_at or
     * created_at, falling back to the time this object was created.
     *
     * @return Carbon UTC time the tokens were created or refreshed.
     */
    public function getCreatedAt()
    {
        if (array_key_exists(static::CREATED_AT, $this->params)) {
            return $this->params[static::CREATED_AT];
        } elseif (array_key_exists(static::OAUTH_CREATED_AT, $this->params)) {
            return $this->params[static::OAUTH_CREATED_AT];
        } else {
            return $this->objectCreatedAt;
        }
    }

    /**
     * The createdAt parameter is parsed to a Carbon datetime.
     *
     * @param mixed $createdAtTime
     * $return $this
     */
    protected function setCreatedAt($createdAtTime)
    {
        $this->params[static::CREATED_AT] = Helper::toCarbon($createdAtTime);
        return $this;
    }

    /**
     * @return array All parsed and calculated parameters.
     */
    public function getAll()
    {
        return array_merge(
            $this->params,
            [
                static::CREATED_AT => (string)$this->get(static::CREATED_AT),
                static::OAUTH_EXPIRES_AT => (string)$this->get(static::OAUTH_EXPIRES_AT),
            ]
        );
    }

    /**
     * @return bool True if the parameters indicates the token has expired
     */
    public function isExpired()
    {
        if ($this->get(static::PARAM_OAUTH_PROBLEM) === static::OAUTH_PROBLEM_TOKEN_EXPIRED) {
            // The server has indicated the token we just tried to
            // use has expired.

            return true;
        }

        if ($this->getRemainingSeconds() <= 0) {
            // Time has run out already.

            return true;
        }

        // Not expired, given the information we have.
        return false;
    }

    /**
     * Tells us if there is a token here, signalling perhaps a successful
     * token fetch or refresh.
     */
    public function hasToken()
    {
        $oauthToken = $this->oauthToken;

        return ! empty($oauthToken);
    }

    /**
     * The remaining time before the token is expected to expire.
     * @return int Time returned in seconds (only while expiresAt() returns seconds).
     */
    public function getRemainingSeconds()
    {
        // Keep the sign so we know when we are past the expiry time.
        return Carbon::now('UTC')->diffInSeconds($this->oauth_expires_at, false);
    }

    public function toArray()
    {
        return $this->getAll();
    }

    /**
     * For interface \JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
