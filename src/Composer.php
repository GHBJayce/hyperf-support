<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Support;

use Composer\Autoload\ClassLoader;
use Hyperf\Collection\Collection;
use RuntimeException;

use function Hyperf\Collection\collect;

class Composer
{
    private static ?Collection $content = null;

    private static ?Collection $json = null;

    private static array $extra = [];

    private static array $scripts = [];

    private static array $versions = [];

    private static ?ClassLoader $classLoader = null;

    /**
     * @throws RuntimeException When `composer.lock` does not exist.
     */
    public static function getLockContent(): Collection
    {
        if (! self::$content) {
            $path = self::discoverLockFile();
            if (! $path) {
                throw new RuntimeException('composer.lock not found.');
            }
            self::$content = collect(json_decode(file_get_contents($path), true));
            $packages = self::$content->offsetGet('packages') ?? [];
            $packagesDev = self::$content->offsetGet('packages-dev') ?? [];
            foreach (array_merge($packages, $packagesDev) as $package) {
                $packageName = '';
                foreach ($package ?? [] as $key => $value) {
                    if ($key === 'name') {
                        $packageName = $value;
                        continue;
                    }
                    switch ($key) {
                        case 'extra':
                            $packageName && self::$extra[$packageName] = $value;
                            break;
                        case 'scripts':
                            $packageName && self::$scripts[$packageName] = $value;
                            break;
                        case 'version':
                            $packageName && self::$versions[$packageName] = $value;
                            break;
                    }
                }
            }
        }
        return self::$content;
    }

    public static function getJsonContent(): Collection
    {
        if (! self::$json) {
            $path = BASE_PATH . '/composer.json';
            if (! is_readable($path)) {
                throw new RuntimeException('composer.json is not readable.');
            }
            self::$json = collect(json_decode(file_get_contents($path), true));
        }
        return self::$json;
    }

    public static function discoverLockFile(): string
    {
        $path = '';
        if (is_readable(BASE_PATH . '/composer.lock')) {
            $path = BASE_PATH . '/composer.lock';
        }
        return $path;
    }

    /**
     * 从composer.lock中收集所有包中extra的指定属性的数据
     * @param string|null $key
     * @return array
     */
    public static function getMergedExtra(string $key = null)
    {
        if (! self::$extra) {
            // 搜集composer.lock数据：composer.lock自身数据、所有包的extra、所有包的version、所有包的scripts
            self::getLockContent();
        }
        if ($key === null) {
            return self::$extra;
        }
        $extra = [];
        /**
         * $project包名称，例如：hyperf/di
         * $config包的extra数据，例如：[
         *  'branch-alias' => [
         *      'dev-master' => '3.0-dev'
         *  ],
         *  'hyperf' => [
         *      'config' => 'Hyperf\\Di\\ConfigProvider'
         *  ]
         * ]
         */
        foreach (self::$extra as $project => $config) {
            foreach ($config ?? [] as $configKey => $item) {
                if ($key === $configKey && $item) {
                    foreach ($item as $k => $v) {
                        if (is_array($v)) {
                            $extra[$k] = array_merge($extra[$k] ?? [], $v);
                        } else {
                            $extra[$k][] = $v;
                        }
                    }
                }
            }
        }
        return $extra;
    }

    public static function getLoader(): ClassLoader
    {
        if (! self::$classLoader) {
            self::$classLoader = self::findLoader();
        }
        return self::$classLoader;
    }

    public static function setLoader(ClassLoader $classLoader): ClassLoader
    {
        self::$classLoader = $classLoader;
        return $classLoader;
    }

    public static function getScripts(): array
    {
        return self::$scripts;
    }

    public static function getVersions(): array
    {
        return self::$versions;
    }

    private static function findLoader(): ClassLoader
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                return $loader[0];
            }
        }

        throw new RuntimeException('Composer loader not found.');
    }
}
