<?php

namespace Sim\Auth\Interfaces;

interface IPage
{
    /**
     * Add page(s) to database
     *
     * @param array $pages
     * @return static
     */
    public function addPages(array $pages);

    /**
     * Remove page(s) from database
     *
     * @param array $pages
     * @return static
     */
    public function removePages(array $pages);

    /**
     * Check if a page exists
     *
     * @param string $page
     * @return bool
     */
    public function hasPage(string $page): bool;

    /**
     * Get all pages
     *
     * @return array
     */
    public function getPages(): array;
}