<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1efbb6eb6f932156b491c0baad6ed471
{
    public static $prefixLengthsPsr4 = array (
        'b' => 
        array (
            'baildate\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'baildate\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1efbb6eb6f932156b491c0baad6ed471::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1efbb6eb6f932156b491c0baad6ed471::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
