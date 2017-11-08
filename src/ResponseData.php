<?php

namespace Academe\XeroPHP;

/**
 * TODO: special handling for pagination where available.
 */

use Carbon\Carbon;

class ResponseData implements \JsonSerializable, \Iterator, \Countable
{
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
     * True if the items are a numeric collection of resources.
     */
    protected $isCollection = false;

    /**
     * Interator current pointer.
     */
    protected $iteratorPosition = 0;

    /**
     * The parent data onject.
     */
    protected $parent;

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

        // Is this a numeric array or associative?
        $isAssociative = count(array_filter(array_keys($data), 'is_string')) > 0;

        if ($isAssociative) {
            // Associatuve keys.
            // Details of one resource.
            foreach ($data as $name => $item) {
                $this->index[strtolower($name)] = $name;

                $this->items[$name] = $this->parseItem($item, $name);
            }
        } elseif (! empty($data)) {
            // Numeric keys.
            // An array of resource objects.
            $this->isCollection = true;

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
            return new static($value, $name);
        }

        $lcName = strtolower($name);

        if (substr($lcName, -3) === 'utc') {
            return $this->toDateTime($value);
        }

        if (substr($lcName, -8) === 'datetime') {
            return $this->toDateTime($value);
        }

        if (substr($lcName, -4) === 'date') {
            return $this->toDate($value);
        }

        return $value;
    }

    /**
     * CHECKME: do we ever get a negative timezone offset?
     * FIXME: the timezone offset may or may not be zero, so take it into account.
     * Maybe the offsets need to be converted into a timezone name?
     */
    public function toDateTime($value)
    {
        if (substr($value, 0, 6) === '/Date(') {
            if (strpos($value, '+') !== false) {
                list($milli, $offset) = preg_replace('/[^0-9]/', '', explode('+', $value));
            } else {
                $milli = preg_replace('/[^0-9]/', '', $value);
                $offset = '00000';
            }

            return Carbon::createFromTimestamp($milli / 1000);
        }

        // One last, clumsy check of the format before we try to convert it.
        // We just look for the "-99T99:" second that is in the middle of all
        // date formats we have encountered so far.
        if (!preg_match('/\-[0-9]{2,2}T[0-9]{2,2}:/', $value)) {
            return $value;
        }

        // This will work for most ISO datetime formats.
        return Carbon::parse($value);
    }

    public function toDate($value)
    {
        // For now, return a DateTime Carbon object.
        // The time will be set to 00:00:00
        return $this->toDateTime($value);
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
     * @return bool true if this object is a collection of resources
     */
    public function isCollection()
    {
        return $this->isCollection;
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
     * @return self|null The parent object, if not the root data object.
     */
    public function getParent()
    {
        return $this->parent;
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
     * For interface Countable
     */
    public function count()
    {
        if ($this->isCollection()) {
            // If this is a collection, then each item is a resource.
            return count($this->items);
        } else {
            // This object is a single resource if any items are set,
            // each item being a resource property, or is empty if no
            // items are set.
            return count($this->items) ? 1 : 0;
        }
    }

    /**
     * Return the first resource in the list if this is a collection.
     * TODO: if this is NOT a collection, then look for a collection of
     * resources (i.e. a collection of self) and return the first from that.
     * An alias of "item" and "items" for one level up would be useful.
     */
    public function first()
    {
        $this->rewind();
        return $this->current();
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
}
