<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use JsonMapper;
use stdClass;

/**
 * Deserialise JSON configuration into typed config objects (Mozart, Package).
 *
 * Accepts files or raw JSON strings and maps them onto the target class
 * using JsonMapper.
 */
class ConfigLoader
{
    /**
     * Load configuration from a file.
     *
     * @template T of object
     * @param string $filePath   Path to the JSON configuration file.
     * @param class-string<T> $targetClass Fully-qualified class to map onto.
     * @return T
     * @throws ConfigurationException If the file cannot be read.
     */
    public function fromFile(string $filePath, string $targetClass): object
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new ConfigurationException('Could not read config from provided file.');
        }

        $fileContents = file_get_contents($filePath);

        if (! $fileContents) {
            throw new ConfigurationException('Could not read config from provided file.');
        }

        return $this->fromString($fileContents, $targetClass);
    }

    /**
     * Load configuration from a JSON string.
     *
     * @template T of object
     * @param string $json           Raw JSON string.
     * @param class-string<T> $targetClass Fully-qualified class to map onto.
     * @return T
     * @throws ConfigurationException If the JSON cannot be decoded or mapped.
     */
    public function fromString(string $json, string $targetClass): object
    {
        $config = json_decode($json);

        if (! $config instanceof stdClass) {
            throw new ConfigurationException('Could not read config from provided string.');
        }

        return $this->mapToClass($config, $targetClass);
    }

    /**
     * Map a decoded JSON object onto the target class via JsonMapper.
     *
     * @template T of object
     * @param class-string<T> $targetClass
     * @return T
     * @throws ConfigurationException If mapping produces an unexpected type.
     */
    private function mapToClass(stdClass $config, string $targetClass): object
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $object = $mapper->map($config, $targetClass);

        if (! $object instanceof $targetClass) {
            throw new ConfigurationException('Could not map config to ' . $targetClass . '.');
        }

        return $object;
    }
}
