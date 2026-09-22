<?php

declare(strict_types=1);

namespace think\addons;

/**
 * 插件框架版本标记
 *
 * 供运行时能力/兼容性检测使用：读取的是代码自身的版本，不依赖 composer
 * 元数据，手工同步 vendor 的部署方式下同样准确（composer 元数据可能滞后）。
 * 发版时须与本包 tag 同步更新 VERSION 常量。
 */
class Version
{
    /**
     * 当前插件框架版本（与发布 tag 保持一致）
     */
    const VERSION = '1.2.0';
}
