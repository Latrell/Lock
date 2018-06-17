<?php
namespace Latrell\Lock;

class NullStore extends GranuleStore implements LockInterface
{
    public function acquire($key)
    {
        // noop.
    }

    public function release($key)
    {
        // noop.
    }

    public function clear()
    {
        // noop.
    }
}
