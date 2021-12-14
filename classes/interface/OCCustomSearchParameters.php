<?php


class OCCustomSearchParameters implements JsonSerializable
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var array
     */
    private $filters = array();

    /**
     * @var array
     */
    private $rawFilters = array();

    /**
     * @var int
     */
    private $limit = 10;

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var array
     */
    private $facets = array();

    /**
     * @var array
     */
    private $sort = array();

    public static function instance(array $data = null)
    {
        $instance = new OCCustomSearchParameters;
        
        if (is_array($data)){
            foreach ($data as $key => $value) {
                if (property_exists($instance, $key)){
                    $instance->{$key} = $value;
                }
            }
        }

        return $instance;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     *
     * @return OCCustomSearchParameters
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     *
     * @return OCCustomSearchParameters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }

    public function addFilter($name, $value)
    {
        $this->filters[$name] = $value;

        return $this;
    }

    public function addRawFilter($value)
    {
        $this->rawFilters[] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getRawFilters(): array
    {
        return $this->rawFilters;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     *
     * @return OCCustomSearchParameters
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     *
     * @return OCCustomSearchParameters
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return array
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * @param array $facets
     *
     * @return OCCustomSearchParameters
     */
    public function setFacets($facets)
    {
        $this->facets = $facets;

        return $this;
    }

    /**
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @param array $sort
     *
     * @return OCCustomSearchParameters
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }

    public function jsonSerialize()
    {
        $vars = get_object_vars($this);

        return $vars;
    }

}
