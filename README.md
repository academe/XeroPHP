[![Build Status](https://travis-ci.org/academe/XeroPHP.svg?branch=master)](https://travis-ci.org/academe/XeroPHP)

XeroPHP API OAuth Access
========================

PHP library for working with the Xero OAuth API.

Intro
-----

This library tackles the following parts of Xero API access:

* Coordinating the OAuth layer to provide secure access.
* Automatically refreshing expired tokens (for Partner Applications).
* Parsing the response into a generic nested object.

This package leaves these functions for other packages to handle, thought
does coordinate them:

* All HTTP communications through [Guzzle 6]().
* OAuth request signing to
  [Guzzle OAuth Subscriber](https://github.com/guzzle/oauth-subscriber)
* OAuth authentication recommended through
  [OAuth 1.0 Client](https://github.com/thephpleague/oauth1-client)
* Xero provider for OAuth 1.0 Client recommended using
  [Xero Provider for The PHP League OAuth 1.0 Client](https://github.com/Invoiced/oauth1-xero)
* Storage of the OAuth tokens to your own application.
  A hook is provided so that refreshed tokens can be updated in storage.
* Knowledge of how to navigate the results is left with your application.
  The generic nested data object helps to do this.

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
* Docs on the Expiry class, which splits OAuthParams into two separate classes.

Quick Start
-----------

```php
use use Academe\XeroPHP;

// Most of the configuration goes into one place.

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
    'clientAdditional' => [
        // You will almost always want exceptions off, so Guzzle does not throw an exception
        // on every non-20x response.
        'exceptions' => false,
        'headers' => [
            // We would like JSON back for most APIs, as it is structured nicely.
            // Exceptions include 'application/pdf' to download or upload files.
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
    },
    // Provide the default endpoint.
    'endpoint' => new Endpoint(...),
]);

// The `Config` class will accept `camelCase` or `snake_case` parameters, since
// OAuth returns strictly snake_case, so can be fed directly into the `Config`.


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
// This assumes the payrun Endpoint was supplied as the default endpoint:
$response = $refreshableClient->get('payruns', ['query' => ['page' => 1]]);
// or if no default endpoint was given in the config:
$response = $refreshableClient->get($api->getGbPayrollAPI('payruns'), ['query' => ['page' => 1]]);


// Assuming all is fine, parse the response to an array.
$bodyArray = XeroPHP\Helper::parseResponse($response);

// Instantiate the response data object.
$result = new XeroPHP\ResponseData($bodyArray);

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

The results object provides access to structured data of resources fetched from the API.
It is a value object, and does not provide any ORM-like functionality.

Each `ResponseData` object can act as a single resource or an itterable collection of
resources. The collection functionality is not particularly fancy, but it has
`count()`, and will iterate over a `foreach` loop.

The root `ResponseData` object provides access to its collection of resources using
`getResources()` and to its single resource using `getResource()`.

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

Response Structures
-------------------

Each response will be in one of a number of structures.
We have given each structure an arbitrary letter to identify it, and listed them below.

### A: Single metadata header; single resource

The resource is in a single node, usually named after the resource content, but not always.
Examples include fetching a single Payrun in the GB Payroll v2.0 API.

<img src="https://github.com/academe/XeroPHP/raw/master/docs/images/A.png" alt="Response Format A" width="270">

### B: Single metadata header; collection of resources

The resources are in an array under a single node, usually named after the resource content, but not always.
Examples include fetching a multiple Payruns in the GB Payroll v2.0 API.
Paging metadata is in an object of its own.

<img src="https://github.com/academe/XeroPHP/raw/master/docs/images/B.png" alt="Response Format B" width="270">

### C: Single metadata header; collection of a single resource

Some APIs will return both a single resource and multiple resources in an array, with no paging metdata.
Distinguishing between the response to requesting a single resource, or matching a single resource in
a resource collection, is not possible; the response looks the same withou looking at more details of the
content of the resource.
Examples include fetching a single payment from the Accounting v2.0 API.

<img src="https://github.com/academe/XeroPHP/raw/master/docs/images/C.png" alt="Response Format C" width="270">

### D: Single metadata header; collection of resources

This structure includes some metadata at the root node, including paging details that are not
wrapped into an object.
Examples include fetching a multiple files from the Files v1.0 API.

<img src="https://github.com/academe/XeroPHP/raw/master/docs/images/D.png" alt="Response Format D" width="270">

### E: Array of resources

Some APIs will return an array of resources with no metadata at all, no paging details, no source details.

<img src="https://github.com/academe/XeroPHP/raw/master/docs/images/E.png" alt="Response Format E" width="270">

### F: Single resources

Similar to format E, the response contains a single resource, not wrapped into a field or object,
and with no metadata to provide context.

<img src="https://github.com/academe/XeroPHP/raw/master/docs/images/F.png" alt="Response Format F" width="270">

There are a number of different formats used to deliver error messages and exceptions (at least four different
structures). These will be documented shortly, as they need to be handled using the same rules.

I suspect at least two of these stcutures can be merged into one.

Other Notes
-----------

I have noticed the occassional 401 which then works on a retry. Using the Guzzle retry
handler would be a good move to avoid unnecessary errors when processing large amounts
of data.
