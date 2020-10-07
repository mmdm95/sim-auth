<?php

namespace Sim\Auth\Storage;

use Sim\Auth\Interfaces\IStorage;

class DBStorage implements IStorage
{
    /**
     * {@inheritdoc}
     */
    public function store()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restore()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        return $this;
    }
}