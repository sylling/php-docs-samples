<?php
/**
 * Copyright 2016 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Samples\Storage\Tests;

use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Samples\Storage\ObjectAclCommand;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\TestUtils\TestTrait;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for ObjectAclCommand.
 */
class ObjectAclCommandTest extends TestCase
{
    use TestTrait;

    protected $commandTester;
    protected $storage;

    public function setUp()
    {
        $application = require __DIR__ . '/../storage.php';
        $this->commandTester = new CommandTester($application->get('object-acl'));
        $this->storage = new StorageClient();
    }

    public function testObjectAcl()
    {
        $bucketName = $this->requireEnv('GOOGLE_STORAGE_BUCKET');
        $objectName = $this->requireEnv('GOOGLE_STORAGE_OBJECT');

        $this->commandTester->execute(
            [
                'bucket' => $bucketName,
                'object' => $objectName,
            ],
            ['interactive' => false]
        );

        $this->expectOutputRegex("/: OWNER/");
    }

    public function testManageObjectAcl()
    {
        $bucketName = $this->requireEnv('GOOGLE_STORAGE_BUCKET');
        $objectName = $this->requireEnv('GOOGLE_STORAGE_OBJECT');

        $bucket = $this->storage->bucket($bucketName);
        $object = $bucket->object($objectName);
        $acl = $object->acl();

        $this->commandTester->execute(
            [
                'bucket' => $bucketName,
                'object' => $objectName,
                '--entity' => 'allAuthenticatedUsers',
                '--create' => true,
            ],
            ['interactive' => false]
        );

        $aclInfo = $acl->get(['entity' => 'allAuthenticatedUsers']);
        $this->assertArrayHasKey('role', $aclInfo);
        $this->assertEquals('READER', $aclInfo['role']);

        $this->commandTester->execute(
            [
                'bucket' => $bucketName,
                'object' => $objectName,
                '--entity' => 'allAuthenticatedUsers',
            ],
            ['interactive' => false]
        );

        $this->commandTester->execute(
            [
                'bucket' => $bucketName,
                'object' => $objectName,
                '--entity' => 'allAuthenticatedUsers',
                '--delete' => true,
            ],
            ['interactive' => false]
        );

        try {
            $acl->get(['entity' => 'allAuthenticatedUsers']);
            $this->fail();
        } catch (NotFoundException $e) {
            $this->assertTrue(true);
        }

        $objectUrl = sprintf('gs://%s/%s', $bucketName, $objectName);
        $outputString = <<<EOF
Added allAuthenticatedUsers (READER) to $objectUrl ACL
allAuthenticatedUsers: READER
Deleted allAuthenticatedUsers from $objectUrl ACL

EOF;
        $this->expectOutputString($outputString);
    }
}
