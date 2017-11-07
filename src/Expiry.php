<?php

namespace Academe\XeroPHP;

/**
 * This class handles the expiry status of saved tokens.
 * It is a convenient class to check the expiry status given persisted
 * token time details.
 */

use Carbon\Carbon;
use DateTime;

class Expiry
{
    /**
     * @var Carbon The local UTC time the token was created or refreshed.
     */
    protected $oauth_created_at;

    /**
     * @var int The number of seconds the token is expted to live.
     */
    protected $oauth_expires_in;

    /**
     * @var Carbon The local UTC time the token is expected to expire.
     */
    protected $oauth_expires_at;

    /**
     * We either need the creation time + the expiry period,
     * or the absolute expiry time.
     * Each data item has a few shortened aliases.
     */
    public function __construct($data)
    {

        foreach((array)$data as $key => $value) {
            $name = API::snakeToCamel($key);

            if ($name === 'oauthExpiresAt' || $name === 'expiresAt') {
                $this->setOAuthExpiresAt($value);
            }

            // Be careful if using Eloquent not to pass in "created_at" from
            // the model.
            if ($name === 'oauthCreatedAt' || $name === 'createdAt') {
                $this->setOAuthCreatedAt($value);
            }

            if ($name === 'oauthExpiresIn' || $name === 'expiresIn') {
                $this->setExpiresIn($value);
            }
        }
    }

    /**
     * @param int|string|Carbon|DateTime $expiresAt
     */
    protected function setOAuthExpiresAt($expiresAt)
    {
        $this->oauth_expires_at = API::toCarbon($expiresAt);
    }

    /**
     * @param int|string|Carbon|DateTime $createdAt
     */
    protected function setOAuthCreatedAt($createdAt)
    {
        $this->oauth_created_at = API::toCarbon($createdAt);
    }

    /**
     * @param int $expiresIn In seconds
     */
    protected function setExpiresIn($expiresIn)
    {
        $this->oauth_expires_in = (int)$expiresIn;
    }

    /**
     * @return Carbon Get the expiry time from what we have.
     */
    public function expiresAt()
    {
        if ($this->oauth_expires_at !== null) {
            return $this->oauth_expires_at;
        }

        if ($this->oauth_created_at !== null && $this->oauth_expires_in !== null) {
            // The copy() clones the object before adding time.
            return $this
                ->oauth_created_at
                ->copy()
                ->addSeconds($this->oauth_expires_in);
        }

        // We don't have enough information to deterime the expiry time.
        // So do we throw an exception, or just assume it is expired now?
        // I'm going for "now".

        return Carbon::now('UTC');
    }

    /**
     * @param int $guardSeconds Guard period brings the expiry time forward
     * @return bool True if the expiry time has been reached
     */
    public function isExpired($guardSeconds = 0)
    {
        // Important to keep the test this way around to avoid race conditions
        // when the expiry time is not know and defaults to now.

        return $this->expiresAt()->lt(
            Carbon::now('UTC')->addSeconds($guardSeconds)
        );
    }
}
