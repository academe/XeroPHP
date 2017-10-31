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
     * @var API
     */
    protected $api;

    /**
     * @var bool Indicates whether the tokens have been automatically refreshed.
     */
    protected $tokenRefreshed = false;

    /**
     * @var OAuthParams|null Details of the refreshed token.
     */
    protected $refreshedToken;

    public function __construct(ClientInterface $client, API $api)
    {
        $this->client = $client;
        $this->api = $api;
    }

    public function getConfig()
    {
        return $this->api->getConfig();
    }

    public function __call($method, $args)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri = $args[0];
        $opts = isset($args[1]) ? $args[1] : [];

        // The token can be refreshed only if there is a refresh token.

        return substr($method, -5) === 'Async'
            ? $this->client->requestAsync(substr($method, 0, -5), $uri, $opts)
            : (
                $this->getConfig()->oauthSessionHandle
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
    public function getFreshedToken()
    {
        return $this->freshedToken;
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
     */
    public function request($method, $uri = '', array $options = [])
    {
        // Start by assuming the token has not expired yet.
        $refreshRequired = false;

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
                throw new \Exception('Guzzle exception was not an expired token', null, $e);
            }
        }

        if ($refreshRequired) {
            // The token has expired, so we should renew it.
            $this->refreshedToken = $this->refreshToken();

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

            $this->tokenRefreshed = true;

            // The following seems really clumsy.
            // We are creating a new client here just to make the new access
            // attempt. We still need to signal to the caller that *its* original
            // client needs to be rebuilt with the new tokens.
            // Or maybe we just need a new OAuth1 handler, and replace the one on
            // the stack?

            // A new config with the fresh tokens.
            // This will also signal any watchers on the config object so that the
            // new settings can be saved.
            $config = $this->getConfig()->withFreshTokens($refresh_result);

            // A new API with the new config.
            $api = new API($config);

            // A new client to redo the request.
            $oauth1 = $api->createOAuth1Handler();
            $stack = $api->createStack($oauth1);
            $client = $api->createClient(['handler' => $stack]);

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
     * CHECKME: does the oauth_session_handle ever change and need saving during refreshes?
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
        // The URL to refresh: https://api.xero.com/oauth/AccessToken
        $accessTokenUrl = new Endpoint(
            $this->getConfig()->baseUrl,
            $this->getConfig()->oauthBasePath,
            $this->getConfig()->oauthAccessTokenResource
        );

        // The signature is required, but it must go into the URL query when
        // refreshing a tokan, rather than the header.
        $refresh_oauth1 = $this->api->createOAuth1Handler(['request_method' => 'query']);
        $refresh_handler_stack = $this->api->createStack($refresh_oauth1);
        $refresh_client = $this->api->createClient(['handler' => $refresh_handler_stack]);

        $refresh_result = $refresh_client->get(
            $accessTokenUrl->getURL(),
            [
                'query' => [
                    'oauth_token' => $oauthToken ?: $this->getConfig()->oauthToken,
                    'oauth_session_handle' => $oauthSessionHandle ?: $this->getConfig()->oauthSessionHandle,
                    'oauth_consumer_key' => $this->getConfig()->consumerKey,
                    'signature_method' => OAuth1::SIGNATURE_METHOD_RSA,
                ],
                'auth' => 'oauth',
                'exceptions' => false,
            ]
        );

        return $this->getOAuthParams($refresh_result);
    }
}
