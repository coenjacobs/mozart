<?php

namespace CoenJacobs\Mozart\Replace;

interface Replacer
{
    public function setAutoloader($autoloader);
    public function replace($contents);
}
