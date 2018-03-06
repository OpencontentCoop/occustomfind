<?php

use Opencontent\QueryLanguage\Converter\QueryConverter;
use Opencontent\QueryLanguage\Parser\Parameter;
use Opencontent\QueryLanguage\Parser\Sentence;
use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Converter\Exception;

class OCCustomSearchableQueryConverter implements QueryConverter
{

	/**
     * @var Query
     */
    protected $query;

    /**
     * @var OCCustomSearchableRepositoryInterface
     */
    protected $repository;

	/**
     * @var OCCustomSearchParameters
     */
    protected $searchParameters;

    public function __construct(OCCustomSearchableRepositoryInterface $repository)
    {
    	$this->repository = $repository;
    	$this->searchParameters = OCCustomSearchParameters::instance();
    }

	public function setQuery(Query $query)
	{
		$this->query = $query;
	}

	public function convert()
	{
		if ( $this->query instanceof Query )
        {
            $filters = array();
            foreach ( $this->query->getFilters() as $item )
            {
                $filter = $this->parseItem( $item );
                if ( !empty( $filter ) )
                {
                    $filters[] = $filter;
                }
            }
            if ( !empty( $filters ) )
            {
                $this->searchParameters->setFilters($filters);
            }

            foreach ( $this->query->getParameters() as $parameters )
            {
                foreach ( $parameters->getSentences() as $parameter )
                {
                    if ( $parameter instanceof Parameter )
                    {
                        $this->convertParameter( $parameter );
                    }
                }
            }
        }	

        return $this->searchParameters;
	}

	protected function parseItem( Item $item )
    {
        $filters = array();
        if ( $item->hasSentences() || $item->clause == 'or' )
        {
            if ( $item->clause == 'or' )
            {
                $filters[] = (string)$item->clause;
            }

            foreach ( $item->getSentences() as $sentence )
            {
                if ( $sentence->getField() == 'q' )
                {
                    $this->searchParameters->setQuery($this->cleanValue($sentence->stringValue()));
                }
                else
                    $filters[] = $this->convertSentence( $sentence );
            }
        }
        if ( $item->hasChildren() )
        {
            foreach ( $item->getChildren() as $child )
            {
                $filters[] = $this->parseItem( $child );
            }
        }

        return $filters;
    }

    protected function convertParameter( Parameter $parameter )
    {
    	$originalKey = (string)$parameter->getKey();
        $value = $parameter->getValue();

        switch( $originalKey )
        {
            case 'sort':
            {                
                if ( is_array( $value ) )
                {
                    $data = array();
                    foreach( $value as $field => $order )
                    {                        
                        if ( !in_array( $order, array( 'asc', 'desc' ) ) )
                        {
                            throw new Exception( "Can not convert sort order value" );
                        }
                        $data[$field] = $order;
                    }
                    $this->searchParameters->setSort($data);
                }
                else
                {
                    throw new Exception( "Sort parameter require an hash value" );
                }

            } break;

            case 'limit':
            {
                if ( is_array( $value ) )
                {
                    throw new Exception( "Limit parameter require an integer value" );
                }
                else
                {
                    $this->searchParameters->setLimit(intval($value));
                }
            } break;

            case 'offset':
            {
                if ( is_array( $value ) )
                {
                    throw new Exception( "Offset parameter require an integer value" );
                }
                else
                {
                    $this->searchParameters->setOffset(intval($value));
                }
            } break;

            case 'facets':
            {
                $facets = array();
                foreach ($value as $item) {
                	$facets[] = $this->parseFacetQueryValue($item);
                }
                $this->searchParameters->setFacets($facets);
            } break;

            default:
                throw new Exception( "Can not convert $originalKey parameter" );
        }
    }

    protected static function parseFacetQueryValue( $item )
    {
        $item = trim( $item, "'" );
        @list( $field, $sort, $limit, $offset ) = explode( '|', $item );
        return array(
            'field'=> $field,
            'limit' => $limit ? $limit : 100,
            'offset' => $offset ? $offset : 0,
            'sort' => $sort ? $sort : 'count'
        );
    }

    protected function convertSentence( Sentence $sentence )
    {
    	$field = (string)$sentence->getField();
        $operator = (string)$sentence->getOperator();
        $value = $sentence->getValue();

        $value = $this->cleanValue( $value, $field );

        switch ( $operator )
        {
        	case 'in':
            case '!in':
            case 'range':
            case '!range':
            {
            	$value = (array)$value;            	
            	return array($field => array($operator, $value));
            }

            case '=':            
            {
            	return array($field => $value);
            }
                break;

            default:
                throw new Exception( "Operator $operator not handled" );
        }
    }

    protected function cleanValue( $value, $field = null )
    {
        $field = $this->repository->getFieldByName($field);
        if ( is_array( $value ) )
        {
            $data = array();
            foreach( $value as $item )
            {
                $item = str_replace( "\'", "'", $item );
                $item = trim( $item, "'" );
                if ($field instanceof OCCustomSearchableFieldInterface && $field->getType() == 'date' && $item!= '*'){
                	$time = new \DateTime( $item, new \DateTimeZone('UTC') );                    
                    if ( !$time instanceof \DateTime)
                    {
                        throw new Exception( "Problem with date $item" );
                    }
                    $item = '"' . ezfSolrDocumentFieldBase::convertTimestampToDate( $time->format('U') ) . '"';
                }
                $data[] = $item;
            }
        }
        else
        {
            $value = str_replace( "\'", "'", $value );
            $value = trim( $value, "'" );
            if ($field instanceof OCCustomSearchableFieldInterface && $field->getType() == 'date' && $value!= '*'){
            	$time = new \DateTime( $value, new \DateTimeZone('UTC') );                    
                if ( !$time instanceof \DateTime)
                {
                    throw new Exception( "Problem with date $value" );
                }
                $item = '"' . ezfSolrDocumentFieldBase::convertTimestampToDate( $time->format('U') ) . '"';
            }
            $data = $value;
        }
        return $data;
    }
}