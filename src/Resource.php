<?php

namespace Academe\XeroPHP;

/**
 * Collecton of resources.
 */

use Exception;

class Resource implements \Countable //\Iterator,  //\JsonSerializable
{
    /**
     *
     */
    protected $properties = [];

    /**
     *
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $name => $item) {
            // TODO: sort out letter case.
            $this->properties[$name] = Helper::responseFactory($item, $name);
        }
    }

    /**
     *
     */
    public function count()
    {
        return 1;
    }
}
