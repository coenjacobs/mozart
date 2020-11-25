<?php

namespace CoenJacobs\Mozart\Replace;

class ClassmapReplacer extends BaseReplacer
{
    /** @var array */
    public $replacedClasses = [];

    /** @var string */
    public $classmap_prefix;

    public function replace($contents)
    {
        return preg_replace_callback(
            '/(?:[abstract]*class |interface )([a-zA-Z\_]+)(?:[ \n]*{| extends| implements)/U',
            function ($matches) {
                $replace = $this->classmap_prefix . $matches[1];
                $this->saveReplacedClass($matches[1], $replace);
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );
    }

    public function saveReplacedClass($classname, $replacedName)
    {
        $this->replacedClasses[ $classname ] = $replacedName;
    }
}
