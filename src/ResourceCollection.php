<?php

namespace Academe\XeroPHP;

/**
 * Collecton of resources.
 */

use InvalidArgumentException;

class ResourceCollection implements \Countable, \Iterator, \JsonSerializable
{
    /**
     * The collection of resources.
     */
    protected $items = [];

    /**
     * Iterator current pointer.
     */
    protected $iteratorPosition = 0;

    /**
     * Parse each resource in the array.
     * Each resource may itself be a collection or a resource, but never a scalar.
     * The collectrion has numeric keys that are not preserved.
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $item) {
            // Scalars are not allowed, from what I have seen.
            // We may find an exception, but will run with this rule for now.

            if (is_scalar($item)) {
                throw new InvalidArgumentException(sprintf(
                    'ResourceCollection given a scalar "%s"=>"%s" as a resource; not permitted',
                    $key,
                    gettype($item)
                ));
            }

            $this->items[] = Helper::responseFactory($item);
        }
    }

    /**
     * Return the first resource in the collection.
     *
     * @return mixed the first resource in the collection, or null if the collection is empty.
     */
    public function first()
    {
        $this->rewind();
        return $this->current();
    }

    /**
     * @return bool True if no properties are set
     */
    public function isEmpty()
    {
        return count($this->items) === 0;
    }

    /**
     *
     */
    public function toArray()
    {
        $array = [];

        foreach ($this->items as $item) {
            $array[] = $item->toArray();
        }

        return $array;
    }

    /**
     * For interface \JsonSerializable
     */
    public function jsonSerialize()
    {
        $array = $this->toArray();

        array_walk_recursive($array, function (&$value, $key) {
            if ($value instanceof Carbon) {
                $value = (string)$value;
            }
        });

        return $array;
    }

    /**
     * @return bool True - a collection is always a collection.
     */
    public function isCollection()
    {
        return true;
    }

    /**
     * @return bool False - a collecrtion is never a resource.
     */
    public function isResource()
    {
        return false;
    }

    /**
     * For interface Countable.
     * The data provided is empty - no resource, no resource list and no metadata.
     */
    public function count()
    {
        return count($this->items);
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
        return array_key_exists($this->iteratorPosition, $this->items);
    }

    /**
     * For interface Iterator
     */
    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->items[$this->iteratorPosition];
    }

    /**
     * An empty resource will be returned as an empty string, otherwise a JSON
     * encoded string.
     *
     * @return string
     */
    public function __tostring()
    {
        if ($this->isEmpty()) {
            return '';
        }

        return json_encode($this);
    }
}
