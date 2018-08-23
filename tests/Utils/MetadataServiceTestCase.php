<?php

namespace Cerpus\MetadataServiceClientTests\Utils;

use Cerpus\MetadataServiceClientTests\Utils\Traits\WithFaker;
use PHPUnit\Framework\TestCase;

class MetadataServiceTestCase extends TestCase
{
    public $testDirectory;

    protected function setUp()
    {
        $this->testDirectory = dirname(__FILE__, 2);

        parent::setUp();
        $this->setUpTraits();
    }

    public function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[WithFaker::class])) {
            $this->setUpFaker();
        }
    }
}