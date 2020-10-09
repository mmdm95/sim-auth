<?php

namespace Sim\Auth\Interfaces;

interface IResource
{
    /**
     * Add page(s) to database
     *
     * @param array $resources
     * @return static
     */
    public function addResources(array $resources);

    /**
     * Remove page(s) from database
     *
     * @param array $resources
     * @return static
     */
    public function removeResources(array $resources);

    /**
     * Check if a page exists
     *
     * @param string $resource
     * @return bool
     */
    public function hasResource(string $resource): bool;

    /**
     * Get all pages
     *
     * @return array
     */
    public function getResources(): array;
}