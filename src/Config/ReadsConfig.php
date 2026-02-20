<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use JsonMapper;
use stdClass;

trait ReadsConfig
{
    /**
     * Load configuration from a file.
     *
     * @param string $filePath Path to the configuration file
     * @return self
     * @throws ConfigurationException If the file cannot be read
     */
    public function loadFromFile(string $filePath): self
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new ConfigurationException('Could not read config from provided file.');
        }

        $fileContents = file_get_contents($filePath);

        if (! $fileContents) {
            throw new ConfigurationException('Could not read config from provided file.');
        }

        return $this->loadFromString($fileContents);
    }

    /**
     * @param array<mixed> $config
     */
    public function loadFromArray(array $config): self
    {
        $encoded = json_encode($config);

        if (! $encoded) {
            throw new ConfigurationException('Could not read config from provided array.');
        }

        $config = json_decode($encoded, false);

        if (! $config) {
            throw new ConfigurationException('Could not read config from provided array.');
        }

        return $this->loadFromStdClass($config);
    }

    public function loadFromStdClass(stdClass $config): self
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $object = $mapper->map($config, self::class);

        if (! $object instanceof self) {
            throw new ConfigurationException('Could not read config from provided array.');
        }

        return $object;
    }

    public function loadFromString(string $config): self
    {
        $config = json_decode($config);

        if ($config === null) {
            throw new ConfigurationException('Could not read config from provided array.');
        }

        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $object = $mapper->map($config, self::class);

        if (! $object instanceof self) {
            throw new ConfigurationException('Could not read config from provided array.');
        }

        return $object;
    }
}
