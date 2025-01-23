<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit852b5263783a1252680eba6f6b687bc5
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Genesis\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Genesis\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/Genesis',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit852b5263783a1252680eba6f6b687bc5::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit852b5263783a1252680eba6f6b687bc5::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit852b5263783a1252680eba6f6b687bc5::$classMap;

        }, null, ClassLoader::class);
    }
}