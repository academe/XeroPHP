[![Build Status](https://travis-ci.org/academe/XeroPHP.svg?branch=master)](https://travis-ci.org/academe/XeroPHP)
[![Latest Stable Version](https://poser.pugx.org/academe/xero-php/v/stable)](https://packagist.org/packages/academe/xero-php)
[![Total Downloads](https://poser.pugx.org/academe/xero-php/downloads)](https://packagist.org/packages/academe/xero-php)
[![Latest Unstable Version](https://poser.pugx.org/academe/xero-php/v/unstable)](https://packagist.org/packages/academe/xero-php)
[![License](https://poser.pugx.org/academe/xero-php/license)](https://packagist.org/packages/academe/xero-php)

Table of Contents
=================

   * [XeroPHP API OAuth Access](#xerophp-api-oauth-access)
      * [Intro](#intro)
      * [Areas to Complete (TODO)](#areas-to-complete-todo)
      * [Quick Start](#quick-start)
      * [The Response Message](#the-response-message)
         * [Message Instantiation](#message-instantiation)
         * [Response Collections](#response-collections)
      * [Guzzle Exceptions](#guzzle-exceptions)
      * [Catching Errors](#catching-errors)
      * [API Response Structures](#api-response-structures)
         * [A: Single metadata header; single resource](#a-single-metadata-header-single-resource)
         * [B: Single metadata header; collection of resources](#b-single-metadata-header-collection-of-resources)
         * [C: Single metadata header; collection of a single resource](#c-single-metadata-header-collection-of-a-single-resource)
         * [D: Single metadata header; collection of resources](#d-single-metadata-header-collection-of-resources)
         * [E: Array of resources](#e-array-of-resources)
         * [F: Single resources](#f-single-resources)
      * [Other Notes](#other-notes)

XeroPHP API OAuth Access
========================

PHP library for working with the Xero OAuth API.

Intro
-----

This library tackles the following parts of Xero API access:

* Coordinating the OAuth layer to provide secure access.
* Automatically refreshing expired tokens (for Partner Applications).
* Parsing the response into general nested objects.

This package leaves these functions for other packages to handle, thought
does coordinate them:

* All HTTP communications through [Guzzle 6](https://github.com/guzzle/guzzle).
* OAuth request signing to
  [Guzzle OAuth Subscriber](https://github.com/guzzle/oauth-subscriber)
* OAuth authentication recommended through
  [OAuth 1.0 Client](https://github.com/thephpleague/oauth1-client)
* Xero provider for OAuth 1.0 Client recommended using
  [Xero Provider for The PHP League OAuth 1.0 Client](https://github.com/Invoiced/oauth1-xero)
* Storage of the OAuth tokens to your own application.
  A hook is provided so that refreshed tokens can be updated in storage.
* Knowledge of how to navigate the results is left with your application.
  However, the generic nested data object that the response builds, helps to do this.

This package differs from the excellent [calcinai/xero-php](https://github.com/calcinai/xero-php)
package in the following fundamental ways:

* It does not get involved in the process of OAuth authorisation with the end user.
  You need to handle that yourself.
* It does not have fixed models for the responses, but uses a generic model structure
  for resources and collections of resources.
* The UK Payroll v2.0 API is supported by this package.

Which package best suites you, will depend on your use-case. Each have pros and cons.

This package needs the OAuth token and secret gained through authorisation
to access the API, and the session handler token if automatic refreshing is
needed for the Partner Application.

This package does not care what you use at the front end to obtain those tokens.
The two packages recommended above to do this are reliable, well documented,
and focus on just getting that one job done.

I am mostly focusing on getting this working for the Xero Partner app, as I need
a robust librayr that just keeps on running as a scheduled process without losing
the tokens and needing a user to re-authenticate.
Once a Partner app has been authorised, it
should in theory be able to access the Xero account for 10 years, refreshing every
30 minutes. In reality, tokens will get lost - even Xero has downtime that can
result in lost authentication tokens.

Areas to Complete (TODO)
------------------------

* So far development of this package has concentrated on reading from the Xero API.
  Writing to the API should be supported, but has not gone through any testing
  at this stage.
* Lots more documentation and examples.
* More consistent handling of errors. The application should not have to go huntiong
  to find out if the error is in the configuration, the network, the remote application,
  the syntax of the request etc. Those details should all be handed to the application
  on a plate.

Quick Start
-----------

```php
use use Academe\XeroPHP;

// Most of the configuration goes into one place.

$myStorageObject = /* Your OAuth token storage object */

$clientProvider = new XeroPHP\ClientProvider([
    // Account credentials.
    'consumerKey'       => 'your-consumer-key',
    'consumerSecret'    => 'your-consumer-sectet',
    // Current token and secret from storage.
    'oauthToken'            => $myStorageObject->oauthToken,
    'oauthTokenSecret'      => $myStorageObject->oauthTokenSecret,
    // Curresnt session for refreshes also from storage.
    'oauthSessionHandle'    => $myStorageObject->oauthSessionHandle,
    // The local time the OAuth token is expected to expire.
    'oauthExpiresAt'        => $myStorageObject->oauthExpiresAt, // Carbon, Datetime or string
    // Running the Partner Application
    'oauth1Options' => [
        'signature_method' => \GuzzleHttp\Subscriber\Oauth\Oauth1::SIGNATURE_METHOD_RSA, // Default
        'private_key_file' => 'local/path/to/private.pem',
        'private_key_passphrase' => 'your-optional-passphrase', // Optional
    ],
    'clientOptions' => [
        // You will almost always want exceptions off, so Guzzle does not throw an exception
        // on every non-20x response.
        // false is the default if not supplied.
        'exceptions' => false,
        'headers' => [
            // We would like JSON back for most APIs, as it is structured nicely.
            // Exceptions include 'application/pdf' to download or upload files.
            // JSON is the default if not supplied.
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
    // If you want to force a token refresh immediately, then set this option.
    //'forceTokenRefresh' => true,
]);

// Get a plain Guzzle client, with appropriate settings.
// Can pass in an options array to override any of the options set up in
// the `$clientProvider`.

$refreshableClient = $clientProvider->getRefreshableClient();
```

Now we have a client to send requests.
This is a refreshable client, so if using the Xero Partner app, it will refresh its token
automatically when it expires and inform your application via the `tokenRefreshCallback`.

After sending a request, you can check if the token was refreshed for any action you may
want to perform as a result:

```php
if ($refreshableClient->tokenIsRefreshed()) {
    // Maybe save the token or other details, or just log the event.
}
```

If the token is refreshed, then the new token will have been stored by your app through
the `tokenRefreshCallback`.

If you want to refresh the tokens explicitly, before you hit an expired token response,
then it can be done like this:

```php
// Refresh the token and get a new provider back:

$clientProvider = $refreshableClient->refreshToken();

// Use the new $clientProvider if you want to create additional refreshable clients.
// Otherwise just keep using the current $refreshableClient.

// The `$refreshableClient` will now have a new Guzzzle client with a refreshed token.
// The new token details are retrieved from the provider, and can then be stored,
// assuming your callback has not already stored it. Store these three details:

$clientProvider->oauthToken;        // String
$clientProvider->oauthTokenSecret;  // String
$clientProvider->oauthExpiresAt;    // Carbon time
```

That may be more convenient to do, but be aware that unless you set a guard time,
there may be times when you miss an expiry and the request will return an expired
token error.

You may want to check that the expiry time is approaching on each run, and renew the
token explicitly. This check can be used to see if we have entered a "guard window"
preceding the expected expiry time:

```php
// A guard window of five minutes (300 seconds).
// If we have entered the last 300 seconds of the token lifetime,
// then renew it immediately.

if ($refreshableClient->isExpired(60*5)) {
    $refreshableClient->refreshToken();
}
```

The Response Message
--------------------

### Message Instantiation

The `ResponseMessage` class is instantiated with the response data.
Either the `Response` object or the data extracted from the response can be
used to initialise the `ResponseMessage`:

```php
// Get the first page of payruns.
// This assumes the payrun Endpoint was supplied as the default endpoint:
$response = $refreshableClient->get('payruns', ['query' => ['page' => 1]]);

// or if no default endpoint was given in the config:
$response = $refreshableClient->get(
    XeroPHP\Endpoint::createGbPayroll('payruns')->getUrl(),
    ['query' => ['page' => 1]]
);

// Assuming all is fine, parse the response to an array.
$bodyArray = XeroPHP\Helper::parseResponse($response);

// Instantiate the response data object.
$result = new XeroPHP\ResponseMessage($bodyArray);

// OR just use the PSR-7 response without the need to parse it first:
$result = new XeroPHP\ResponseMessage($response);

### Navigating the Response Message

// Now we can navigate the data.

// At the top level will be metadata.

echo $result->getMetadata()->id;
// 14c9fc04-f825-4163-a0cf-3c2bc31c989d

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

The results object provides access to structured data of resources fetched from the API.
It is a value object, and does not provide any ORM-like functionality (e.g. you can't
update it then `PUT` it back to Xero, at least not yet).

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

### Response Collections

// Inside the ResponseMessage will be either a resource or a collection
// of resources.
// If it contains a single resource, then you can still extract it as a
// collection, which will then contain a single resource.

foreach($result->getCollection() as $payrun) {
    echo $payrun->id . " at " . $payrun->periodStartDate . "\n";
}
// e4df31c9-07db-47d5-a415-6ee32d9048eb at 2017-09-25 00:00:00
// fbd6fc76-dbfc-459d-b230-80334d175048 at 2017-10-20 00:00:00
// 46200d03-67f2-4f5d-8852-cdad50cbe886 at 2017-10-25 00:00:00
```

There may be further collections of resources deeper in the data, such as
a list of addresses for a contact.

### Response Dates and Times

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

### Pagination

There is no automatic pagination feature (automatically fetching subsequent pages) when
iterating over a paginated resource.
A decorator class could easily do this though, and that may make a nice addition to take
the logic of "fetching all the matching things" that span more than one page away from
the application (ideally the application would make a query, then loop over the resources
and each page would be lazy-loaded into the collection automatically when going off the
end of the page).

All other datatypes will be either a scalar the API supplied (string, float, int, boolean)
or another `ResponseData` object containing either a single `Resource` (e.g. "Invoice")
or a `ResourceCollection` (e.g. "CreditNotes").

### Resource Properties

Accessing properties of a resource object is case-insensitive.
This decision was made due to the mixed use of letter cases throughout the Xero APIs.

A resource will have properties. Each property may be another resource, a resource
collection, a date or time, or a scalar (string, integer, float).

Accessing a non-existant property will return an empty `Resource`.
Drilling deeper into an empty resource will give you further empty resources.

```php
$value = $result->foo->bar->where->amI;
var_dump($value->isEmpty());
// bool(true)
```

But do be aware that when you hit a scalar (e.g. a string) then that is what you will get
back and not a `Resource` object.

The API sometimes returns a `null` for a field or resource rather than simply omitting the
field. Examples are the `pagination` field when fetching a single `payrun`, or the `problem`
field when there is no problem.
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

*Note: this package now switches off Guzzle exceptions by defaul. You can turn them back on
using this parameter if that is desirable. Token refreshing will work both with or without
excpetions being enabled.*

This option can be used on each request, or set as the default in the `ClientProvider`
instantiation options.
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
* Data errors such as trying to retrieve beyond the last page of results

The places where error details cna be found are:

* OAuth errors will be returned as URL-encoded parameters in the response body.
  The `OAuthParams` class can parse these details and provide some interpretation.
* Request construction errors are returned TBC

API Response Structures
-----------------------

Each response will be in one of a number of structures.
The structures listed below have been identified so far, with the aim that all will
be recognised automatically and normalised to a single resource or collection.

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
