<?php

namespace Academe\XeroPHP;

/**
 * TODO: special handling for pagination where available.
 */

use Carbon\Carbon;

class ResponseData implements \JsonSerializable, \Iterator, \Countable
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
     * Original source data.
     */
    protected $data = [];

    /**
     * The name of the element this data came from.
     */
    protected $name;

    /**
     * Lower-case mapping of field names to provide case-insensitive search.
     */
    protected $index = [];

    /**
     * Expanded items.
     */
    protected $items = [];

    /**
     * Interator current pointer.
     */
    protected $iteratorPosition = 0;

    /**
     * The parent data onject.
     */
    protected $parent;

    /**
     * Cache the structure type for multiple access.
     */
    protected $typeCache;

    // Can be support other kinds of iterables?
    public function __construct(array $data = [], $name = null, self $parent = null)
    {
        $this->data = $data;

        if ($name !== null) {
            $this->name = $name;
        }

        if ($parent !== null) {
            $this->parent = $parent;
        }

        if ($this->isAssociative()) {
            // Data has associatuve keys.
            // Details of one resource.
            foreach ($data as $name => $item) {
                $this->index[strtolower($name)] = $name;

                $this->items[$name] = $this->parseItem($item, $name);
            }
        } elseif (! empty($data)) {
            // Numeric keys.
            // An array of resource objects.

            foreach ($data as $key => $item) {
                // The name will normally be the singular of the outer element name.
                // The XML response format provides this name, but the JSON format
                // does not.

                $this->items[] = $this->parseItem($item);
            }
        }
    }

    /**
     * Parse the value of a resource field, if necessary.
     * Dates will be converted to Carbon, arrays to a child collection,
     * and everything else will be left as supplied.
     */
    protected function parseItem($value, $name = null)
    {
        if (is_array($value)) {
            return new static($value, $name, $this);
        }

        $lcName = strtolower($name);

        if (substr($lcName, -3) === 'utc') {
            return Helper::toCarbon($value);
        }

        if (substr($lcName, -8) === 'datetime') {
            return Helper::toCarbon($value);
        }

        if (substr($lcName, -4) === 'date') {
            return Helper::toCarbon($value);
        }

        return $value;
    }

    public function toDate($value)
    {
        // For now, return a DateTime Carbon object.
        // The time will be set to 00:00:00
        return Helper::toCarbon($value);
    }

    /**
     * For interface \JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Get an individual property, a field.
     * The property may be a scalar, or may be another object.
     * If the property does not exist, then an empty self will be returned.
     *
     * TODO: refactor the method body into a get() method, and allow scalar defaults
     * to be set for unset properties. It is unclear how walking the structure with
     * a default would work through, as only the very last data object in the chain
     * can return the default, ad the last does not know it's the last. So a helper
     * function to wrap a data walk maybe?
     */
    public function __get($name)
    {
        $lcName = strtolower($name);

        // With the "_raw" suffix we return the unadulterated raw item.
        if (substr($lcName, -4) === '_raw') {
            return $this->data[substr($name, 0, -4)];
        }

        // The property is not set at all. Return an empty object
        // instead.
        if (! isset($this->index[$lcName])) {
            return new static([], $name, $this);
        }

        $value = $this->items[$this->index[$lcName]];

        // If the API has returned an explicit null for a field, then return
        // an empty data object instead, so we can navigate through it without
        // raising an exception.
        if ($value === null) {
            return new static([], $name, $this);
        }

        return $value;
    }

    /**
     * TODO: refactor this a bit - no need to fetch the item - just check the items array;.
     */
    public function __isset($name)
    {
        // If this is an empty node, then it has no properties set.
        if ($this->isEmpty()) {
            return false;
        }

        $item = $this->$name;

        if ($item instanceof self && $item->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * If the data array is not an associative array then it must be a
     * collection array with numeric keys. The collection may be empty.
     *
     * @return bool true if this object is a collection of resources
     */
    public function isCollection()
    {
        return ! $this->isAssociative();
    }

    /**
     * Recursively return the data as a nested array.
     *
     * @param bool $raw Returns the raw data at the node if true.
     */
    public function toArray($raw = false)
    {
        if ($raw) {
            return $this->data;
        }

        $array = [];

        foreach ($this->items as $name => $item) {
            $array[$name] = ($item instanceof self ? $item->toArray() : $item);
        }

        return $array;
    }

    /**
     * @return bool true if this object was created with no data
     */
    public function isEmpty()
    {
        return empty($this->data);
    }

    /**
     * @return bool true if the source data is an associative array; an object
     */
    public function isAssociative()
    {
        return count(
            array_filter(array_keys($this->data), 'is_string')
        ) > 0;
    }

    /**
     * @return self|null The parent object, if not the root data object.
     */
    public function getParent()
    {
        return $this->parent;
    }

    public function hasParent()
    {
        return ! ($this->getParent() === null);
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

        if ($this->isCollection()) {
            return $this->items[$this->iteratorPosition];
        } else {
            return $this->items;
        }
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
        if ($this->isCollection()) {
            return isset($this->items[$this->iteratorPosition]);
        } else {
            return ($this->iteratorPosition === 0);
        }
    }

    /**
     * For interface Countable.
     */
    public function count()
    {
        // getResources() does the heavy lifting of finding where the resource or
        // resources are.
        $resources = $this->getResources();
        return count($resources->data);
    }

    /**
     * Convert an "empty" object to an empty string.
     */
    public function __tostring()
    {
        if ($this->isEmpty()) {
            return '';
        } else {
            throw \Exception('Cannot convert object to string');
        }
    }

    /**
     * Return the resource or resources object.
     */
    public function getResourceField()
    {
        $item = null;

        switch ($this->getStructureType()) {
            case static::STRUCTURE_E:
            case static::STRUCTURE_F:
                $item = $this;
                break;
            case static::STRUCTURE_A:
            case static::STRUCTURE_B:
            case static::STRUCTURE_C:
            case static::STRUCTURE_D:
                foreach ($this->items as $checkItem) {
                    if ($checkItem instanceof static) {
                        if ($checkItem->name !== 'pagination' && $checkItem->name !== 'problem') {
                            $item = $checkItem;
                            break;
                        }
                    }
                }
                break;
        }

        return $item;
    }

    /**
     * Return a collection of resources in the model.
     * If the model is a collection of resources, then $this will be returned.
     * If the model is a single response but has a field that is a collection of
     * resources, the that field will be returned. For example, GET to the
     * Payments endpoint will return a response with a Payments field collection.
     * If the modle is a single resource, then it will be wrapped into a collection???
     */
    public function getResources()
    {
        $resourceField = $this->getResourceField();

        if ($resourceField === null) {
            return new static([], '', $this);
        }

        if ($resourceField->isCollection() || $resourceField->isEmpty()) {
            return $resourceField;
        }

        return new static([$this], '', $this);
    }

    /**
     * Get the single resource, or the first resource if there is a collection.
     */
    public function getResource()
    {
        $resourceField = $this->getResourceField();

        if ($resourceField === null) {
            return new static([], '', $this);
        }

        if (! $resourceField->isCollection() || $resourceField->isEmpty()) {
            return $resourceField;
        }

        $resourceField->rewind();
        return $resourceField->current();
    }

    /**
     * Check if a field has been provided by the API.
     *
     * @param string $name The name of the field.
     * @return bool true if the item was provided, even if it was null.
     */
    public function has($name)
    {
        // Only need to check the index.

        return array_key_exists(strtolower($name), $this->index);
    }

    /**
     * Determine what top-level data structure we have.
     * Only relevant on the root level - or is it? Needs some tests.
     */
    public function getStructureType()
    {
        if ($this->typeCache !== null) {
            return $this->typeCache;
        }

        // do-while structure being used like a "goto".
        do {
            if ($this->has('providerName')) {
                if ($this->has('status')) {
                    $this->typeCache = self::STRUCTURE_C; // Or D
                    break;
                }

                if ($this->has('httpStatusCode')) {
                    if (! $this->problem->isEmpty()) {
                        $this->typeCache = self::STRUCTURE_H;
                        break;
                    }

                    if ($this->pagination->isEmpty()) {
                        $this->typeCache = self::STRUCTURE_A;
                    } else {
                        $this->typeCache = self::STRUCTURE_B;
                    }

                    break;
                }
            }

            if ($this->has('TotalCount') && $this->has('Items')) {
                $this->typeCache = self::STRUCTURE_B;
                break;
            }

            if ($this->isCollection()) {
                $this->typeCache = self::STRUCTURE_E;
                break;
            }

            if ($this->has('message')) {
                if ($this->has('httpStatusCode')) {
                    // Old format error of any type
                    $this->typeCache = static::STRUCTURE_G;
                } else {
                    // New format malformed request error
                    $this->typeCache = static::STRUCTURE_G;
                }

                break;
            }

            if ($this->isAssociative()) {
                $this->typeCache = self::STRUCTURE_F;
                break;
            }
        } while (false);

        return $this->typeCache;
    }
}
