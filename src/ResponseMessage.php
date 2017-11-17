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

class ResponseMessage //implements \JsonSerializable, \Iterator, \Countable
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

        foreach ($this->sourceData as $key => $value) {
            $this->index[strtolower($key)] = $key;
        }

        // We now want to determine the data structure, extract the resource or
        // resources and put them where they belong, and extract the metadata and
        // put that where it belongs.
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
            if ($this->hasSourceField('providerName')) {
                if ($this->hasSourceField('status')) {
                    $this->dataStructureCache = self::STRUCTURE_C; // Or D
                    break;
                }

                if ($this->hasSourceField('httpStatusCode')) {
                    if ($this->getSourceField('problem') === null) {
                        $this->dataStructureCache = self::STRUCTURE_H;
                        break;
                    }

                    if ($this->getSourceField('pagination') === null) {
                        $this->dataStructureCache = self::STRUCTURE_A;
                    } else {
                        $this->dataStructureCache = self::STRUCTURE_B;
                    }

                    break;
                }
            }

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
}
