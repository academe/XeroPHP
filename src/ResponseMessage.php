<?php

namespace Academe\XeroPHP;

/**
 * Top level response message.
 * This extracts the metadata and resources.
 * TODO: recognise OAuth responses and set the OAuthParams object as the resource.
 */

use Psr\Http\Message\ResponseInterface;
use Carbon\Carbon;
use InvalidArgumentException;

class ResponseMessage implements \Iterator, \Countable //\JsonSerializable
{
    // New format single resource with header.
    const STRUCTURE_A = 'A';
    // New format resource list with header
    // Files resource list
    const STRUCTURE_B = 'B';
    const STRUCTURE_C = 'C';
    const STRUCTURE_D = 'D';
    // Collection of resources with no header
    // Old format resource list (can be just a single resource)
    const STRUCTURE_E = 'E';
    // Single resource with no wrapper
    const STRUCTURE_F = 'F';
    // Simple error message.
    const STRUCTURE_G = 'G';
    // New format structure error detail.
    const STRUCTURE_H = 'H';

    /**
     * @var array The source data.
     */
    protected $sourceData = [];

    /**
     * Cache the structure type for multiple access.
     */
    protected $dataStructureCache;

    /**
     * Lower-case mapping of field names to provide case-insensitive search.
     */
    protected $index = [];

    /**
     * A single resource, which could be a collection of resources.
     */
    protected $resource;

    /**
     * For interface Iterator
     * Interator current pointer.
     */
    protected $iteratorPosition = 0;

    /**
     *
     */
    public function __construct($data)
    {
        // If the data is a HTTP response, then parse the data out from that.

        if ($data instanceof ResponseInterface) {
            $data = Helper::parseResponse($data);
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                '$data must be an array; %s supplied',
                gettype($data)
            ));
        }

        $this->sourceData = $data;

        // Create an index to help check fields in a case-insensitive way.
        // Should we do this while parsing?

        foreach ($this->sourceData as $key => $value) {
            $this->index[strtolower($key)] = $key;
        }

        // We now want to determine the data structure, extract the resource or
        // resources and put them where they belong, and extract the metadata and
        // put that where it belongs.

        $this->parseSourceData();
    }

    /**
     * Parse the data we have been given.
     */
    protected function parseSourceData()
    {
        // An empty dataset has been provided.

        if (empty($this->sourceData)) {
            return;
        }

        // An numeric-keyed array at the root will be a collection of resources
        // with no metadata to describe them.

        if (Helper::isNumericArray($this->sourceData)) {
            $this->resource = Helper::responseFactory($this->sourceData);
            return;
        }
    }

    /**
     * The data provided is empty - no resource, no resource list and no metadata.
     */
    public function isEmpty()
    {
        return empty($this->sourceData);
    }

    /**
     * @param string $name The name of the field using any letter-case.
     * @return bool True if the source field of that name was supplied.
     */
    public function hasSourceField($name)
    {
        return array_key_exists(strtolower($name), $this->index);
    }

    /**
     * @param string $name The name of the field using any letter-case.
     * @return mixed The value of the source field or null if not set.
     */
    public function getSourceField($name)
    {
        return $this->hasSourceField($name)
            ? $this->sourceData[$this->index[strtolower($name)]]
            : null;
    }

    /**
     * @return array The source data.
     */
    public function getSource()
    {
        return $this->sourceData;
    }

    /**
     * Determine what data structure we have.
     * See notes below: this may be the wrong approach.
     *
     * $return string one of self::STRUCTURE_?
     */
    public function getStructureType()
    {
        if ($this->dataStructureCache !== null) {
            return $this->dataStructureCache;
        }

        // do-while structure being used like a "goto".
        do {
            if ($this->hasSourceField('TotalCount') && $this->hasSourceField('Items')) {
                $this->dataStructureCache = self::STRUCTURE_B;
                break;
            }

// This is a kind of chicken and egg thing. Perhaps we don't need to know the format,
// but we just parse it all as we go along?
//            if ($this->isCollection()) {
//                $this->dataStructureCache = self::STRUCTURE_E;
//                break;
//            }

            if ($this->hasSourceField('message')) {
                if ($this->hasSourceField('httpStatusCode')) {
                    // Old format error of any type
                    $this->dataStructureCache = static::STRUCTURE_G;
                } else {
                    // New format malformed request error
                    $this->dataStructureCache = static::STRUCTURE_G;
                }

                break;
            }

//            if ($this->isAssociative()) {
//                $this->dataStructureCache = self::STRUCTURE_F;
//                break;
//            }
        } while (false);

        return $this->dataStructureCache;
    }


    /**
     * For interface Iterator
     */
    public function rewind()
    {
        $this->iteratorPosition = 0;
    }

    /**
     * For interface Iterator
     */
    public function current()
    {
        // If an array, then return the items in the current position,
        // otherwise return the complete items array as a single (and only)
        // element.

        //if ($this->isCollection()) {
        //    return $this->items[$this->iteratorPosition];
        //} else {
        //    return $this->items;
        //}
    }

    /**
     * For interface Iterator
     */
    public function key()
    {
        return $this->iteratorPosition;
    }

    /**
     * For interface Iterator
     */
    public function next()
    {
        $this->iteratorPosition++;
    }

    /**
     * For interface Iterator
     */
    public function valid()
    {
        //if ($this->isCollection()) {
        //    return isset($this->items[$this->iteratorPosition]);
        //} else {
        //    return ($this->iteratorPosition === 0);
        //}
    }

    /**
     * @return bool True if the response contains a collection of resources.
     */
    public function isCollection()
    {
        return $this->resource instanceof ResourceCollection;
    }

    /**
     * @return bool True if the response contains a single resources.
     */
    public function isResource()
    {
        return $this->resource instanceof Resource;
    }

    /**
     * For interface Countable.
     * The data provided is empty - no resource, no resource list and no metadata.
     */
    public function count()
    {
        if ($this->isEmpty()) {
            return 0;
        }

        // If a collection, then get the count of resources fetched so far.

        if ($this->isCollection()) {
            return count($this->resource);
        }

        // If a resource then the count will be 1.
        if ($this->isResource()) {
            return 1;
        }

        return 0;
    }

    /**
     * Get the collection of resources.
     * @return ResourceCollection
     */
    public function getCollection()
    {
        if ($this->isCollection()) {
            return $this->resource;
        }

        if ($this->isResource()) {
            return new ResourceCollection([$this->resource]);
        }

        return new ResourceCollection();
    }
}
