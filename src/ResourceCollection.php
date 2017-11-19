<?php

namespace Academe\XeroPHP;

/**
 * Collecton of resources.
 */

use Exception;

class ResourceCollection implements \Countable, \Iterator  //\JsonSerializable
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

            if (is_scalar($data)) {
                throw new Exception(sprintf(
                    'ResourceCollection given a scalar "%s"=>"%s" as a resource; not permitted',
                    $key,
                    gettype($item)
                ));
            }

            $this->items[] = Helper::responseFactory($item);
        }
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
        return $this->items[$this->iteratorPosition];
    }
}
