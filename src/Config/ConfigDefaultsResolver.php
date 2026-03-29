<?php

namespace CoenJacobs\Mozart\Config;

use CoenJacobs\Mozart\Exceptions\ConfigurationException;

/**
 * Fill empty Mozart configuration properties with sensible defaults.
 *
 * Encapsulates the inference logic that derives dep_namespace,
 * classmap_prefix, constant_prefix, and functions_prefix from the
 * root package's autoload configuration or package name.
 */
class ConfigDefaultsResolver
{
    /**
     * Apply sensible defaults to empty configuration properties,
     * using information from the root package when available.
     * Properties that already have explicit values are never overwritten.
     *
     * Derivation order matters: dep_directory -> classmap_directory ->
     * dep_namespace -> classmap_prefix -> constant_prefix -> functions_prefix.
     */
    public function apply(Mozart $config, Package $package): void
    {
        if (empty($config->depDirectory)) {
            $config->depDirectory = 'vendor-prefixed/';
        }

        if (empty($config->classmapDir)) {
            $config->classmapDir = $config->depDirectory;
        }

        $depNamespaceWasEmpty = empty($config->depNamespace);
        $rootNamespace = $this->inferRootNamespace($package);

        if ($depNamespaceWasEmpty && !empty($rootNamespace)) {
            $config->depNamespace = $rootNamespace . '\\Dependencies';
        }

        if ($depNamespaceWasEmpty && empty($config->depNamespace)) {
            throw new ConfigurationException(
                'Could not automatically determine dep_namespace. ' .
                'Mozart tried to infer it from: ' .
                '(1) the PSR-4 autoload namespace in your composer.json, or ' .
                '(2) your package name (vendor/package format). ' .
                'Please explicitly set "extra.mozart.dep_namespace" in your composer.json.'
            );
        }

        if (empty($config->classmapPrefix)) {
            $prefixSource = $depNamespaceWasEmpty ? $rootNamespace : $config->depNamespace;
            $config->classmapPrefix = $this->namespaceToPrefixString($prefixSource);
        }

        if (empty($config->constantPrefix)) {
            $config->constantPrefix = strtoupper($config->classmapPrefix);
        }

        if (empty($config->functionsPrefix)) {
            $config->functionsPrefix = strtolower($config->classmapPrefix);
        }
    }

    /**
     * Infer the root namespace from the package's PSR-4 autoload entry or,
     * failing that, from the package name. Used to derive dep_namespace
     * (with \Dependencies appended) and classmap_prefix (as a flat
     * underscore-separated prefix).
     */
    private function inferRootNamespace(Package $package): string
    {
        foreach ($package->getAutoloaders() as $autoloader) {
            if ($autoloader instanceof Psr4) {
                $namespace = $autoloader->getSearchNamespace();
                if (!empty($namespace)) {
                    return $namespace;
                }
            }
        }

        if (isset($package->name) && !empty($package->name) && str_contains($package->name, '/')) {
            $parts = explode('/', $package->name);
            $converted = array_map(function (string $part): string {
                return str_replace(' ', '', ucwords(str_replace('-', ' ', $part)));
            }, $parts);

            return implode('\\', $converted);
        }

        return '';
    }

    /**
     * Convert a namespace string to a flat prefix by replacing backslashes
     * with underscores and appending a trailing underscore.
     */
    private function namespaceToPrefixString(string $namespace): string
    {
        $trimmed = trim($namespace, '\\');

        return $trimmed === '' ? '' : str_replace('\\', '_', $trimmed) . '_';
    }
}
