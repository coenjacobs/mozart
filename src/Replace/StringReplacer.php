<?php

namespace CoenJacobs\Mozart\Replace;

interface StringReplacer
{
    public function replace(string $contents): string;
}
