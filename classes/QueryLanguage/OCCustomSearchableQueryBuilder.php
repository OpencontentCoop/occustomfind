<?php

use Opencontent\QueryLanguage\Converter\Exception;
use Opencontent\QueryLanguage\QueryBuilder;
use Opencontent\QueryLanguage\Query;


class OCCustomSearchableQueryBuilder extends QueryBuilder
{

	public $fields = array();

	public $parameters = array(
        'sort',
        'limit',
        'offset',
        'facets',
    );

    public $operators = array(
        '=',        
        'in',
        '!in',        
        'range',
        '!range',
    );

	public function __construct(OCCustomSearchableRepositoryInterface $repository)
	{
		foreach ($repository->getFields() as $field) {
			$this->fields[] = $field->getName();
		}

		$this->tokenFactory = new OCCustomSearchableTokenFactory( $this->fields, $this->operators, $this->parameters, $this->clauses );
		$this->converter = new OCCustomSearchableQueryConverter($repository);
	}

}