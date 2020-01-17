<?php

namespace Cerpus\MetadataServiceClient\Adapters;

use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use Cerpus\MetadataServiceClient\Exceptions\MalformedJsonException;
use Cerpus\MetadataServiceClient\Exceptions\MetadataServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use InvalidArgumentException;
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
     * @var string|null
     */
    private $metadataId;

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
    private const FIELD_DEFINITION_URL = '/v2/field_definitions';
    private const FIELD_DEFINITION_FIELDNAME_URL = '/v2/field_definitions/%s';

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
            $this->metadataId = null;
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
            $response = $this->client->get(sprintf(self::LEARNINGOBJECT_URL, $id, $metaType));

            return guzzle_json_decode($response->getBody()->getContents(), false);
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * @return array
     * @throws MetadataServiceException
     */
    public function getAllMetaData()
    {
        $metaResults = $metaDataPromises = [];

        try {
            $id = $this->getUuid(false);
            foreach ($this->propertyMapping as $key => $value) {
                $metaDataPromises[$key] = $this->client->getAsync(sprintf(self::LEARNINGOBJECT_URL, $id, $key));
            }

            $metaResponse = Promise\settle($metaDataPromises)->wait();

            foreach ($this->propertyMapping as $key => $response) {
                if (isset($metaResponse[$key]['value'])) {
                    /** @var Response $theResponse */
                    $theResponse = $metaResponse[$key]['value'];
                    $theContent = guzzle_json_decode($theResponse->getBody()->getContents(), false);
                    $metaResults[$key] = $theContent;
                } else {
                    Log::warning("Response from metadata api is missing [$key]['value']");
                }
            }

            return $metaResults;
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * Save new metadata
     *
     * @param string $metaType Type of metadata to save, one of METATYPE_* constants
     * @param string|mixed $data The data to store
     * @return mixed
     * @throws MetadataServiceException
     */
    public function createData($metaType, $data)
    {
        try {
            $id = $this->getUuid(true);
            $propertyName = $this->getPropertyName($metaType);
            if ($propertyName === null) {
                throw new InvalidArgumentException('Unknown metaType ' . $metaType);
            }
            $url = sprintf(self::LEARNINGOBJECT_URL, $id, $metaType);
            if ($metaType !== self::METATYPE_ESTIMATED_DURATION &&
                $metaType !== self::METATYPE_DIFFICULTY &&
                $metaType !== self::METATYPE_PUBLIC_STATUS
            ) {
                $url = sprintf(self::LEARNINGOBJECT_LIMITED_CREATE_URL, $id, $metaType);
            }
            $response = $this->client->post($url, [
                'json' => [
                    $propertyName => $data
                ]
            ]);

            $result = guzzle_json_decode($response->getBody()->getContents(), false);

            if ($result === null) {
                throw new MalformedJsonException('result was null');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * @param array $dataArray
     * @return array
     * @throws MetadataServiceException
     */
    public function createDataFromArray(array $dataArray): array
    {
        $result = [];
        $this->getUuid(true);
        foreach ($dataArray as $metaType => $data) {
            $propName = $this->getPropertyName($metaType);
            if (is_array($data) && count($data) > 0) {
                foreach ($data as $d) {
                    if (is_object($d) && property_exists($d, $propName)) {
                        $result[$metaType][] = $this->createData($metaType, $d->$propName);
                    }
                }
            } elseif (is_object($data) && property_exists($data, $propName)) {
                $result[$metaType] = $this->createData($metaType, $data->$propName);
            }
        }

        return $result;
    }

    /**
     * @param string $metaType one of METATYPE_* constants
     * @param string $metaId
     * @return bool if data was deleted
     * @throws MetadataServiceException if data could not be deleted
     */
    public function deleteData($metaType, $metaId)
    {
        try {
            $id = $this->getUuid(false);
            $this->client->delete(sprintf(self::LEARNINGOBJECT_EDIT_URL, $id, $metaType, $metaId));

            return true;
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                return false;
            }

            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * @param string $metaType
     * @param string $metaId
     * @param mixed $data
     * @return mixed
     * @throws InvalidArgumentException if $metaType is invalid
     * @throws MetadataServiceException if data could not be updated
     */
    public function updateData($metaType, $metaId, $data)
    {
        try {
            $id = $this->getUuid(false);
            $propertyName = $this->getPropertyName($metaType);
            if ($propertyName === null) {
                throw new InvalidArgumentException("Unknown metaType '$metaType'");
            }
            $response = $this->client->put(sprintf(self::LEARNINGOBJECT_EDIT_URL, $id, $metaType, $metaId), [
                'json' => [
                    $propertyName => $data
                ]
            ]);

            $result = guzzle_json_decode($response->getBody()->getContents(), false);

            if (empty($result)) {
                throw new MalformedJsonException('result was empty');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
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
     * @return string
     * @throws MetadataServiceException if the UUID could not be retrieved or created
     */
    public function getUuid($create = false)
    {
        if (!$this->metadataId) {
            try {
                $response = $this->client->get(sprintf(self::ENTITY_GUID_URL, $this->entityGuid));
                $result = guzzle_json_decode($response->getBody()->getContents(), false);

                if (!is_string($result->id ?? null)) {
                    throw new MalformedJsonException("missing 'id' field");
                }

                $this->metadataId = $result->id;
            } catch (GuzzleException $e) {
                if ($create && $e->getCode() === 404) {
                    $this->metadataId = $this->createId();
                } else {
                    throw MetadataServiceException::fromGuzzleException($e);
                }
            }
        }

        return $this->metadataId;
    }

    /**
     * Request Metadata service to create an UUID for this entity
     *
     * @throws MetadataServiceException if UUID could not be created
     */
    private function createId(): string
    {
        try {
            $response = $this->client->post(self::CREATE_LEARNINGOBJECT_URL, [
                'json' => [
                    'entityType' => $this->entityType,
                    'entityGuid' => $this->entityGuid,
                ]
            ]);

            $result = guzzle_json_decode($response->getBody()->getContents(), false);

            if (!is_string($result->id ?? null)) {
                throw new MalformedJsonException("'id' is missing or not a string");
            }

            return $result->id;
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * The the property name for the metatype
     *
     * @param string $metaType Type to get the property name for, one on METATYPE_* constants
     * @return string|null  Property name or null if it does not exist
     */
    private function getPropertyName($metaType): ?string
    {
        return $this->propertyMapping[$metaType] ?? null;
    }

    /**
     * @return string[]
     * @throws MetadataServiceException
     */
    public function getKeywords(): array
    {
        return array_column($this->getData(self::METATYPE_KEYWORDS) ?: [], 'keyword');
    }

    /**
     * @param string $searchText
     * @return mixed
     * @throws MetadataServiceException
     */
    public function searchForKeywords(string $searchText)
    {
        try {
            $response = $this->client->get(self::KEYWORDS, [
                'query' => [
                    'prefix' => $searchText
                ]
            ]);

            $result = $response->getBody()->getContents();

            return guzzle_json_decode($result, false);
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    public function getCustomFieldDefinition(string $fieldName)
    {
        if (!isset($this->customFieldDefinitions[$fieldName])) {
            try {
                $response = $this->client->get(sprintf(self::FIELD_DEFINITION_FIELDNAME_URL, rawurlencode($fieldName)));
                $result = guzzle_json_decode($response->getBody()->getContents(), false);

                $this->customFieldDefinitions[$fieldName] = $result;
            } catch (GuzzleException $e) {
                if ($e->getCode() === 404) {
                    return null;
                }

                throw MetadataServiceException::fromGuzzleException($e);
            }
        }

        return $this->customFieldDefinitions[$fieldName];
    }

    public function addCustomFieldDefinition(string $fieldName, string $dataType, bool $isCollection = false, bool $requiresUniqueValues = false)
    {
        try {
            $response = $this->client->post(self::FIELD_DEFINITION_URL, [
                'json' => [
                    'name' => $fieldName,
                    'dataType' => $dataType,
                    'isCollection' => $isCollection,
                    'requiresUniqueValues' => $requiresUniqueValues
                ]
            ]);

            return guzzle_json_decode($response->getBody()->getContents(), false);
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    public function getCustomFieldValues(string $fieldName): ?array
    {
        try {
            $response = $this->client->get(sprintf(self::CUSTOM_FIELDS_URL, rawurlencode($this->entityGuid)), [
                'query' => [
                    'fieldName' => $fieldName,
                ],
            ]);

            $result = guzzle_json_decode($response->getBody()->getContents(), true);

            if (!is_array($result)) {
                throw new MalformedJsonException('result was expected to be an array');
            }

            return array_column($result, 'value');
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * @param string $fieldName
     * @param $value
     * @param bool $deduplicateFieldValues
     * @return Collection|mixed|void|null
     * @throws MetadataServiceException
     */
    public function setCustomFieldValue(string $fieldName, $value, bool $deduplicateFieldValues = false)
    {
        if (!$fieldDefinition = $this->getCustomFieldDefinition($fieldName)) {
            throw new InvalidArgumentException("Field '$fieldName' is not defined in the Metadata service. Please define it using the 'addCustomFieldDefinition' method before attempting to set a value.");
        }

        if ($fieldDefinition->isCollection) {
            return $this->addCustomFieldCollectionValues($fieldName, $value, $deduplicateFieldValues);
        } else {
            return $this->setCustomFieldPlainValue($fieldName, $value);
        }
    }

    private function setCustomFieldPlainValue(string $fieldName, $value)
    {
        try {
            $url = sprintf(self::CUSTOM_FIELD_VALUE_URL, rawurlencode($this->entityGuid), rawurlencode($fieldName));
            $response = $this->client->put($url, ['json' => ['value' => $value]]);

            return guzzle_json_decode($response->getBody()->getContents(), false);
        } catch (GuzzleException $e) {
            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * @param string $fieldName
     * @param $value
     * @param bool $deduplicateFieldValues
     * @return Collection|mixed|void|null
     * @throws InvalidArgumentException if $fieldName is invalid
     * @throws MetadataServiceException
     */
    public function setCustomFieldValues(string $fieldName, $value, bool $deduplicateFieldValues = false)
    {
        $fieldDefinition = $this->getCustomFieldDefinition($fieldName);

        if (!$fieldDefinition) {
            throw new InvalidArgumentException("Field '$fieldName' is not defined in the Metadata service. Please define it using the 'addCustomFieldDefinition' method before attempting to set a value.");
        }

        if ($fieldDefinition->isCollection) {
            return $this->addCustomFieldCollectionValues($fieldName, $value, $deduplicateFieldValues);
        } else {
            return $this->setCustomFieldPlainValue($fieldName, $value);
        }
    }

    public function addCustomFieldCollectionValues(string $fieldName, $values = null, bool $deduplicateFieldValues = false)
    {
        // TODO: this method doesn't work, fix it or remove it
        if (!$fieldName || $values === null) {
            return;
        }

        if (is_string($values)) {
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

        } catch (Throwable $t) {
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
        try {
            $url = sprintf(self::CUSTOM_FIELDS_URL, rawurlencode($this->entityGuid));
            $response = $this->client->get($url);

            return guzzle_json_decode($response->getBody()->getContents(), false);
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                return [];
            }

            throw MetadataServiceException::fromGuzzleException($e);
        }
    }

    /**
     * @throws MetadataServiceException if learning object could not be retrieved or created
     */
    public function getLearningObject(bool $create = false)
    {
        try {
            $response = $this->client->get(sprintf(self::LEARNING_OBJECTS_URL, $this->entityGuid));
            $learningObject = guzzle_json_decode($response->getBody()->getContents(), false);

            if (!is_object($learningObject)) {
                throw new MalformedJsonException('result was expected to be object');
            }

            return $learningObject;
        } catch (GuzzleException $e) {
            if ($create && $e->getCode() === 404) {
                $this->setEntityType(self::ENTITYTYPE_RESOURCE);
                $this->createId();

                return $this->getLearningObject();
            }

            throw MetadataServiceException::fromGuzzleException($e);
        }
    }
}
