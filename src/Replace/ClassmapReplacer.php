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
                // If it matches any of the namespaces to skip, then do nothing
                $namespacesToSkip = $this->namespacesToSkip ?? [];
                foreach ($namespacesToSkip as $namespaceToSkip) {
                    if (strlen($matches[1]) >= strlen($namespaceToSkip) && substr($matches[1], 0, strlen($namespaceToSkip)) == $namespaceToSkip) {
                        return $matches[0];
                    }
                }
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
