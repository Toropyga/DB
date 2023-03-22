<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7b7ba57d5a380c22de1f556c444a8cb8
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'FYN\\DB\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'FYN\\DB\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7b7ba57d5a380c22de1f556c444a8cb8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7b7ba57d5a380c22de1f556c444a8cb8::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit7b7ba57d5a380c22de1f556c444a8cb8::$classMap;

        }, null, ClassLoader::class);
    }
}
