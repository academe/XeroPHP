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

class ResponseMessage implements \Iterator, \Countable, \JsonSerializable
{
    /**
     * @var array The source data.
     */
    protected $sourceData = [];

    /**
     * Lower-case mapping of field names to provide case-insensitive search.
     */
    protected $index = [];

    /**
     * A single resource, which could be a collection of resources.
     */
    protected $resource;

    /**
     * @var Resource The pagination object.
     */
    protected $paginationResource;

    /**
     * @var Resource The non-pagination, non-error and non-resource details.
     */
    protected $metadataResource;

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

        // We now want to determine the data structure, extract the resource or
        // resources and put them where they belong, and extract the metadata and
        // put that where it belongs.

        $this->parseSourceData();
    }

    /**
     * Check if a field has been provided by the API.
     *
     * @param string $name The name of the field.
     * @return bool true if the item was provided, even if it was null.
     */
    public function has($name)
    {
        // The index alone has everything we need.
        return array_key_exists(strtolower($name), $this->index);
    }

    /**
     *
     */
    public function toArray()
    {
        $array = [
            'pagination' => $this->getPagination()->toArray(),
            'metadata' => $this->getMetadata()->toArray(),
            // TODO: errors and exceptions
        ];

        if ($this->isCollection()) {
            $array['resources'] = $this->getCollection()->toArray();
        }

        return $array;
    }

    /**
     * For interface \JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->toArray();
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
     * Parse the data we have been given.
     */
    protected function parseSourceData()
    {
        // An empty dataset has been provided.

        if (empty($this->sourceData)) {
            return;
        }

        // Create an index to help check fields in a case-insensitive way.
        // Should we do this while parsing?

        foreach ($this->sourceData as $key => $value) {
            $this->index[strtolower($key)] = $key;
        }

        $hasMetadata = false;

        // TODO: think the pagination through a little more, expecially wrt single resources.

        if ($this->has('pagination')) {
            if (isset($this->pagination)) {
                // TODO: some endpoints put the pagination fields at the root.
                $pagination = $this->getSourceField('pagination');
                $paginationData = [
                    // TODO: some endpoints provide different pagination field names.
                    'page' => isset($pagination['page']) ? $pagination['page'] : -1,
                    'pageSize' => isset($pagination['pageSize']) ? $pagination['pageSize'] : 100,
                    'pageCount' => isset($pagination['pageCount']) ? $pagination['pageCount'] : -1,
                    'itemCount' => isset($pagination['itemCount']) ? $pagination['itemCount'] : -1,
                ];
            } else {
                // An empty pagination field means either one resource or there was an error.
                // We'll go for one pagination field and fix for errors later.
                $paginationData = [
                    'page' => 1,
                    'pageSize' => 100,
                    'pageCount' => 1,
                    'itemCount' => 1,
                ];
            }
        } else {
            // No pagination field at all, so pagination details are largely unknown.
            $paginationData = [
                'page' => -1,
                'pageSize' => 100,
                'pageCount' => -1,
                'itemCount' => -1,
            ];
        }

        $this->paginationResource = new Resource($paginationData);

        $resourceName = null;

        do {
            // An numeric-keyed array at the root will be a collection of resources
            // with no metadata to describe them.

            if (Helper::isNumericArray($this->sourceData)) {
                $this->resource = new ResourceCollection($this->sourceData);
                break;
            }

            if ($this->has('providerName')) {
                $hasMetadata = true;

                // Locate the resource array.
                $resourceName = $this->findResourceField();

                if ($this->has('httpStatusCode')) {
                    if ($this->has('pagination')) {
                        if (isset($this->pagination)) {
                            // A multi-resource collection with a pagination object.
                            // TODO: test an empty collection is created for no matches.
                            // e.g. is the resource field ever simply not returned, or returned
                            // as a null instead of an empty array?

                            if ($resourceName) {
                                $this->resource = new ResourceCollection($this->sourceData[$resourceName]);
                                break;
                            }
                        } else {
                            // A single resource (signalled by an empty pagination).
                            // TODO: an empty pagination could also be a 404 response.

                            if ($resourceName) {
                                $this->resource = new Resource($this->sourceData[$resourceName]);
                                break;
                            }
                        }
                    }
                }

                if ($this->has('status')) {
                    // Older format, no pagination, resource will always be in
                    // an array whether fetching just one or many.

                    $hasMetadata = true;

                    if ($resourceName) {
                        $this->resource = new ResourceCollection($this->sourceData[$resourceName]);
                        break;
                    }
                }
            }

            // Fallback - just a single resource on its own.

            if (Helper::isAssociativeArray($this->sourceData)) {
                $this->resource = new Resource($this->sourceData);
                break;
            }
        } while (false);

        // Now collect together the remaining fields as metadata.

        $metadata = [];

        if ($hasMetadata) {
            foreach ($this->sourceData as $name => $item) {
                $lcName = strtolower($name);

                if ($lcName === 'pagination' || $lcName === 'problem' || $name === $resourceName) {
                    continue;
                }

                $metadata[$name] = Helper::responseFactory($item, $name);
            }
        } else {
            // TODO: Set some default metadata fields?
        }

        // CHECKME: do we need to add any common metadata fields or made-up metadata fields?
        $this->metadataResource = new Resource($metadata);
    }

    /**
     * Return the pagination object.
     */
    public function getPagination()
    {
        return $this->paginationResource;
    }

    /**
     * Return the metadata object.
     */
    public function getMetadata()
    {
        return $this->metadataResource;
    }

    /**
     * Find the resource or resources element name in the source data.
     * The assumption is that it will be the first array we find, skipping
     * over some metadata fields we know about.
     *
     * @return string|null The field name or null if none found.
     */
    protected function findResourceField()
    {
        foreach ($this->sourceData as $name => $item) {
            $lcName = strtolower($name);

            if ($lcName === 'pagination' || $lcName === 'problem') {
                // Some objects at the root level are definitely not resources.
                continue;
            }

            if (is_array($item)) {
                return $name;
            }
        }

        // No resource fields found: null result.
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
     * @param string $name The name of the field using any letter-case
     * @param mixed $default Value if the source field does not exist
     * @return mixed The value of the source field or null if not set
     */
    public function getSourceField($name, $default = null)
    {
        return $this->hasSourceField($name)
            ? $this->sourceData[$this->index[strtolower($name)]]
            : $default;
    }

    /**
     * @return array The source data.
     */
    public function getSourceData()
    {
        return $this->sourceData;
    }

    /**
     * For interface Iterator
     */
    public function rewind()
    {
        if ($this->isCollection()) {
            $this->resource->rewind();
        }

        if ($this->isResource()) {
            $this->iteratorPosition = 0;
        }
    }

    /**
     * For interface Iterator
     */
    public function current()
    {
        if ($this->isCollection()) {
            return $this->resource->current();
        }

        if ($this->isResource()) {
            return $this->resource;
        }
    }

    /**
     * For interface Iterator
     */
    public function key()
    {
        if ($this->isCollection()) {
            return $this->resource->key();
        }

        if ($this->isResource()) {
            return $this->iteratorPosition;
        }
    }

    /**
     * For interface Iterator
     */
    public function next()
    {
        if ($this->isCollection()) {
            return $this->resource->next();
        }

        if ($this->isResource()) {
            $this->iteratorPosition++;
        }
    }

    /**
     * For interface Iterator
     */
    public function valid()
    {
        if ($this->isCollection()) {
            return $this->resource->valid();
        }

        if ($this->isResource()) {
            return $this->iteratorPosition === 0;
        }
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

    /**
     * Get the single resource, or the first resource if there are many.
     * Return an empty resource if there are none
     *
     * @return Resource
     */
    public function getResource()
    {
        if ($this->isResource()) {
            return $this->resource;
        }

        if ($this->isCollection()) {
            return $this->resource->first();
        }

        return new Resource();
    }

    /**
     * Get the first resource, which may be the only resource.
     * Default to an empty resource if there are none.
     *
     * @return Resource
     */
    public function first()
    {
        return $this->getResource();
    }
}
