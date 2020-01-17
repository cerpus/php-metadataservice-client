<?php

namespace {
    class Log
    {
        public static function warning($message)
        {
        }
    }
}


namespace Cerpus\MetadataServiceClientTests\Adapters {

    use Cerpus\MetadataServiceClient\Adapters\CerpusMetadataServiceAdapter;
    use Cerpus\MetadataServiceClient\Exceptions\HttpException;
    use Cerpus\MetadataServiceClient\Exceptions\MalformedJsonException;
    use Cerpus\MetadataServiceClientTests\Utils\MetadataServiceTestCase;
    use Cerpus\MetadataServiceClientTests\Utils\Traits\WithFaker;
    use GuzzleHttp\Client;
    use GuzzleHttp\Handler\MockHandler;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Psr7\Response;
    use InvalidArgumentException;
    use Teapot\StatusCode;

    /**
     * Class MetadataServiceAdapterTest
     * @package Cerpus\MetadataServiceClientTests\Adapters
     */
    class MetadataServiceAdapterTest extends MetadataServiceTestCase
    {
        use WithFaker;

        private $prefix = 'unitTest-';

        private function getClient(array $responses)
        {
            $mock = new MockHandler($responses);
            $handler = HandlerStack::create($mock);
            return new Client(['handler' => $handler]);
        }

        /**
         * @test
         */
        public function getUuid_noMatch_thenFail()
        {
            $this->expectException(MalformedJsonException::class);
            $this->expectExceptionMessage('json_decode failed');

            $client = $this->getClient([
                new Response(StatusCode::OK, [], ''),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->getUuid(false);
        }

        /**
         * @test
         */
        public function getUuid_badRequest_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::BAD_REQUEST);

            $client = $this->getClient([
                new Response(StatusCode::BAD_REQUEST),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->getUuid(false);
        }

        /**
         * @test
         */
        public function getUuid_match_thenSuccess()
        {
            $entityUuid = $this->faker->uuid;

            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)['id' => $entityUuid])),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $this->assertEquals($entityUuid, $metadataservice->getUuid(false));
            $this->assertEquals($entityUuid, $metadataservice->getUuid(false));
        }

        /**
         * @test
         */
        public function getUuid_noMatch_thenCreate()
        {
            $entityUuid = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)['id' => $entityUuid])),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $this->assertEquals($entityUuid, $metadataservice->getUuid(true));
        }

        /**
         * @test
         */
        public function getData_noResourcesId_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::NOT_FOUND);

            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->getData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS);
        }

        /**
         * @test
         */
        public function getData_noMatchWithUnexpectedErrorMessage_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::BAD_REQUEST);

            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)['id' => $this->faker->uuid])),
                new Response(StatusCode::BAD_REQUEST, [], 'Bad request'),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->getData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS);
        }

        /**
         * @test
         */
        public function getData_noMatch_thenFail()
        {
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)['id' => $this->faker->uuid])),
                new Response(StatusCode::NOT_FOUND),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $this->assertNull($metadataservice->getData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS));
        }

        /**
         * @test
         */
        public function getData_unexpectedReturnCode_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::FORBIDDEN);

            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)['id' => $this->faker->uuid])),
                new Response(StatusCode::FORBIDDEN, [], ''),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->getData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS);
        }

        /**
         * @test
         */
        public function getData_valid_thenSuccess()
        {
            $entityUuid = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)['id' => $entityUuid])),
                new Response(StatusCode::OK, [], json_encode([
                    (object)[
                        "id" => "8e60818f-602b-439b-a6b5-98e999f603ad",
                        "keyword" => "geografi"
                    ],
                    (object)[
                        "id" => "99b45447-42d9-4c9f-8b98-039993c9959c",
                        "keyword" => "historie"
                    ]
                ])),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->setEntityId($entityUuid);
            $metadataservice->setEntityType(CerpusMetadataServiceAdapter::ENTITYTYPE_RESOURCE);
            $metadata = $metadataservice->getData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS);
            $this->assertInternalType('array', $metadata);
            $this->assertCount(2, $metadata);
            $this->assertObjectHasAttribute("id", $metadata[0]);
            $this->assertObjectHasAttribute("keyword", $metadata[0]);
            $this->assertInternalType('object', $metadata[1]);
            $this->assertEquals($metadata[1]->id, "99b45447-42d9-4c9f-8b98-039993c9959c");
            $this->assertEquals($metadata[1]->keyword, "historie");
        }

        /**
         * @test
         */
        public function getAllMetadata_noUuid_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::NOT_FOUND);

            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->getAllMetaData();
        }

        /**
         * @test
         */
        public function getAllMetadata_valid_thenSuccess()
        {
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)['id' => $this->faker->uuid])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([(object)['id' => $this->faker->uuid, 'keyword' => $this->faker->word]])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
                new Response(StatusCode::OK, [], json_encode([])),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $allMetadata = $metadataservice->getAllMetaData();
            $this->assertInternalType('array', $allMetadata);
            $this->assertCount(12, $allMetadata);
        }

        /**
         * @test
         */
        public function createData_noUuid_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::NOT_FOUND);

            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::NOT_FOUND),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->createData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, [
                'keyword' => $this->faker->word
            ]);
        }

        /**
         * @test
         */
        public function createData_unknownType_thenFail()
        {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Unknown metaType Unknown_type');

            $entityUuid = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)['id' => $entityUuid])),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->createData("Unknown_type", [
                'keyword' => $this->faker->word
            ]);
        }

        /**
         * @test
         */
        public function createData_valid_thenSuccess()
        {
            $entityUuid = $this->faker->uuid;
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'keyword' => $keyword,
                ])),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $createdData = $metadataservice->createData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $keyword);
            $this->assertInternalType('object', $createdData);
            $this->assertObjectHasAttribute('id', $createdData);
            $this->assertObjectHasAttribute('keyword', $createdData);
        }

        /**
         * @test
         */
        public function createData_emptyResponse_thenFail()
        {
            $this->expectException(MalformedJsonException::class);
            $this->expectExceptionMessage('json_decode failed');

            $entityUuid = $this->faker->uuid;
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND, [], 'Not found'),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::OK, [], null),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->createData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $keyword);
        }

        /**
         * @test
         */
        public function createData_errorResponse_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::BAD_REQUEST);

            $entityUuid = $this->faker->uuid;
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::BAD_REQUEST, [], null),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->createData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $keyword);
        }

        /**
         * @test
         */
        public function createDataFromArray_noUuid_thenFail()
        {
            $this->expectException(MalformedJsonException::class);
            $this->expectExceptionMessage('json_decode failed');

            $client = $this->getClient([
                new Response(StatusCode::OK, [], ''),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $result = $metadataservice->createDataFromArray([]);
            $this->assertInternalType('array', $result);
            $this->assertCount(0, $result);
        }

        /**
         * @test
         */
        public function createDataFromArray_validArray_thenSuccess()
        {
            $entityUuid = $this->faker->uuid;
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'keyword' => $keyword,
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'subjectDeweyCode' => 7,
                ])),
            ]);

            $data = [
                CerpusMetadataServiceAdapter::METATYPE_KEYWORDS => [
                    (object)['keyword' => $keyword],
                ],
                CerpusMetadataServiceAdapter::METATYPE_SUBJECTS => [
                    (object)['subjectDeweyCode' => 7]
                ],
            ];
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $createdData = $metadataservice->createDataFromArray($data);
            $this->assertInternalType('array', $createdData);
            $this->assertCount(2, $createdData);
        }

        /**
         * @test
         */
        public function createDataFromArray_validObject_thenSuccess()
        {
            $entityUuid = $this->faker->uuid;
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'keyword' => $keyword,
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'subjectDeweyCode' => 7,
                ])),
            ]);

            $data = [
                CerpusMetadataServiceAdapter::METATYPE_KEYWORDS => (object)['keyword' => $keyword],
                CerpusMetadataServiceAdapter::METATYPE_SUBJECTS => (object)['subjectDeweyCode' => 7],
            ];
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $createdData = $metadataservice->createDataFromArray($data);
            $this->assertInternalType('array', $createdData);
            $this->assertCount(2, $createdData);
        }

        /**
         * @test
         */
        public function createDataFromArray_invalidParameters_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::BAD_REQUEST);

            $entityUuid = $this->faker->uuid;
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::BAD_REQUEST, [], "Invalid structure"),
            ]);

            $data = [
                CerpusMetadataServiceAdapter::METATYPE_KEYWORDS => (object)['keyword' => $keyword],
            ];
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->createDataFromArray($data);
        }

        /**
         * @test
         */
        public function deleteData_noUuid_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::NOT_FOUND);

            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::NOT_FOUND),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->deleteData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $this->faker->uuid);
        }

        /**
         * @test
         * @doesNotPerformAssertions
         */
        public function deleteData_valid_thenSuccess()
        {
            $entityUuid = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::OK),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $this->assertTrue($metadataservice->deleteData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $this->faker->uuid));
        }

        /**
         * @test
         */
        public function deleteData_notFound_thenReturnFalse()
        {
            $entityUuid = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::NOT_FOUND),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $this->assertFalse($metadataservice->deleteData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, 'uuidTest'));
        }

        /**
         * @test
         */
        public function deleteData_invalidResponse_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::FORBIDDEN);

            $entityUuid = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $entityUuid
                ])),
                new Response(StatusCode::FORBIDDEN),
            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->deleteData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, 'uuidTest');
        }

        /**
         * @test
         */
        public function updateData_noUuid_thenFail()
        {
            $id = $this->faker->uuid;
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::NOT_FOUND);

            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::NOT_FOUND),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->updateData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $id, "dummy");
        }

        /**
         * @test
         */
        public function updateData_invalidType_thenFail()
        {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Unknown metaType 'NotValidMetaType'");

            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'keyword' => $keyword,
                ])),

            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->updateData("NotValidMetaType", 'uuidTest', $keyword);
        }

        /**
         * @test
         */
        public function updateData_validData_thenSuccess()
        {
            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'keyword' => $keyword,
                ])),

            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $updatedData = $metadataservice->updateData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $this->faker->uuid, $keyword);
            $this->assertInternalType('object', $updatedData);
            $this->assertObjectHasAttribute('id', $updatedData);
            $this->assertObjectHasAttribute('keyword', $updatedData);
        }

        /**
         * @test
         */
        public function updateData_invalidResponse_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::FORBIDDEN);

            $keyword = $this->faker->word;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid
                ])),
                new Response(StatusCode::FORBIDDEN, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'keyword' => $keyword,
                ])),

            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->updateData(CerpusMetadataServiceAdapter::METATYPE_KEYWORDS, $this->faker->uuid, $keyword);
        }

        /**
         * @test
         */
        public function addGoal_noUuid_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::NOT_FOUND);

            $client = $this->getClient([
                new Response(StatusCode::NOT_FOUND),
                new Response(StatusCode::NOT_FOUND),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->addGoal($this->faker->uuid);
        }

        /**
         * @test
         */
        public function addGoal_valid_thenSuccess()
        {
            $learningGoalId = $this->faker->uuid;
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid
                ])),
                new Response(StatusCode::OK, [], json_encode((object)[
                    'id' => $this->faker->uuid,
                    'learningGoalId' => $learningGoalId,
                ])),

            ]);

            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $createData = $metadataservice->addGoal($learningGoalId);
            $this->assertInternalType('object', $createData);
            $this->assertObjectHasAttribute('id', $createData);
            $this->assertObjectHasAttribute('learningGoalId', $createData);
        }

        /**
         * @test
         */
        public function searchForKeywords_validSearchtext_thenSuccess()
        {
            $client = $this->getClient([
                new Response(StatusCode::OK, [], json_encode([
                    (object)[
                        "id" => "8e60818f-602b-439b-a6b5-98e999f603ad",
                        "keyword" => "mattematikk"
                    ],
                    (object)[
                        "id" => "99b45447-42d9-4c9f-8b98-039993c9959c",
                        "keyword" => "musikk"
                    ]
                ])),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $keywords = $metadataservice->searchForKeywords("m");
            $this->assertInternalType('array', $keywords);
            $this->assertCount(2, $keywords);
        }

        /**
         * @test
         */
        public function searchForKeywords_invalidServerResponse_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::BAD_REQUEST);

            $client = $this->getClient([
                new Response(StatusCode::BAD_REQUEST),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->searchForKeywords("m");
        }

        /**
         * @test
         */
        public function fetchAllCustomFields_invalidServerResponse_thenFail()
        {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(StatusCode::BAD_REQUEST);

            $client = $this->getClient([
                new Response(StatusCode::BAD_REQUEST),
            ]);
            $metadataservice = new CerpusMetadataServiceAdapter($client, $this->prefix);
            $metadataservice->fetchAllCustomFields();
        }
    }
}
