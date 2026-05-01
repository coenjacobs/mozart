<?php

namespace CoenJacobs\Mozart\Config;

class Extra
{
    public ?Mozart $mozart = null;

    public function getMozart(): ?Mozart
    {
        return $this->mozart;
    }

    public function setMozart(?Mozart $mozart): void
    {
        $this->mozart = $mozart;
    }
}
