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
* Docs on the Expiry class, which splits OAuthParams into two separate classes.

Quick Start
-----------

```php
use use Academe\XeroPHP;

// Most of the configuration goes into one place.

$clientProvider = new XeroPHP\ClientProvider([
    // Account credentials.
    'consumerKey'    => 'your-consumer-key',
    'consumerSecret' => 'your-consumer-sectet',
    // Current token and secret from storage.
    'oauthToken'           => $oauth_token,
    'oauthTokenSecret'    => $oauth_token_secret,
    // Curresnt session for refreshes also from storage.
    'oauthSessionHandle'   => $oauth_session_handle,
    // Running the Partner Application
    'oauth1Options' => [
        'signature_method' => \GuzzleHttp\Subscriber\Oauth\Oauth1::SIGNATURE_METHOD_RSA,
        'private_key_file' => 'local/path/to/private.pem',
        'private_key_passphrase' => 'your-optional-passphrase',
    ],
    'clientOptions' => [
        // You will almost always want exceptions off, so Guzzle does not throw an exception
        // on every non-20x response.
        'exceptions' => false,
        'headers' => [
            // We would like JSON back for most APIs, as it is structured nicely.
            // Exceptions include 'application/pdf' to download or upload files.
            'Accept' => XeroPHP\ClientProvider::HEADER_ACCEPT_JSON,
        ],
    ],
    // When the token is automatically refreshed, then this callback will
    // be given the opportunity to put it into storage.
    'tokenRefreshCallback' => function($newClientProvider, $oldClientProvider) use ($myStorageObject) {
        // The new token and secret are available here:
        $oauthToken= $newClientProvider->oauthToken;
        $oauthTokenSecret = $newClientProvider->oauthTokenSecret;
        $oauthExpiresAt = $newClientProvider->oauthExpiresAt; // Carbon\Carbon

        // Now those new credentials need storing.
        $myStorageObject->storeTheNewTokenWhereever($oauth_token, $oauth_token_secret, $oauthExpiresAt);
    },
]);

// Get a plain Guzzle client, with appropriate settings.
$refreshableClient = $clientProvider->getRefreshableClient();
```

Now we have a client to send requests.
This is a refreshable client, so if using the Partner app, it will refresh its token
automatically when it expires and inform your application via the `tokenRefreshCallback`.

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

The `ResponseMessage` class is instantiated with the response data, optionally
converted to an array:

```php
// Get the first page of payruns.
// This assumes the payrun Endpoint was supplied as the default endpoint:
$response = $refreshableClient->get('payruns', ['query' => ['page' => 1]]);
// or if no default endpoint was given in the config:
$response = $refreshableClient->get($api->getGbPayrollAPI('payruns'), ['query' => ['page' => 1]]);


// Assuming all is fine, parse the response to an array.
$bodyArray = XeroPHP\Helper::parseResponse($response);

// Instantiate the response data object.
$result = new XeroPHP\ResponseMessage($bodyArray);
// or just the PSR-7 response:
$result = new XeroPHP\ResponseMessage($response);

// Now we can navigate the data.

echo $result->getMetadata()->id;
// 14c9fc04-f825-4163-a0cf-3c2bc31c989d

foreach($result->getCollection() as $payrun) {
    echo $payrun->id . " at " . $payrun->periodStartDate . "\n";
}
// e4df31c9-07db-47d5-a415-6ee32d9048eb at 2017-09-25 00:00:00
// fbd6fc76-dbfc-459d-b230-80334d175048 at 2017-10-20 00:00:00
// 46200d03-67f2-4f5d-8852-cdad50cbe886 at 2017-10-25 00:00:00

echo $result->getPagination()->pageSize;
// 100

var_dump($result->getPagination()->toArray());
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

A `ResponseMessage` object may contain a resource, a collection or resources, or may be empty.
The following methods indicate what the response contains:

```php
if ($result->isCollection()) {
    $collection = $result->getCollection();
}

if ($result->isResource()) {
    $resource = $result->getResource();
}

if ($result->isEmpty()) {
    // No resources - check the metadata to find out why (TODO).
}
```

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
* DateOfBirth (as a prefix)

There is no automatic pagination feature (automatically fetching subsequent pages) when
iterating over a paginated resource.
A decorator class could easily do this though, and that may make a nice addition to take
the logic of "fetching all the matching things" that span more than one page away from
the application.

All other datatypes will be either a scalar the API supplied (string, float, int, boolean)
or another `ResponseData` object containing either a single `Resource` (e.g. "Invoice")
or a `ResourceCollection` (e.g. "CreditNotes").

Accessing properties of this object is case-insensitive.
Accessing a non-existant property will return an empty `Resource`:

```php
$value = $result->foo->bar->where->am_i;
var_dump($value->isEmpty());
// bool(true)
```

But do be aware that when you hit a scalar (e.g. a string) then that is what you will get
back and not a `Resource` object.

The API sometimes returns a `null` for a field or resource rather than simply omitting the
field. Examples are the `pagination` field when fetching a single `payrun`, or the `problem`
field when there is no problem.
(TBC: the pagination and problem fields are a special case and parsed before being presented.)
In this case, when you fetch the value, you will be given an empty `Resource` object
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
However - the root structure of the responses are all normalised by this package so the
application doesn't need to have knoweldge about its details.

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
