<?php

declare(strict_types=1);

namespace Alfa;

use PDO;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Component\VarDumper\VarDumper;

class Query
{
    public function __construct(
        protected PDO $database
    )
    {}

    public function insert(Entity $entity) : string
    {
        $entityPersistenceInformation = $this->entityPersistenceInformation($entity);

        $query = sprintf(
            "insert into %s (%s) values (%s)",
            $entityPersistenceInformation->tableName,
            implode(", ", $entityPersistenceInformation->columns),
            implode(", ", $entityPersistenceInformation->columnsBind)
        );

        $stmt = $this->database->prepare($query);

        foreach ($entityPersistenceInformation->columnsBind as $chave => $column) {
            $stmt->bindValue(
                $column, 
                $entityPersistenceInformation->columnsValue[$chave]
            );
        }

        $stmt->execute();

        return $this->database->lastInsertId();


    }

    public function find(int|string $id, string $className) : ?Entity
    {
        $entityPersistenceInformation = $this->classNamePersistenceInformation($className);
        
        $stmt = $this->database->prepare(
            sprintf(
                "select %s from %s where %s = :id",
                implode(",", $entityPersistenceInformation->columns),
                $entityPersistenceInformation->tableName,
                $entityPersistenceInformation->primaryKey
            )
        );

        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            return $this->recordToEntity(
                $record, 
                $className, 
                $entityPersistenceInformation->columnsType
            );
        }

        return null;

    }

    public function findAll(string $className) : array
    {
        $entityPersistenceInformation = $this->classNamePersistenceInformation($className);
        
        $stmt = $this->database->prepare(
            sprintf(
                "select %s from %s",
                implode(",", $entityPersistenceInformation->columns),
                $entityPersistenceInformation->tableName
            )
        );

        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnsType = $entityPersistenceInformation->columnsType;

        return array_map(function ($record) use ($className, $columnsType){
            return $this->recordToEntity(
                $record, 
                $className, 
                $columnsType
            );
        }, $records);
        
    }

    public function delete(Entity $entity): bool
    {
        $entityPersistenceInformation = $this->entityPersistenceInformation($entity);
        $stmt = $this->database->prepare(
            sprintf("delete from %s where %s = :id",
                $entityPersistenceInformation->tableName,
                $entityPersistenceInformation->primaryKey
            )
        );

        $stmt->bindParam(':id', $entity->{$entityPersistenceInformation->primaryKey});
        return $stmt->execute();        

    }

    public function update(Entity $entity): Entity
    {
        $entityPersistenceInformation = $this->entityPersistenceInformation($entity);
        $columnsValue = $entityPersistenceInformation->columnsValue;
        $columnsType = $entityPersistenceInformation->columnsType;
        
        $sets = array_map(function($column, $columnValue) use ($columnsType){
            $delimiter = ($columnsType[$column] === 'string' ) ? "'" : "";
            return $column."=".$delimiter.$columnValue.$delimiter;
        }, $entityPersistenceInformation->columns, $columnsValue);

        $sql = sprintf(
            "update %s set %s where %s = :id",
            $entityPersistenceInformation->tableName,
            implode(", ", $sets),
            $entityPersistenceInformation->primaryKey
        );
        
        $stmt = $this->database->prepare($sql);
        $stmt->bindParam(':id', $entity->{$entityPersistenceInformation->primaryKey});
        $stmt->execute();

        return $this->find(
                $entity->{$entityPersistenceInformation->primaryKey}, 
                $entity::class
        );

    }

    protected function recordToEntity($record, $className, $columnsType) : Entity
    {
        $entity = new $className();
        foreach ($record as $columnName => $value) {
            if ($columnsType[$columnName] === 'int')
                $value = (int) $value;    
            $entity->{$columnName} = $value;
        }
        return $entity;
    }

    protected function entityPersistenceInformation(Entity $entity) : stdClass
    {
        return $this->classNamePersistenceInformation(get_class($entity), $entity);
    }

    protected function classNamePersistenceInformation(string $entityName, Entity $entity = null) : stdClass
    {
        $tableInformation = new stdClass;
        $reflector = new ReflectionClass($entityName);
        $classAttributes = $reflector->getAttributes(Table::class);
        $tableInformation->tableName = $classAttributes[0]->getArguments()['name'];

        $properties = $reflector->getProperties();

        foreach ($properties as $property) {
            $reflectionProperty = new ReflectionProperty($property->class, $property->name);
            $propertyAttributes = $reflectionProperty->getAttributes();
            foreach ($propertyAttributes as $propertyAttribute) {

                if ($propertyAttribute->getName() === PrimaryKey::class) {
                    $tableInformation->primaryKey = $property->name;
                }
                
                if (!is_null($entity) && 
                    $propertyAttribute->getName() === Column::class && 
                    !empty($entity->{$property->name})) {
                        $tableInformation->columns[] = $property->name;
                        $tableInformation->columnsBind[] = sprintf(":%s", $property->name);
                        $tableInformation->columnsValue[] = $entity->{$property->name};
                        $tableInformation->columnsType[$property->name] = 
                        $reflectionProperty->getType()->getName();
                }

                if (is_null($entity) 
                    && $propertyAttribute->getName() == Column::class) {
                        $tableInformation->columns[] = $property->name;
                        $tableInformation->columnsType[$property->name] = 
                        $reflectionProperty->getType()->getName();
                    }


            }
        }

        return $tableInformation;

    }

}