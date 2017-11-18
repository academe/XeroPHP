<?php

namespace Academe\XeroPHP;

/**
 * Collecton of resources.
 */

class ResourceCollection //implements \Iterator, \Countable //\JsonSerializable
{
    /**
     * Parse each resource in the array.
     * Each resource may itself be a collection, a resource, or a scalar.
     * A factory may be the way to handle this, to convert a lump of data to
     * an object, since it is needed in several places.
     */
    public function __construct(array $data)
    {
    }
}
