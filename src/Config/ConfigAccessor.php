<?php

namespace CoenJacobs\Mozart\Config;

trait ConfigAccessor
{
    public function get(string $key): mixed
    {
        if (! isset($this->$key)) {
            throw new \Exception('Can\'t get setting: '. $key .' not set');
        }

        return $this->$key;
    }

    public function set(string $key, mixed $value):  void
    {
        if (! property_exists($this, $key)) {
            throw new \Exception('Can\'t set setting: '. $key . ' not set');
        }

        $this->$key = $value;
    }
}
