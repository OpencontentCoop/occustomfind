<?php

class OpendataDataset implements JsonSerializable
{
    private $data;

    /**
     * @var eZContentObjectAttribute
     */
    private $context;

    private $guid;

    private $createdAt;

    private $modifiedAt;

    private $creator;

    /**
     * @var OpendataDatasetDefinition
     */
    private $definition;

    public function __construct(array $data, $context, $definition)
    {
        $this->data = $data;
        $this->context = $context;
        $this->definition = $definition;
        foreach ($this->definition->getFields() as $field){
            if (!isset($this->data[$field['identifier']])){
                $this->data[$field['identifier']] = null;
            }
        }
    }

    public function getGuid()
    {
        return $this->guid;
    }

    /**
     * @param mixed $guid
     */
    public function setGuid($guid)
    {
        $this->guid = $guid;
    }

    /**
     * @param null $identifier
     * @return mixed
     */
    public function getData($identifier = null)
    {
        if ($identifier){
            return isset($this->data[$identifier]) ? $this->data[$identifier] : null;
        }
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @return OpendataDatasetDefinition
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * @param OpendataDatasetDefinition $definition
     */
    public function setDefinition($definition)
    {
        $this->definition = $definition;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @param mixed $modifiedAt
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
    }

    /**
     * @return mixed
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param mixed $creator
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
    }

    public function jsonSerialize()
    {
        $data = $this->data;
        $data['_guid'] = $this->getGuid();
        $data['_createdAt'] = $this->getCreatedAt();
        $data['_modifiedAt'] = $this->getModifiedAt();
        $data['_creator'] = $this->getCreator();

        return $data;
    }

}