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

    /**
     * Destroy session
     * @param string $session_uuid
     * @return static
     */
    public function destroy(string $session_uuid)
    {
        return $this;
    }
}