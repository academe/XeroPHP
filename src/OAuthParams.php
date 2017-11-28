<?php

namespace Academe\XeroPHP;

/**
 * Simple value object for the OAuth response parameters.
 * It will assume the OAuth token was created the moment this class
 * was instantiated, but you can provide it with an alternative creation
 * time if your app knows better.
 *
 * OAuth uses snake_case parameters, which are all converted to lowerCamelCase
 * when coming into, and dealing with, this class.
 *
 * OAuth registration and renewal will give us:
 * - oauth_expires_in - expiry time in seconds for a token from its creation
 * The oauthExpiresIn is no use without knowing the creation time.
 * The creation time is only really known at the moment the token is created or
 * refreshed. So this is where an oauthExpiresAt value can be calculated.
 */

use GuzzleHttp\Psr7\Response;
use Carbon\Carbon;

class OAuthParams implements \JsonSerializable
{
    /**
     * See https://developer.xero.com/documentation/auth-and-limits/oauth-issues
     * @var string Values for the PARAM_OAUTH_PROBLEM parameter
     */
    // The most common two problems, the first recoverable through a refresh,
    // and the second not.
    const OAUTH_PROBLEM_TOKEN_EXPIRED       = 'token_expired';
    const OAUTH_PROBLEM_TOKEN_REJECTED      = 'token_rejected';

    // Some issues that may occur during refresh or authorisaion or
    // due to incorrect configuration.
    const OAUTH_PROBLEM_TOKEN_SIG_INVALID   = 'signature_invalid';
    const OAUTH_PROBLEM_TOKEN_NONCE_USED    = 'nonce_used';
    const OAUTH_PROBLEM_TOKEN_TIMESTAMP     = 'timestamp_refused';
    const OAUTH_PROBLEM_TOKEN_SIG_METHOD    = 'signature_method_rejected';
    const OAUTH_PROBLEM_TOKEN_PERM_DENIED   = 'permission_denied';
    const OAUTH_PROBLEM_TOKEN_KEY_UNKOWN    = 'consumer_key_unknown';
    const OAUTH_PROBLEM_TOKEN_XERO_ERROR    = 'xero_unknown_error';

    // Note: spaces and not underscores. Reason unknown.
    const OAUTH_PROBLEM_TOKEN_RATE_LIMIT    = 'rate limit exceeded';

    /**
     * @var string The name of the oauth problem code parameter.
     */
    const PARAM_OAUTH_PROBLEM           = 'oauthProblem';
    const PARAM_OAUTH_PROBLEM_ADVICE    = 'oauthProblemAdvice';

    const OAUTH_EXPIRES_IN  = 'oauthExpiresIn';
    const OAUTH_EXPIRES_AT  = 'oauthExpiresAt';
    const OAUTH_CREATED_AT  = 'oauthCreatedAt';
    const CREATED_AT        = 'createdAt';

    /**
     * @var array Parsed OAuth parameters returned from the remote server
     */
    protected $params = [];

    /**
     * This is the fallback if no token creation time or expiry time is passed in.
     *
     * @var Carbon UTC time this object was created
     */
    protected $objectCreatedAt;

    /**
     * @param Response|array $data
     */
    public function __construct($data)
    {
        if ($data instanceof Response) {
            // All OAuth responses from Xero have a content type of text/html.
            // Maybe this will change one day, so keep an eye on this.
            // "application/x-www-form-urlencoded" seems like it would be more appropriate,
            // though that's usually for posting a request.

            if (substr($data->getHeaderLine('content-type'), 0, 9) === 'text/html') {
                $body = (string)$data->getBody();
                parse_str($body, $data);
            } else {
                $data = [];
            }
        }

        if (is_array($data)) {
            foreach ($data as $name => $value) {
                $this->set($name, $value);
            }
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

    /**
     * Magic getter for properties.
     *
     * @paran string $name
     * @return mixed
     */
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
     * This is the time you would store against a token so you know when it is
     * going to be expiring.
     * For any non-successfulk response without an expires_in parameter, the
     * expiry time will always be calculated as now.
     *
     * @return Carbon UTC time the token is expected to expire.
     */
    public function getOauthExpiresAt()
    {
        return $this->getOauthCreatedAt()
            ->copy()
            ->addSeconds($this->get(static::OAUTH_EXPIRES_IN));
    }

    /**
     * The token created time may be supplied as oauth_created_at or
     * created_at, falling back to the time this object was created.
     *
     * @return Carbon UTC time the tokens were created or refreshed.
     */
    public function getOauthCreatedAt()
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
     * Set the OAuth created time, which takes prescenedence over the object
     * creation time.
     *
     * @param mixed $createdTime Timestamp to be converted to a Carbon time.
     * @return this A clone with the new OAuth created time.
     */
    public function withOauthCreatedAt($createdTime)
    {
        $clone = clone $this;

        $clone->setOauthCreatedAt($createdTime);

        return $clone;
    }

    /**
     * Alias to withOauthCreatedAt().
     */
    public function withCreatedAt($createdTime)
    {
        return $this->withOauthCreatedAt($createdTime);
    }

    /**
     * Alias to the oauthCreatedAt parameter is parsed to a Carbon datetime.
     *
     * @param mixed $createdAtTime
     * $return $this
     */
    protected function setCreatedAt($createdAtTime)
    {
        return $this->getOauthCreatedAt();
    }

    /**
     * The oauthCreatedAt parameter is parsed to a Carbon datetime.
     *
     * @param mixed $createdAtTime
     * $return $this
     */
    protected function setOauthCreatedAt($createdAtTime)
    {
        $this->params[static::OAUTH_CREATED_AT] = Helper::toCarbon($createdAtTime);
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
                static::OAUTH_CREATED_AT => (string)$this->getOauthCreatedAt(),
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
            // The server has indicated the token we just tried to use has expired.
            return true;
        }

        // Not expired, given the information we have.
        return false;
    }

    /**
     * Tells us if there is a token here, signalling a successful
     * token creation or refresh.
     *
     * @return bool true if the parameters contains a token key and value
     */
    public function hasToken()
    {
        $oauthToken = $this->oauthToken;

        return ! empty($oauthToken);
    }

    /**
     * @param bool true if a token is being rejected
     */
    public function isRejected()
    {
        return $this->get(static::PARAM_OAUTH_PROBLEM) === static::OAUTH_PROBLEM_TOKEN_REJECTED;
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
