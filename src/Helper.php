<?php

namespace Academe\XeroPHP;

/**
 * Static helper methods.
 */

use Psr\Http\Message\ResponseInterface;
use Carbon\Carbon;

class Helper
{
    /**
     * Convert a persisted and retreived timestamp item to a UTC Carbon object.
     *
     * @param mixed $item
     * @return Carbon|$item Value converted to Carbon if possible, otherwise returned as supplied.
     */
    public static function toCarbon($item)
    {
        if ($item instanceof Carbon) {
            return $item->setTimezone('UTC');
        }

        if ($item instanceof DateTime) {
            return Carbon::instance($item)->setTimezone('UTC');
        }

        if (is_integer($item)) {
            return Carbon::createFromTimestamp($item);
        }

        if (is_string($item)) {
            if (substr($item, 0, 6) === '/Date(') {
                // The Microsoft format date and datetime that some of the older APIs use.

                if (strpos($item, '+') !== false) {
                    list($milli, $offset) = preg_replace('/[^0-9]/', '', explode('+', $item));
                } else {
                    $milli = preg_replace('/[^0-9]/', '', $item);
                    $offset = '00000';
                }

                return Carbon::createFromTimestamp($milli / 1000);
            }

            // One last, clumsy check of the format before we try to convert it.
            // We just look for the "-99T99:" second that is in the middle of all
            // date formats we have encountered so far.
            // This check may have to gom as it throws out, for example, OAuth expiry
            // times that may have been retrieved from the database as strings.

            if (! preg_match('/\-[0-9]{2,2}T[0-9]{2,2}:/', $item)) {
                return $item;
            }

            return Carbon::parse($item)->setTimezone('UTC');
        }

        return $item;
    }

    /**
     * Convert a snake_case string to camelCase.
     * Static helper method.
     *
     * @param string $name
     * @return string
     */
    public static function snakeToCamel($name)
    {
        return lcfirst(
            str_replace(
                '_',
                '',
                ucwords($name, '_')
            )
        );
    }

    /**
     * Convert a parsed response array to a nested ResponseData instance.
     */
    public static function arrayToModel($data)
    {
        return new ResponseData($data);
    }

    /**
     * @return bool true if the data is an associative array.
     */
    public static function isAssociativeArray(array $data)
    {
        return count(
            array_filter(array_keys($data), 'is_string')
        ) > 0;
    }

    /**
     * @return bool true if the data is a numeric keyed array.
     */
    public static function isNumericArray(array $data)
    {
        return count(
            array_filter(array_keys($data), 'is_numeric')
        ) > 0;
    }

    /**
     * Parse an API response body into an array.
     */
    public static function parseResponse(ResponseInterface $response)
    {
        // Strip off the character encoding, e.g. "application/json; charset=utf-8"
        list($contentType) = explode(';', $response->getHeaderLine('content-type'));

        switch ($contentType) {
            case 'application/json':
                $data = json_decode((string)$response->getBody(), true);
                break;
                //
            case 'text/xml':
                // This conversion is not so good for navigating due to the way lists
                // of items are converted. Best to avoid if possible.
                $data = json_decode(
                    json_encode(
                        simplexml_load_string(
                            (string)$response->getBody(),
                            null,
                            LIBXML_NOCDATA
                        )
                    ),
                    true
                );
                break;
                //
            case 'text/html':
                // The older format will return a string in the event of an error.
                // If we have a one-line string, we will wrap it into the simple message
                // array that the new format uses when a rrequest is malformed.
                // TODO: also look out for URL-encoded parameters injected by Xero's
                // OAuth middleware.
                $data = [
                    'message' => (string)$response->getBody(),
                    'httpStatusCode' => $response->getStatusCode(),
                ];
                break;
                //
            default:
                $data = (string)$response->getBody();
                break;
        }

        // Some APIs will return a single error string with a variety of different
        // claimed content types.
        if (is_string($data)) {
            return [
                'message' => $data,
                'httpStatusCode' => $response->getStatusCode(),
            ];
        }

        return $data;
    }

    /**
     * Parse a data item.
     *
     * @parem mixed $data
     * @return mixed Return Collection, Resource, Carbon or scalar value
     */
    public static function responseFactory($data, $name = '')
    {
        // Check for a date format first.

        if (is_string($data) && $name) {
            $lcName = strtolower($name);
            $isDate = false;

            if (substr($lcName, -3) === 'utc') {
                $isDate = true;
            }

            if (substr($lcName, -8) === 'datetime') {
                $isDate = true;
            }

            if (substr($lcName, -4) === 'date') {
                $isDate = true;
            }

            if (substr($lcName, 0, 11) === 'dateofbirth') {
                $isDate = true;
            }

            if ($isDate) {
                $data = static::toCarbon($data);
            }
        }

        if (is_scalar($data)) {
            return $data;
        }

        // An empty array is assumed to be an empty collection.
        if (is_array($data) && (static::isNumericArray($data) || empty($data))) {
            return new ResourceCollection($data);
        }

        if (is_array($data) && static::isAssociativeArray($data)) {
            return new Resource($data);
        }

        // A Carbon date or something else.
        return $data;
    }
}
