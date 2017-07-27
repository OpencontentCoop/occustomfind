<?php

class DummySearchableObject extends OCCustomSearchableObjectAbstract
{

    public function getGuid()
    {
        return 'dummy-' . $this->attributes['id'];
    }

    public static function getFields()
    {
        return array(
            OCCustomSearchableField::create('id', 'int'),
            OCCustomSearchableField::create('dummy', 'string'),
            OCCustomSearchableField::create('foo', 'text'),
            OCCustomSearchableField::create('bar', 'string[]')
        );
    }
}
