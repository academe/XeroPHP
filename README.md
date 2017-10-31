XeroPHP API OAuth Access
------------------------

PHP library for working with the Xero OAuth API.

Intro
=====

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
========================

So far development of this package has concentrated on reading from the Xero API.
Writing to the API should be supported, but has not gone through any testing
at this stage.

Tests. Any help is setting some up would be great.

The Results Object
==================

The results object provides access structured data of resources fetched from the API.
It is a value object, and does not provide any ORM-like functionality.

