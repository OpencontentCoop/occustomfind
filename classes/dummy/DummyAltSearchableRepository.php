<?php


class DummyAltSearchableRepository extends DummySearchableRepository
{
    public function getIdentifier()
    {
        return 'dummy_alt';
    }

    public function availableForClass()
    {
        return DummyAltSearchableObject::class;
    }
}
