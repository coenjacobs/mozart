<?php

namespace CoenJacobs\Mozart\Replace;

interface Replacer
{
    public function setAutoloader($autoloader);
    public function setNamespacesToSkip(array $namespacesToSkip);
    public function replace($contents);
}
