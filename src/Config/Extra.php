<?php

namespace CoenJacobs\Mozart\Config;

use stdClass;

class Extra
{
    public ?Mozart $mozart = null;

    public function getMozart()
    {
        return $this->mozart;
    }
}
