<?php

use Opencontent\QueryLanguage\Parser\Token;
use Opencontent\QueryLanguage\Parser\TokenFactory;

class OCCustomSearchableTokenFactory extends TokenFactory
{
	public function __construct( $fields, $operators, $parameters, $clauses )
    {
        $this->fields = $fields;
        $this->operators = $operators;
        $this->parameters = $parameters;
        $this->clauses = $clauses;
    }
}