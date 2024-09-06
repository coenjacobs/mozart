<?php

namespace CoenJacobs\Mozart\Config;

use stdClass;

class Composer
{
    use ReadsConfig, ConfigAccessor;

    public string $name;
    public array $require = [];

    public ?Autoload $autoload = null;
    public ?Extra $extra = null;

    public function setAutoload(stdClass $data)
    {
        $autoload = new Autoload();
        $autoload->setupAutoloaders($data);
        $this->autoload = $autoload;
    }

    public function getExtra(): ?Extra
    {
        return $this->extra;
    }

    public function isValidMozartConfig(): bool
    {
        if (empty($this->getExtra())) {
            return false;
        }

        if (empty($this->getExtra()->getMozart())) {
            return false;
        }

        return $this->getExtra()->getMozart()->isValidMozartConfig();
    }
}
