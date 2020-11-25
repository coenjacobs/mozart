<?php

namespace CoenJacobs\Mozart\Replace;

class ClassmapReplacer extends BaseReplacer
{
    /** @var array */
	public $replacedClasses = [];

	/** @var array */
	public $classmap = [];

    /** @var string */
    public $classmap_prefix;

    public function replace($contents, $file)
    {
        return preg_replace_callback(
            '/(?:[abstract]*class |interface )([a-zA-Z\_]+)(?:[ \n]*{| extends| implements)/U',
            function ($matches) use ($file) {
                $replace = $this->classmap_prefix . $matches[1];
                $this->saveReplacedClass($matches[1], $replace, $file);
                return str_replace($matches[1], $replace, $matches[0]);
            },
            $contents
        );
    }

    public function saveReplacedClass($classname, $replacedName, $file)
    {
        $this->replacedClasses[ $classname ] = $replacedName;
	    $this->classmap[ $replacedName ] = $file;
    }
}
