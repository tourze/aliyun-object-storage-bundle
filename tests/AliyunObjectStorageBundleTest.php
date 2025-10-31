<?php

namespace Tourze\AliyunObjectStorageBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AliyunObjectStorageBundle\AliyunObjectStorageBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(AliyunObjectStorageBundle::class)]
#[RunTestsInSeparateProcesses]
class AliyunObjectStorageBundleTest extends AbstractBundleTestCase
{
}
