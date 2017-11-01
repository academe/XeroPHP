XeroPHP API OAuth Access
========================

PHP library for working with the Xero OAuth API.

Intro
-----

This library tackles the following parts of Xero API access:

* Coordinating the OAuth layer to provide secure access.
* Automatically refreshing expired tokens (for Partner Applications).
* Parsing the response into a generic nested object.

This package leaves these functions for other packages to handle:

* All HTTP communications through [Guzzle 6]().
* OAuth request signing to
  [Guzzle OAuth Subscriber](https://github.com/guzzle/oauth-subscriber)
* OAuth authentication recommended through
  [OAuth 1.0 Client](https://github.com/thephpleague/oauth1-client)
* Xero provider for OAuth 1.0 Client recommended using
  [Xero Provider for OAuth 1.0 Client](https://github.com/Invoiced/oauth1-xero)
* Storage of the OAuth tokens to your own app.
* Knowledge of how to navigate the results is left with your application,
  thorugh the generic nested data object will help.

This package needs the OAuth token and secret gained through authorisation
to access the API, and the session handler token if automatic refreshing is
needed for the Partner Application.
This package does not care what you use at the front end obtain those tokens.
The two packages recommended above to do this are reliable, well documented,
and focus on just getting that one job done.

Areas to Complete (TODO)
------------------------

* So far development of this package has concentrated on reading from the Xero API.
Writing to the API should be supported, but has not gone through any testing
at this stage.
* Lots more documentation and examples.
* Tests. Any help is setting some up would be great.

Quick Start
-----------

```php
use use Academe\XeroPHP;

// Most of the confifuration goes into one place.
$config = new XeroPHP\Config([
    // Account credentials.
    'consumerKey'    => 'your-consumer-key',
    'consumerSecret' => 'your-consumer-sectet',
    // Current token and secret from storage.
    'oauthToken'           => $oauth_token,
    'oauthTokenSecret'    => $oauth_token_secret,
    // Curresnt session for refreshes also from storage.
    'oauthSessionHandle'   => $oauth_session_handle,
    // Running the Partner Application
    'oauth1Additional' => [
        'signature_method' => \GuzzleHttp\Subscriber\Oauth\Oauth1::SIGNATURE_METHOD_RSA,
        'private_key_file' => 'local/path/to/private.pem',
        'private_key_passphrase' => 'your-optional-passphrase',
    ],
    'api' => 'api.xro', // 'payroll.xro' etc.
    'version' => '2.0', // '1.0' for AU and US Payroll
    'clientAdditional' => [
        'headers' => [
            // We would like JSON back for most APIs, as it is structured nicely.
            'Accept' => 'application/json',
        ],
    ],
    // When the token is automatically refreshed, then this callback will
    // be given the opportunity to put it into storage.
    'tokenRefreshCallback' => function($newConfig, $oldConfig) use ($myStorageObject) {
        // The new token and secret are available here:
        $oauth_token = $newConfig->oauth_token;
        $oauth_token_secret = $newConfig->oauth_token_secret;

        // Now those new crdentials need storing.
        $myStorageObject->storeTheNewTokenWhereever($oauth_token, $oauth_token_secret);
    }
]);

// The API object will help us coordinate setting up the client.
$api = new XeroPHP\API($config);

// The OAuth1 handler signs the requests.
$oauth1 = $api->createOAuth1Handler();

// The handler stack is used to push the OAuth1 handler into Guzzle.
$stack = $api->createStack($oauth1);

// Get a plain Guzzle client, with appropriate settings.
$client = $api->createClient(['handler' => $stack]);

// Create the auto-token refreshing client.
// Use it like a Guzzle client to send requests.
// Pass the resource path in as the URI, as the base URL is already
// configured.
$refreshableClient = $api->createRefreshableClient($client);
```

Now we have a client to send requests.

After sending a request, you can check if the token was refreshed:

```php
$tokensWereRefreshed = $refreshableClient->isTokenRefreshed();
```

If the token is refreshed, then the new token will (hopefully) have been
stored. The client then needs to be rebuilt as above.

If you want to refresh the tokens explicitly, before you hit an expired
token response, then it can be done like this:

```php
$newTokenDetails = $refreshableClient->refreshToken();
```

You then have to store $newTokenDetails (a value object - just pull out what
you need or store the whole thing) and rebuild the client.
That may be more convenient to do, but be aware that unless you set a guard time,
there may be times when you miss an expiry and the request will return an expired
token error.

The Results Object
------------------

The `ResponseData` class is instantiated with the response data converted to an array:

```php
// Get the first page of payruns.
$response = $refreshableClient->get('payruns', ['query' => ['page' => 1]]);

// Assuming all is fine, parse the response to an array.
$array = XeroPHP\API::parseResponse($response);

// Instantiate the response data object.
$result = new XeroPHP\ResponseData($array)

// Now we can navigate the data.
echo $result->id;
// 14c9fc04-f825-4163-a0cf-3c2bc31c989d

foreach($result->PayRuns as $payrun) {
    echo $payrun->id . " at " . $payrun->periodStartDate . "\n";
}
// e4df31c9-07db-47d5-a415-6ee32d9048eb at 2017-09-25 00:00:00
// fbd6fc76-dbfc-459d-b230-80334d175048 at 2017-10-20 00:00:00
// 46200d03-67f2-4f5d-8852-cdad50cbe886 at 2017-10-25 00:00:00

echo $result->pagination->pagesize;
// 100

var_dump($result->pagination->toArray());
// array(4) {
//   ["page"]=>
//   int(1)
//   ["pageSize"]=>
//   int(100)
//   ["pageCount"]=>
//   int(1)
//   ["itemCount"]=>
//   int(3)
// }
```

The results object provides access structured data of resources fetched from the API.
It is a value object, and does not provide any ORM-like functionality.

Each `ResponseData` object can act as a single resource or an itterable collection of
resources. The collection fucntionality is particularly fancy, but it has `first()`
and `count()`, and will iterate over a `foreach` loop.

An attempt is made to convert all dates and times to a `Carbon` datetime.
Xero mixes quite a number of date formats across its APIs, so it is helpful to
get them all normallised.
Formats I've found so far:

* "/Date(1509454062181)/" - milliseconds since the Unix epoch, UTC.
* "/Date(1439813704613+0000)/" - milliseconds since the Unix epoch, with a timezone offset.
* "2017-10-20T16:04:50" - ISO UTC time, to the second.
* "2017-10-31T12:50:15.9920037" - ISO UTC timestamp with microseconds.
* "2017-09-25T00:00:00" - ISO UTC date only.

I'm sure there will be more. These fields are recognised solely through the suffix to
their name at present. Suffixes recognised are:

* UTC
* Date
* DateTime

There is no automatic pagination feature (automatically fetching subsequent pages) when
iterating over a paginated resource.
A decorator class could easily do this though, and that may make a nice addition to take
the logic of "fetching all the matching things" that span more than one page away from
the application.

All other datatypes will be either a scalar the API supplied (string, float, int, boolean)
or another `ResponseData` object containing either a single resource (e.g. "pagination")
or a collection of resources (e.g. "payruns").

Accessing properties of this object is case-insensitive.
Accessing a non-existant property will return an empty `ResponseData`:

```php
$value = $result->foo->bar->where->am_i;
var_dump($value->isEmpty());
// bool(true)
```

But do be aware that when you hit a scalar (e.g. a string) then that is what you will get
back and not a `ResponseData` object.

The API sometimes returns a `null` for a field or resource rather than simply omitting the
field. Examples are the `pagination` field when fetching a single `payrun`, or the `problem`
field when there is no problem.
In this case, when you fetch the value, you will be given an empty `ResponseData` object
instead.

Guzzle Exceptions
-----------------

By default, the Guzzle client will throw an exception if it receives a non 20x HTTP response.
When this happens, the HTTP response can be found in the exception.

```php
try {
    $response = $client->get('PayRuns', []);
} catch (\Exception $e) {
    $response = $e->getResponse();
    ...
}
```

Handling non-20x messages like this may not be convenient, so Guzzle can be told not to
throw an exception using the `exceptions` option:

```php
$response = $client->get('PayRuns', ['exceptions' => false]);
```

This option can be used on each request, or set as the default in the `Config` instantiation.
This package is designed not to care which approach you take. However, *not* throwing an
exception often makes sense, because even non-20x responses nearly always contain a response
body with information the application is going to need to log or to make a decision.

Catching Errors
---------------

There are numerous sources of error, and these are reported in many different ways, and with
different data structures. The aim of this package will be to try to normalise them, but
in the meantime here is a list of those we know:

* OAuth errors
* Request construction errors, such as an invalid UUID format
* Invalid resource errors, such as a missing resource or incorrect URL

The places where error details cna be found are:

* OAuth errors will be returned as URL-encoded parameters in the response body.
  The `OAuthParams` class can parse these details and provide some interpretation.
* Request construction errors are returned TBC

Other Notes
-----------

I have noticed the occassional 401 which then works on a retry. Using the Guzzle retry
handler would be a good move to avoid unnecessary errors when processing large amounts
of data.
