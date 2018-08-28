<?php

namespace Cerpus\MetadataServiceClient\Contracts;

/**
 * Interface MetadataServiceContract
 * @package Cerpus\MetadataServiceClient\Contracts
 */
interface MetadataServiceContract
{
    public function setEntityType($entityType);

    public function setEntityId($entityId);

    public function getData($metaType);

    public function getAllMetaData();

    public function createData($metaType, $data);

    public function createDataFromArray(Array $dataArray);

    public function deleteData($metaType, $metaId);

    public function updateData($metaType, $metaId, $data);

    public function addGoal($goalId);

    public function getUuid($create = false);


}