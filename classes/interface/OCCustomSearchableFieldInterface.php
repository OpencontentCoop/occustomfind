<?php


interface OCCustomSearchableFieldInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param null $context search (default), sort, filter or facet
     *
     * @return string
     */
    public function getSolrName($context = null);

    /**
     * @return bool
     */
    public function isMultiValued();

    /**
     * @return string
     */
    public function getType();

}
