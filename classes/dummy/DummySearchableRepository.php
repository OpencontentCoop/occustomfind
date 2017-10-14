<?php


class DummySearchableRepository extends OCCustomSearchableRepositoryAbstract
{
    public function getIdentifier()
    {
        return 'dummy';
    }

    public function availableForClass()
    {
        return 'DummySearchableObject';
    }

    public function countSearchableObjects()
    {
        return 10;
    }

    public function fetchSearchableObjectList($limit, $offset)
    {
        $data = array();
        for ($i = $offset; $i < $this->countSearchableObjects(); $i++) {
            if (count($data) == $limit) {
                break;
            }

            $prefix = $this->getIdentifier();
            $suffix = ( $i & 1 ) ? ' even' : ' odd';

            $class = $this->availableForClass();
            $data[] = new $class(array(
                'id' => $i,
                'dummy' => $prefix . ' field dummy ' . $i,
                'foo' => $prefix . ' field foo ' . $i,
                'bar' => array(
                    'field bar' . $suffix,
                    'field bar2' . $suffix,
                )
            ));
        }

        return $data;
    }

    public function fetchSearchableObject($objectID)
    {
        $className = $this->availableForClass();
        return new $className();
    }


}
