<?php

namespace CoenJacobs\Mozart\Config;

use Exception;
use JsonMapper;
use stdClass;

trait ReadsConfig
{
    public static function loadFromFile(string $filePath): self
    {
        $fileContents = file_get_contents($filePath);

        if (! $fileContents) {
            throw new Exception('Could not read config from provided file.');
        }

        return self::loadFromString($fileContents);
    }

    /**
     * @param array<mixed> $config
     */
    public static function loadFromArray(array $config): self
    {
        $encoded = json_encode($config);

        if (! $encoded) {
            throw new Exception('Could not read config from provided array.');
        }

        $config = json_decode($encoded, false);

        if (! $config) {
            throw new Exception('Could not read config from provided array.');
        }

        return self::loadFromStdClass($config);
    }

    public static function loadFromStdClass(stdClass $config): self
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $object = $mapper->map($config, self::class);

        if (! $object instanceof self) {
            throw new Exception('Could not read config from provided array.');
        }

        return $object;
    }

    public static function loadFromString(string $config): self
    {
        $config = json_decode($config);

        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $object = $mapper->map($config, self::class);

        if (! $object instanceof self) {
            throw new Exception('Could not read config from provided array.');
        }

        return $object;
    }
}
