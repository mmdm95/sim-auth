<?php

namespace Sim\Auth\Interfaces;

interface IStorage
{
    /**
     * Store credentials to storage
     *
     * @return static
     */
    public function store();

    /**
     * Restore credentials to storage
     *
     * @return static
     */
    public function restore();

    /**
     * Delete stored credentials from storage
     *
     * @return static
     */
    public function delete();
}