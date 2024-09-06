<?php

namespace CoenJacobs\Mozart\Config;

use ArrayObject;
use stdClass;

/**
 * @extends ArrayObject<string,mixed>
 */
class OverrideAutoload extends ArrayObject
{
    public function __construct(stdClass $objects)
    {
        $objects = (array) $objects;

        $storage = [];

        foreach ($objects as $key => $object) {
            $storage[$key] = $object;
        }

        parent::__construct($storage);
    }

    public function getByKey(string $key): mixed
    {
        if (! isset($this[$key])) {
            return null;
        }

        return $this[$key];
    }
}
