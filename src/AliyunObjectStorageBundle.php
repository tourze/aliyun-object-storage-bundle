<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AliyunObjectStorageBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    /**
     * @return array<string, bool>
     */
    public static function getBundleDependencies(): array
    {
        return ['all' => true];
    }
}
