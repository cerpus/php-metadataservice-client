<?php

namespace Cerpus\MetadataServiceClient\Adapters;

use GuzzleHttp\Psr7\Response;
use Log;
use GuzzleHttp\Promise;
use Cerpus\MetadataServiceClient\Contracts\MetadataServiceContract;
use Cerpus\MetadataServiceClient\Exceptions\MetadataServiceException;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

/**
 * Class MetadataServiceAdapter
 * @package Cerpus\MetadataServiceClient\Adapters
 */
class CerpusMetadataServiceAdapter implements MetadataServiceContract
{
    /** @var Client */
    private $client;
    private $entityType;
    private $entityId;
    private $entityGuid = '';
    private $prefix = '';
    private $metadataId = false;

    // metaType => propertyName
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

    const METATYPE_EDUCATIONAL_STANDARD = 'educational_standards';
    const METATYPE_EDUCATIONAL_USES = 'educational_uses';
    const METATYPE_KEYWORDS = 'keywords';
    const METATYPE_LANGUAGES = 'languages';
    const METATYPE_LEARNING_GOALS = 'learning_goals';
    const METATYPE_MATERIAL_TYPES = 'material_types';
    const METATYPE_PRIMARY_USERS = 'primary_users';
    const METATYPE_SUBJECTS = 'subjects';
    const METATYPE_TARGET_AUDIENCE = 'target_audiences';
    const METATYPE_EDUCATIONAL_LEVEL = 'levels';
    const METATYPE_ESTIMATED_DURATION = 'estimated_duration';
    const METATYPE_DIFFICULTY = 'difficulty';
    const METATYPE_PUBLIC_STATUS = 'public';

    const ENTITYTYPE_LEARNINGOBJECT = 'learningobject';
    const ENTITYTYPE_COURSE = 'course';
    const ENTITYTYPE_MODULE = 'module';
    const ENTITYTYPE_ACTIVITY = 'activity';
    const ENTITYTYPE_RESOURCE = 'resource';
    const ENTITYTYPE_USER = 'user';
    const ENTITYTYPE_LEARNINGGOAL = 'learninggoal';

    const LEARNINGOBJECT_URL = '/v1/learningobject/%s/%s';
    const ENTITY_GUID_URL = '/v1/learningobject/entity_guid/%s';
    const CREATE_LEARNINGOBJECT_URL = '/v1/learningobject/create';
    const LEARNINGOBJECT_EDIT_URL = '/v1/learningobject/%s/%s/%s';

    /**
     * CerpusMetadataServiceAdapter constructor.
     *
     * @param Client $client
     * @param string $prefix Prefix for the entity id
     */
    public function __construct(Client $client, $prefix)
    {
        $this->client = $client;
        $this->prefix = $prefix;
        $this->updateEntityGuid();
    }

    protected function updateEntityGuid()
    {
        $newId = $this->entityId;
        if (Uuid::isValid($newId) === false) {
            $newId = $this->prefix . $this->entityId;
        }
        if ($this->entityGuid !== $newId) {
            $this->entityGuid = $newId;
            $this->metadataId = false;
        }
    }

    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;
        $this->updateEntityGuid();

        return $this;
    }

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
                $urlPostFix = '';
                if ($metaType !== self::METATYPE_ESTIMATED_DURATION &&
                    $metaType !== self::METATYPE_DIFFICULTY &&
                    $metaType !== self::METATYPE_PUBLIC_STATUS
                ) {
                    $urlPostFix = '/create';
                }
                $response = $this->client->post(
                    sprintf(self::LEARNINGOBJECT_URL, $id, $metaType) . $urlPostFix,
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
            throw new MetadataServiceException('Failed creating LearningObject', 1003);
        } catch (MetadataServiceException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new MetadataServiceException('Failed setting metadata of type ' . $metaType, 1002, $e);
        }
    }

    public function createDataFromArray(Array $dataArray)
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

    public function fetchLearningGoal($id = '')
    {
        if (empty($id)) {
            return [];
        }

        $goal = new \stdClass();
        $goal->id = $id;
        $goal->title = "Goal #$i title";

        return $goal;
    }

    public function fetchLearningGoals($id = [])
    {
        if (empty($id)) {
            return [];
        }
        $allGoals = [];
        foreach ($id as $goalId) {
            $goal = new \stdClass();
            $goal->id = $goalId;
            $goal->title = "Goal #$goalId title";
            $allGoals[] = $goal;
        }

        return $allGoals;
    }

    public function addGoal($goalId)
    {
        $id = $this->getUuid(true);
        if ($id !== false) {
            $endPoint = '/v1/learningobject/' . $id . '/learning_goals/create';
            $result = $this->client->request('POST', $endPoint, [
                'json' => [
                    'learningGoalId' => $goalId,
                ]
            ]);
            return json_decode($result->getBody()->getContents());
        }

        return false;
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
    private function getPropertyName($metaType)
    {
        if (array_key_exists($metaType, $this->propertyMapping)) {
            return $this->propertyMapping[$metaType];
        }

        return null;
    }

}