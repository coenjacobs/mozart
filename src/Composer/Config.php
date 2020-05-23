<?php

namespace Mozart\Composer;

class Config
{
    /** @var array */
    private $data;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function loadFromFile($filePath)
    {
        $config = json_decode(file_get_contents($filePath));
        return new self($config);
    }

    /**
     * @param string $key
     * @return bool|mixed
     */
    public function get($key)
    {
        if (isset($this->data[ $key ])) {
            return $this->data[ $key ];
        }

        return false;
    }

    /**
     * @param string $key
     * @param mixed $data
     */
    public function set($key, $data)
    {
        $this->data[$key] = $data;
    }
}
