<?php

namespace DMM;

require_once __DIR__.'/DbAdapter.php';

class Mapper
{
    protected $db;
    
    protected $tableName;
    
    protected $identityFields;
    
    public function __construct(\PDO $pdo, $tableName, $identityFields)
    {
        $this->db = new DbAdapter($pdo);
        $this->tableName = $tableName;
        if (!is_array($identityFields)) {
            $identityFields = array($identityFields);
        }
        $this->identityFields = $identityFields;
    }
    
    /**
     * Returns the SQL condition to identity this model
     * 
     * @param array $identity A hash of field => value for the identity of this object
     * @return string
     */
    private function getIdentityCondition(array $identity)
    {
        $conditions = array();
        foreach ($identity as $key => $value) {
            if (in_array($key, $this->identityFields)) {
                $conditions[] = sprintf("`%s` = '%s'", $key, $value);
            }
        }
        return implode(' AND ', $conditions);
    }

    /**
     * @param array $row
     * @return DMM\BaseDomainModel
     */
    protected function loadItem($row=null)
    {
        if (!$row) return null;
        $item = new $this->modelClass;
        $item->__load($row);
        return $item;
    }

    /**
     * Returns a domain model that matches a given identity
     * 
     * @param array $identity A hash of fieldname => value to use to identify the model
     * @param \DMM\BaseDomainModel
     * @return \DMM\BaseDomainModel
     */
    public function find($identity, BaseDomainModel $model)
    {
        $sql = "SELECT * FROM `%s` WHERE %s";
        $findSql = sprintf($sql, 
            $this->tableName, 
            $this->getIdentityCondition($identity));
        $row = $this->db->fetchRow($findSql);
        return $row ? $model->__load($row) : null;
    }
    
    public function insert(BaseDomainModel $model)
    {
        $this->db->insert($this->tableName, $model->__toArray());
        $id = $this->db->getLastInsertId();
        if ($id) $model->__setIdentity($id);    
        return $this;
    }

    /**
     * @param BaseDomainModel $model
     * @return void
     */
    public function update(BaseDomainModel $model)
    {
        $this->db->update($this->tableName, 
            $model->__toArray(), 
            $this->getIdentityCondition($model->__identity()));
        return $this;
    }

    /**
     * @param domain_model_MagicAccess $model
     * @return domain_mapper_MagicAccess
     */
    public function save(BaseDomainModel $model)
    {
        // We check to see if we can load this model to
        // determine if we insert or update
        $existingModel = $this->find($model->__identity(), clone $model);
        if ($existingModel) {
            $this->update($model);
        } else {
            $this->insert($model);
        }
        return $this;
    }
}


/**
 * Data mapper for domain objects that implement magic access.
 *
 * @package DMM
 */
class domain_mapper_MagicAccess 
{
    /**
     * @var string
     */
    protected $tableName;
    
    /**
     * @var array
     */
    protected $primaryKey;
    
    /**
     * Name of model class that this is the mapper for.
     * 
     * @var string
     */
    protected $modelClass = 'domain_model_MagicAccess';
    
    /**
     * Name of collection class that this is the mapper for.
     * 
     * @var domain_model_Collection
     */
    protected $modelCollectionClass = 'domain_model_Collection';
    
    /**
     * @param db_Access $db
     * @param string $tableName
     * @param string|array $primaryKey
     */
    public function __construct(db_Access $db, $tableName, $primaryKey)
    {
        parent::__construct($db);
        $this->tableName = $tableName;
        if (!is_array($primaryKey)) {
            $primaryKey = array($primaryKey);
        }
        $this->primaryKey = $primaryKey;
    }
    
    /**
     * @param array $rows
     * @return domain_model_Collection
     */
    protected function loadCollection(array $rows)
    {
        $collection = new $this->modelCollectionClass($this->modelClass);
        foreach ($rows as $row) {
            // Note we check to see if the model was successfully loaded before
            // adding.  This allows mappers to subclass the loadItem method and do
            // clever things (good for subsites).
            $item = $this->loadItem($row);
            if ($item) $collection[] = $item;
        }
        return $collection;
    }
    
    /**
     * Inserts a new model into the db
     *  
     * @param domain_model_MagicAccess $model
     * @return void
     */
    public function insert(domain_model_MagicAccess $model)
    {
        $this->db->insert($this->tableName, $model->__toArray());
        $id = $this->db->getLastInsertId();
        if ($id) {
            $model->__setIdentity($id);    
        }
    }
    
    
    
    /**
     * Deletes a model from the db
     * 
     * @param domain_model_MagicAccess $model
     * @return void
     */
    public function delete(domain_model_MagicAccess $model)
    {
        $identity = $model->__identity();
        return $this->db->delete($this->tableName, $this->getIdentityCondition($identity));
    }
    /**
     * Method designed to be subclassed with validation rules
     * 
     * The returned array should be a hash of field name to error message
     * 
     * @param domain_model_MagicAccess $model
     * @return array
     */
    public function getValidationErrors($model)
    {
        // We default to calling the corresponding method on the model
        // object.
        return $model->getValidationErrors();
    }
    
}