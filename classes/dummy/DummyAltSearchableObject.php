<?php


class DummyAltSearchableObject extends DummySearchableObject
{
    public function getGuid()
    {
        return 'dummy_alt-' . $this->attributes['id'];
    }
}
