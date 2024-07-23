<?php

namespace CoenJacobs\Mozart\Composer;

class Config
{
    /** @var array<mixed> */
    private $data;

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function loadFromFile(string $filePath): Config
    {
        $fileContents = file_get_contents($filePath);
        $config = ( ! $fileContents ) ? $config = array() : json_decode($fileContents);
        return new self((array)$config);
    }

    /**
     * @param string $key
     * @return bool|mixed
     */
    public function get(string $key)
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
    public function set($key, $data): void
    {
        $this->data[$key] = $data;
    }
}
