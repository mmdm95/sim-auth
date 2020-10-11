<?php

namespace Sim\Auth\Interfaces;

interface IResource
{
    /**
     * Add resource(s) to database
     *
     * $resources has following structure:
     * [
     *   [
     *     column1 => value1,
     *     column2 => value2,
     *   ],
     *   [
     *     column1 => value3,
     *     column2 => value4,
     *   ],
     *   ...
     * ]
     *
     * @param array $resources
     * @return static
     */
    public function addResources(array $resources);

    /**
     * Remove resource(s) from database
     *
     * Note:
     *   $resources should be array of resources' name or resources' id
     *
     * @param array $resources
     * @return static
     */
    public function removeResources(array $resources);

    /**
     * Check if a resource exists
     *
     * @param string|int $resource
     * @return bool
     */
    public function hasResource($resource): bool;

    /**
     * Get all resources
     *
     * Note:
     *   It contains all columns of resources table
     *
     * @return array
     */
    public function getResources(): array;

    /**
     * Get all resources' name
     *
     * @return array
     */
    public function getResourcesNames(): array;
}