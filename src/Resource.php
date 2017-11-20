<?php

namespace Academe\XeroPHP;

/**
 * Collecton of resources.
 */

use Exception;

class Resource implements \Countable, \JsonSerializable //\Iterator,
{
    /**
     *
     */
    protected $sourceData = [];

    /**
     *
     */
    protected $properties = [];

    /**
     * Lower-case mapping of field names to provide case-insensitive search.
     */
    protected $index = [];

    /**
     *
     */
    public function __construct(array $data = [])
    {
        $this->sourceData = $data;

        foreach ($data as $name => $item) {
            $this->setProperty($name, Helper::responseFactory($item, $name));
        }
    }

    /**
     *
     */
    protected function setProperty($name, $item)
    {
        // Add the item.
        $this->properties[$name] = $item;

        // Add an index entry.
        $this->index[strtolower($name)] = $name;
    }

    /**
     *
     */
    public function count()
    {
        return 1;
    }

    /**
     * @return bool True if no properties are set
     */
    public function isEmpty()
    {
        return count($this->properties) === 0;
    }

    /**
     * @param string $name The property name (case-insensitive)
     * @return bool True if a property has been provided
     */
    public function has($name)
    {
        // The index alone has everything we need.
        return array_key_exists(strtolower($name), $this->index);
    }

    /**
     * For interface \JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the Resource and ResourceCollection objects into arrays.
     */
    public function toArray()
    {
        $array = [];

        foreach ($this->properties as $name => $item) {
            if ($item instanceof Resource || $item instanceof ResourceCollection) {
                $array[$name] = $item->toArray();
                continue;
            }

            // A scalar or another type of object.
            $array[$name] = $item;
        }

        return $array;
    }

    /**
     * @param string $name Name of the property deliered by the API we want teh value of.
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getProperty($name);
    }

    /**
     * @param string $name The name of the field.
     * @return bool True if a data field was supplied and is not null.
     */
    public function __isset($name)
    {
        return $this->has($name) && isset($this->sourceData[$this->index[strtolower($name)]]);
    }

    /**
     * @param moxed $default Will return this value if property not present or delivered as null
     * @return mixed
     */
    public function getProperty($name, $default = null)
    {
        $lcName = strtolower($name);

        // With the "_raw" suffix we return the unadulterated raw item.
        //if (substr($lcName, -4) === '_raw') {
        //    return $this->data[substr($name, 0, -4)];
        //}

        // The property is not set at all. Return an empty object
        // instead.

        if (isset($this->index[$lcName])) {
            $value = $this->properties[$this->index[$lcName]];

            // The API can return an explicit null for some fields, and we will
            // treat those as not set, for the action of getting a value.

            if ($value !== null) {
                return $value;
            }
        }

        return $default === null
            ? new static([], $name, $this)
            : $default;
    }
}
