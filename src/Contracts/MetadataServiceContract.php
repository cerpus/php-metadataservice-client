<?php

namespace Cerpus\MetadataServiceClient\Contracts;

/**
 * Interface MetadataServiceContract
 * @package Cerpus\MetadataServiceClient\Contracts
 */
interface MetadataServiceContract
{
    public const METATYPE_DIFFICULTY = 'difficulty';
    public const METATYPE_EDUCATIONAL_LEVEL = 'levels';
    public const METATYPE_EDUCATIONAL_STANDARD = 'educational_standards';
    public const METATYPE_EDUCATIONAL_USES = 'educational_uses';
    public const METATYPE_ESTIMATED_DURATION = 'estimated_duration';
    public const METATYPE_KEYWORDS = 'keywords';
    public const METATYPE_LANGUAGES = 'languages';
    public const METATYPE_LEARNING_GOALS = 'learning_goals';
    public const METATYPE_MATERIAL_TYPES = 'material_types';
    public const METATYPE_PRIMARY_USERS = 'primary_users';
    public const METATYPE_PUBLIC_STATUS = 'public';
    public const METATYPE_SUBJECTS = 'subjects';
    public const METATYPE_TARGET_AUDIENCE = 'target_audiences';

    public const ENTITYTYPE_ACTIVITY = 'activity';
    public const ENTITYTYPE_COURSE = 'course';
    public const ENTITYTYPE_LEARNINGGOAL = 'learninggoal';
    public const ENTITYTYPE_LEARNINGOBJECT = 'learningobject';
    public const ENTITYTYPE_MODULE = 'module';
    public const ENTITYTYPE_RESOURCE = 'resource';
    public const ENTITYTYPE_USER = 'user';

    public function setEntityType($entityType);

    public function setEntityId($entityId);

    public function getData($metaType);

    public function getAllMetaData();

    public function createData($metaType, $data);

    public function createDataFromArray(array $dataArray);

    public function deleteData($metaType, $metaId);

    public function updateData($metaType, $metaId, $data);

    public function addGoal($goalId);

    public function getUuid($create = false);

    public function getKeywords();

    public function searchForKeywords(string $searchText);

    public function getCustomFieldDefinition(string $fieldName);

    public function addCustomFieldDefinition(string $fieldName, string $dataType, bool $isCollection = false, bool $requiresUniqueValues = false);

    public function getCustomFieldValues(string $fieldName);

    public function setCustomFieldValue(string $fieldName, $value);

    public function setCustomFieldValues(string $fieldName, $value, bool $deduplicateFieldValues = false);

    public function addCustomFieldCollectionValues(string $fieldName, $values = null, bool $deduplicateFieldValues = false);

    public function fetchAllCustomFields(): array;

    public function getLearningObject(bool $create = false);
}
