<?php

namespace Academe\XeroPHP;

/**
 * A decorator for the Guzzle client to support token refreshes
 * with autamatic retry afterwards.
 * Request OAuth1 signing is handled by the OAuth1 subscriber for Guzzle.
 */

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Subscriber\Oauth\OAuth1;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

class RefreshableClient
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var ClientProvider
     */
    protected $clientProvider;

    /**
     * @var bool Indicates whether the tokens have been automatically refreshed.
     */
    protected $tokenRefreshed = false;

    /**
     * @var OAuthParams|null Details of the refreshed token.
     */
    protected $refreshedToken;

    public function __construct(ClientInterface $client, ClientProvider $clientProvider)
    {
        $this->client = $client;
        $this->clientProvider = $clientProvider;
    }

    /**
     * Handle requests such as get(), put() and post().
     */
    public function __call($method, $args)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require at least a URI');
        }

        $uri = $args[0];
        $opts = isset($args[1]) ? $args[1] : [];

        // The token can be refreshed only if there is a refresh token, so only call the
        // local request method if we have a handle.

        return substr($method, -5) === 'Async'
            ? $this->client->requestAsync(substr($method, 0, -5), $uri, $opts)
            : (
                $this->clientProvider->oauthSessionHandle
                ? $this->request($method, $uri, $opts)
                : $this->client->request($method, $uri, $opts)
            );
    }

    /**
     * @return bool Indicates that the tokens have been automaically refreshed.
     */
    public function isTokenRefreshed()
    {
        return $this->tokenRefreshed;
    }

    /**
     * @return OAuthParams|null The token refresh response details, or null if no refresh.
     */
    public function getRefreshedToken()
    {
        return $this->refreshedToken;
    }

    /**
     * Get OAuth data from a response.
     * These will be URL-encoded in the response body.
     * The response HTTP code is ignored when looking for this data.
     *
     * @return array
     */
    public function getOAuthParams(Response $response)
    {
        // All OAuth responses from Xero have a content type of text/html.
        // Maybe this will change one day, so keep an eye on this.
        // "application/x-www-form-urlencoded" seems like it would be more appropriate,
        // though that's usually for posting a request.

        if (substr($response->getHeaderLine('content-type'), 0, 9) === 'text/html') {
            $body = $response->getBody()->getContents();
            parse_str($body, $parts);
        } else {
            $parts = [];
        }

        return new OAuthParams($parts);
    }

    /**
     * All synchronous requests with a refresh token (Xero Partner application)
     * divert through here.
     *
     * @param string $method GET, POST PUT, etc.
     * @param string $uri The aobsolute URI or URI relative to the bas URI
     * @paran array $options Additional options to send to the Guzzle request
     */
    public function request($method, $uri = '', array $options = [])
    {
        // Start by assuming the token has not expired yet, hoping that the application
        // has already checked its expiry time and renewed it as we approach that expiry time.
        $refreshRequired = false;

        // This feels like a bit of a fudge; see https://github.com/academe/XeroPHP/issues/4
        // If the ModifiedAfter GET parameter has been provided, then move it to the
        // If-Modified-Since HTTP header.

        if (! empty($options['query']) && ! empty($options['query']['modifiedAfter'])) {
            if (! array_key_exists('headers', $options)) {
                $options['headers'] = [];
            }

            // We will assume the format will be an acceptable ISO timestamp (many formats work).
            $options['headers']['If-Modified-Since'] = (string)$options['query']['modifiedAfter'];
            unset($options['query']['modifiedAfter']);
        }

        try {
            $response = $this->client->request($method, $uri, $options);

            // Here check if the response indicates the tokens have expired.
            // So long as we get the expired error, then we know we can renew that token.
            // We may get here if exceptions are turned off for the Guzzle client.

            $params = $this->getOAuthParams($response);

            if ($response->getStatusCode() == 401 && $params->isExpired()) {
                $refreshRequired = true;
            }
        } catch (ClientException $e) {
            // Guzzle will stick the response in the exception for us.
            $response = $e->getResponse();

            // Parse out any OAuth response parameters.
            $params = $this->getOAuthParams($response);

            if ($response->getStatusCode() == 401 && $params->isExpired()) {
                $refreshRequired = true;
            } else {
                // Anything else, just throw it again because it is not for us.
                throw $e;
            }
        }

        // For testing the fresh token handling, a token refresh can be forced by setting
        // this option.

        if ($this->clientProvider->forceTokenRefresh) {
            $refreshRequired = true;
        }

        if ($refreshRequired) {
            // The token has expired, so we should renew it.
            $this->refreshedToken = $this->refreshToken();

            // This will create a new access client with the fresh tokens.
            $client = $this->clientProvider->getAccessClient();

            // Retry the original request, but with this new client.
            $response = $client->request($method, $uri, $options);
        }

        return $response;
    }

    /**
     * A successful renew will normally include:
     *  oauth_token - the new token
     *  oauth_token_secret - the new token secret
     *  oauth_expires_in - seconds until it expires
     *  oauth_session_handle - handle for the session
     *  oauth_authorization_expires_in - session expiry in seconds
     *  xero_org_muid - the default organisation ID, possibly, but undocumented
     *
     * The oauth_authorization_expires_in is normally 10 years, and the oauth_session_handle
     * will last this long unless invalidated by the user. Re-authorising will invalidate
     * the current oauth_session_handle.
     * The oauth_token and the oauth_token_secret must be saved for all further accesses.
     *
     * In the evenr of an error there will normally be:
     *  oauth_problem - an ereor code, e.g. "token_rejected"
     *  oauth_problem_advice - a human-readable description of the error
     *
     * Once refreshed, this client should be discarded and rebuilt from scratch.
     *
     * @return OAuthParams The OAuth parameters in response to the refresh request
     */
    public function refreshToken($oauthToken = null, $oauthSessionHandle = null)
    {
        $refresh_client = $this->clientProvider->getRefreshClient();

        // Everything is already set for the refresh client; just make the request.

        $refresh_result = $refresh_client->get(null);

        $this->refreshedToken = $this->getOAuthParams($refresh_result);

        if (! $this->refreshedToken->hasToken()) {
            // Failed to renew the tokens.
            throw new \Exception(sprintf(
                'Token refresh error "%s": %s',
                $this->refreshedToken->oauth_problem,
                $this->refreshedToken->oauth_problem_advice
            ));
        }

        // We have a new token.
        // Make the details available to the user of this object.
        // FIXME: reset this when there was no refresh.

        $this->tokenRefreshed = true;

        // Set a new clientProvider with the fresh tokens.

        $this->clientProvider = $this->clientProvider->withFreshToken($this->refreshedToken);

        return $this->refreshedToken;
    }
}
