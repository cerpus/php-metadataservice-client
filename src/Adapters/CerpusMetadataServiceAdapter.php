<?php

namespace Cerpus\MetadataServiceClient\Adapters;

use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use Cerpus\MetadataServiceClient\Exceptions\MetadataServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use Log;
use Ramsey\Uuid\Uuid;
use function GuzzleHttp\json_decode as guzzle_json_decode;

/**
 * Class MetadataServiceAdapter
 * @package Cerpus\MetadataServiceClient\Adapters
 */
class CerpusMetadataServiceAdapter implements MetadataServiceContract
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string|null
     */
    protected $entityType;

    /**
     * @var string|null
     */
    protected $entityId;

    /**
     * @var string
     */
    protected $entityGuid = '';

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var bool
     */
    private $metadataId = false;

    /**
     * metaType => propertyName
     *
     * @var array
     */
    private $propertyMapping = [
        'educational_standards' => 'educationalStandard',
        'educational_uses' => 'educationalUse',
        'keywords' => 'keyword',
        'languages' => 'languageCode',
        'learning_goals' => 'learningGoalId',
        'material_types' => 'materialType',
        'primary_users' => 'primaryUser',
        'subjects' => 'subjectDeweyCode',
        'target_audiences' => 'audience',
        'levels' => 'level',
        'estimated_duration' => 'duration',
        'difficulty' => 'difficulty',
        'public' => 'is_public',
    ];

    private $customFieldDefinitions = [];

    private const LEARNINGOBJECT_URL = '/v1/learningobject/%s/%s';
    private const ENTITY_GUID_URL = '/v1/learningobject/entity_guid/%s';
    private const CREATE_LEARNINGOBJECT_URL = '/v1/learningobject/create';
    private const LEARNINGOBJECT_EDIT_URL = '/v1/learningobject/%s/%s/%s';
    private const LEARNINGOBJECT_LIMITED_CREATE_URL = '/v1/learningobject/%s/%s/create';
    private const KEYWORDS = '/v1/keywords';
    private const LEARNING_OBJECTS_URL = '/v2/learning_objects/%s';
    private const CUSTOM_FIELDS_URL = '/v2/learning_objects/%s/field_values';
    private const CUSTOM_FIELD_VALUE_URL = '/v2/learning_objects/%s/field_values/%s';

    /**
     * CerpusMetadataServiceAdapter constructor.
     *
     * @param Client $client
     * @param string $prefix Prefix for the entity id
     * @param string $entityType
     * @param string $entityId
     */
    public function __construct(Client $client, string $prefix = '', $entityType = null, $entityId = null)
    {
        $this->client = $client;
        $this->prefix = $prefix;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->updateEntityGuid();
    }

    protected function buildGuid()
    {
        return $this->prefix . $this->entityId;
    }

    private function updateEntityGuid()
    {
        $newId = $this->entityId;
        if (Uuid::isValid($newId) === false) {
            $newId = $this->buildGuid();
        }
        if ($this->entityGuid !== $newId) {
            $this->entityGuid = $newId;
            $this->metadataId = false;
        }
    }

    /**
     * @param string $entityType
     * @return $this
     */
    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;
        $this->updateEntityGuid();

        return $this;
    }

    /**
     * @param string $entityId
     * @return $this
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;
        $this->updateEntityGuid();

        return $this;
    }

    /**
     * Retrieve the metadata for the given type
     *
     * @param string $metaType The type of metadata to retrieve, one of METATYPE_* constants
     * @return mixed  The metadata or null if not found
     * @throws MetadataServiceException
     */
    public function getData($metaType)
    {
        try {
            $id = $this->getUuid(false);
            if ($id !== false) {
                $request = sprintf(self::LEARNINGOBJECT_URL, $id, $metaType);
                $response = $this->client->get($request);
                $result = json_decode($response->getBody()->getContents());

                if ($response->getStatusCode() === 200 && $result !== null) {
                    return $result;
                }

                return null;
            }
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            Log::error('[' . __METHOD__ . '] ' . $e->getMessage() . ' (' . $e->getCode() . ')');
            throw new MetadataServiceException('Service error', 1005, $e);
        }

        throw new MetadataServiceException('LearningObject not found', 1001);
    }

    /**
     * @return array
     * @throws MetadataServiceException
     */
    public function getAllMetaData()
    {
        $start = microtime(true);
        $metaResults = [];
        try {
            $id = $this->getUuid(false);
            if ($id !== false) {
                $metaDataPromises = array_map(function ($key) use ($id) {
                    return $this->client->getAsync(sprintf(self::LEARNINGOBJECT_URL, $id, $key));
                }, $this->propertyMapping);
                $metaResponse = Promise\settle($metaDataPromises)->wait();

                foreach ($this->propertyMapping as $key => $response) {
                    /** @var Response $theResponse */
                    if (array_key_exists($key, $metaResponse) && array_key_exists('value', $metaResponse[$key])) {
                        $theResponse = $metaResponse[$key]['value'];
                        $theContent = json_decode($theResponse->getBody()->getContents());
                        $metaResults[$key] = $theContent;
                    } else {
                        Log::debug("Response from metadata api is missing [$key]['value']");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('[' . __METHOD__ . '] ' . $e->getMessage() . ' (' . $e->getCode() . ')');
            throw new MetadataServiceException('Service error', 1005, $e);
        }

        Log::debug(__METHOD__ . ' time: ' . (microtime(true) - $start));

        return $metaResults;
    }

    /**
     * Save new metadata
     *
     * @param string $metaType Type of metadata to save, one of METATYPE_* constants
     * @param string $data The data to store
     * @return mixed
     * @throws MetadataServiceException
     */
    public function createData($metaType, $data)
    {
        try {
            $id = $this->getUuid(true);
            if ($id !== false) {
                $propertyName = $this->getPropertyName($metaType);
                if ($propertyName === null) {
                    throw new MetadataServiceException('Unknown metaType ' . $metaType, 1004);
                }
                $url = sprintf(self::LEARNINGOBJECT_URL, $id, $metaType);
                if ($metaType !== self::METATYPE_ESTIMATED_DURATION &&
                    $metaType !== self::METATYPE_DIFFICULTY &&
                    $metaType !== self::METATYPE_PUBLIC_STATUS
                ) {
                    $url = sprintf(self::LEARNINGOBJECT_LIMITED_CREATE_URL, $id, $metaType);
                }
                $response = $this->client->post(
                    $url,
                    [
                        'json' => [
                            $propertyName => $data
                        ]
                    ]
                );

                $result = json_decode($response->getBody()->getContents());
                if ($response->getStatusCode() === 200 && $result !== null) {
                    return $result;
                }

                return false;
            }
        } catch (\Exception $e) {
            throw new MetadataServiceException('Failed setting metadata of type ' . $metaType, 1002, $e);
        }

        throw new MetadataServiceException('Failed creating LearningObject', 1003);
    }

    /**
     * @param array $dataArray
     * @return array
     * @throws MetadataServiceException
     */
    public function createDataFromArray(array $dataArray)
    {
        $result = [];
        try {
            $id = $this->getUuid(true);
            if ($id !== false) {
                foreach ($dataArray as $metaType => $data) {
                    $propName = $this->getPropertyName($metaType);
                    if (is_array($data) && count($data) > 0) {
                        foreach ($data as $d) {
                            if (is_object($d) && property_exists($d, $propName)) {
                                $result[$metaType][] = $this->createData($metaType, $d->$propName);
                            }
                        }
                    } else if (is_object($data) && property_exists($data, $propName)) {
                        $result[$metaType] = $this->createData($metaType, $data->$propName);
                    }
                }
            }
        } catch (\Exception $e) {
            throw new MetadataServiceException(
                'Failed creating data from array',
                1009,
                $e
            );
        }

        return $result;
    }

    /**
     * @param string $metaType one of METATYPE_* constants
     * @param string $metaId
     * @return bool
     * @throws MetadataServiceException
     */
    public function deleteData($metaType, $metaId)
    {
        try {
            $id = $this->getUuid(false);
            if ($id !== false) {
                $result = $this->client->delete(sprintf(self::LEARNINGOBJECT_EDIT_URL, $id, $metaType, $metaId));

                return ($result->getStatusCode() === 200);
            }
        } catch (\Exception $e) {
            throw new MetadataServiceException(
                'Failed deleting metadata. Type: ' . $metaType . ', Id: ' . $metaId,
                1006,
                $e
            );
        }

        return false;
    }

    /**
     * @param string $metaType
     * @param string $metaId
     * @param mixed $data
     * @return bool|mixed
     * @throws MetadataServiceException
     */
    public function updateData($metaType, $metaId, $data)
    {
        try {
            $id = $this->getUuid(false);
            if ($id !== false) {
                $propertyName = $this->getPropertyName($metaType);
                if ($propertyName === null) {
                    throw new MetadataServiceException('Unknown metaType ' . $metaType, 1008);
                }
                $response = $this->client->put(
                    sprintf(self::LEARNINGOBJECT_EDIT_URL, $id, $metaType, $metaId),
                    array(
                        'json' => [
                            $propertyName => $data
                        ]
                    )
                );
                $result = json_decode($response->getBody()->getContents());
                if ($response->getStatusCode() === 200 && $result !== null) {
                    return $result;
                }

                return false;
            }
        } catch (\Exception $e) {
            throw new MetadataServiceException(
                'Failed updating metadata. Type: "' . $metaType . '", Id: "' . $metaId . '"',
                1007,
                $e
            );
        }

        return false;
    }

    /**
     * @param string $goalId
     * @return mixed
     * @throws MetadataServiceException
     */
    public function addGoal($goalId)
    {
        return $this->createData(self::METATYPE_LEARNING_GOALS, $goalId);
    }

    /**
     * Get the UUID for the entity from the Metadata service
     *
     * @param bool $create If the entity does not have an UUID assigned, create it
     * @return bool|string False on failure, UUID on success
     */
    public function getUuid($create = false)
    {
        if ($this->metadataId !== false) {
            return $this->metadataId;
        }

        try {
            $response = $this->client->get(sprintf(self::ENTITY_GUID_URL, $this->entityGuid));
            $result = json_decode($response->getBody()->getContents());
            if ($result !== null && property_exists($result, 'id')) {
                $this->metadataId = $result->id;

                return $this->metadataId;
            }
        } catch (\Exception $e) {
            if ($e->getCode() !== 404) {
                Log::error('[' . __METHOD__ . '] ' . $e->getMessage() . ' (' . $e->getCode() . ')');
            } else if ($create) {
                return $this->createId();
            }
        }

        return false;
    }

    /**
     * Request Metadata service to create an UUID for this entity
     *
     * @return bool|string False on failure, UUID on create
     */
    private function createId()
    {
        try {
            $response = $this->client->post(
                self::CREATE_LEARNINGOBJECT_URL,
                array(
                    'json' => [
                        'entityType' => $this->entityType,
                        'entityGuid' => $this->entityGuid,
                    ]
                )
            );
            $result = json_decode($response->getBody()->getContents());
            if ($result !== null && property_exists($result, 'id')) {
                $this->metadataId = $result->id;

                return $this->metadataId;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return false;
    }

    /**
     * The the property name for the metatype
     *
     * @param string $metaType Type to get the property name for, one on METATYPE_* constants
     * @return string|null  Property name or null if it does not exist
     */
    private function getPropertyName($metaType): ?string
    {
        if (array_key_exists($metaType, $this->propertyMapping)) {
            return $this->propertyMapping[$metaType];
        }

        return null;
    }

    /**
     * @return string[]
     * @throws MetadataServiceException
     */
    public function getKeywords(): array
    {
        return array_column($this->getData(self::METATYPE_KEYWORDS), 'keyword');
    }

    public function searchForKeywords(string $searchText)
    {
        try {
            $response = $this->client->get(self::KEYWORDS, [
                'query' => [
                    'prefix' => $searchText
                ]
            ]);

            $result = $response->getBody()->getContents();
            return json_decode($result);
        } catch (ClientException $exception) {
            throw new MetadataServiceException('Could not load keywords', 1010, $exception);
        }
    }

    public function getCustomFieldDefinition(string $fieldName): ?string
    {
        if (array_key_exists($fieldName, $this->customFieldDefinitions)) {
            return $this->customFieldDefinitions[$fieldName];
        }

        try {
            $response = $this->client->get('/v2/field_definitions/' . rawurlencode($fieldName));
            $result = json_decode($response->getBody()->getContents(), false);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Unable to decode field definition");
            }
            $this->customFieldDefinitions[$fieldName] = $result;

            return $this->customFieldDefinitions[$fieldName];
        } catch (\Throwable $t) {
            Log::error(__METHOD__ . ': ' . $t->getMessage());
        }
        return null;
    }

    public function addCustomFieldDefinition(string $fieldName, string $dataType, bool $isCollection = false, bool $requiresUniqueValues = false)
    {
        try {
            $response = $this->client->post('/v2/field_definitions', [
                'json' => [
                    'name' => $fieldName,
                    'dataType' => $dataType,
                    'isCollection' => $isCollection,
                    'requiresUniqueValues' => $requiresUniqueValues
                ]
            ]);
            return json_decode($response->getBody()->getContents(), false);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return null;
        }
    }

    public function getCustomFieldValues(string $fieldName): ?array
    {
        $response = $this->client->get(sprintf(self::CUSTOM_FIELDS_URL, rawurlencode($this->entityGuid)), [
            'query' => [
                'fieldName' => $fieldName,
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (!is_array($result)) {
            Log::error(sprintf('[%s] %s', __METHOD__, 'Data format error'));
            return null;
        }

        return array_column($result, 'value');
    }

    public function setCustomFieldValue(string $fieldName, $value, bool $deduplicateFieldValues = false)
    {
        if (!$fieldDefinition = $this->getCustomFieldDefinition($fieldName)) {
            throw new MetadataServiceException("Field '$fieldName' is not defined in the Metadata service. Please define it using the 'addCustomFieldDefinition' method before attempting to set a value.");
        }

        if ($fieldDefinition->isCollection) {
            return $this->addCustomFieldCollectionValues($fieldName, $value, $deduplicateFieldValues);
        } else {
            return $this->setCustomFieldPlainValue($fieldName, $value);
        }
    }

    protected function setCustomFieldPlainValue(string $fieldName, $value)
    {
        try {
            $url = sprintf(self::CUSTOM_FIELD_VALUE_URL, rawurlencode($this->entityGuid), rawurlencode($fieldName));
            $response = $this->client->put($url, ['json' => ['value' => $value]]);

            return json_decode($response->getBody()->getContents(), false);
        } catch (\Throwable $t) {
            Log::error(__METHOD__ . ': (' . $t->getCode() . ') ' . $t->getMessage());
            return null;
        }
    }

    public function setCustomFieldValues(string $fieldName, $value, bool $deduplicateFieldValues = false)
    {
        $fieldDefinition = $this->getCustomFieldDefinition($fieldName);

        if (!$fieldDefinition) {
            throw new MetadataServiceException("Field '$fieldName' is not defined in the Metadata service. Please define it using the 'addCustomFieldDefinition' method before attempting to set a value.");
        }

        if ($fieldDefinition->isCollection) {
            return $this->addCustomFieldCollectionValues($fieldName, $value, $deduplicateFieldValues);
        } else {
            return $this->setCustomFieldPlainValue($fieldName, $value);
        }
    }

    public function addCustomFieldCollectionValues(string $fieldName, $values = null, bool $deduplicateFieldValues = false)
    {
        if (!$fieldName || $values === null) {
            return;
        }

        if (\is_string($values)) {
            $values = [$values];
        }

        $values = collect($values);

        try {
            // Default is to allow all values, even if they're duplicates
            $existingCollectionFieldValues = collect([]);
            if ($deduplicateFieldValues) {
                // Get the list of existing values
                $existingCollectionFieldValues = collect($this->getCustomFieldValues($fieldName));
                // Remove duplicates from the supplied values as well
                $values = $values->unique();
            }

            return $values->filter(function ($v) use ($existingCollectionFieldValues) {
                return !$existingCollectionFieldValues->contains($v);
            })
                ->map(function ($v) use ($fieldName) {
                    return $this->addCustomFieldCollectionValue($fieldName, $v);
                });

        } catch (\Throwable $t) {
            Log::error(__METHOD__ . ': (' . $t->getCode() . ')' . $t->getMessage());
            return null;
        }

        $url = sprintf(self::CUSTOM_FIELDS_URL, rawurlencode($this->entityGuid));
        $response = $this->client->post($url, [
            'json' => [
                'name' => $fieldName,
                'value' => $value,
            ],
        ]);

        return guzzle_json_decode($response->getBody()->getContents(), false);
    }

    /**
     * @throws MetadataServiceException
     */
    public function fetchAllCustomFields(): array
    {
        $fields = [];

        try {
            $url = sprintf(self::CUSTOM_FIELDS_URL, rawurlencode($this->entityGuid));
            $response = $this->client->get($url);
            $fields = json_decode($response->getBody()->getContents());
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new MetadataServiceException('Error decoding response when getting all custom field values.');
            }
        } catch (ClientException $e) {
            // Learning object not found is OK. Everything else is not OK.
            if ($e->getCode() !== 404) {
                throw new MetadataServiceException('HTTP error', $e->getCode(), $e);
            }
        }

        return $fields;
    }

    public function getLearningObject(bool $create = false)
    {
        $learningObject = null;

        try {
            $path = sprintf(self::LEARNING_OBJECTS_URL, $this->entityGuid);
            $response = $this->client->get($path);
            $object = json_decode($response->getBody()->getContents(), false);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new MetadataServiceException("Error decoding learning object.");
            }
            $learningObject = $object;
        } catch (ClientException $e) {
            // Learning object not found is OK. Everything else is not OK.
            if ($e->getCode() !== 404) {
                throw $e;
            }
            if ($create) {
                $this->setEntityType(self::ENTITYTYPE_RESOURCE);
                if ($id = $this->createId()) {
                    $learningObject = $this->getLearningObject();
                }
            }
        }

        return $learningObject;
    }
}
