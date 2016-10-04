<?php

namespace Acquia\Club\Loader;

use Symfony\Component\Config\Loader\FileLoader;

class JsonFileLoader extends FileLoader
{
    public function load($resource, $type = null)
    {
        $config = array();
        if ($data = file_get_contents($resource)) {
            $config = json_decode($data, true);

            if (0 < $errorCode = json_last_error()) {
                throw new InvalidResourceException(sprintf('Error parsing JSON - %s', $this->getJSONErrorMessage($errorCode)));
            }
        }

        return $config;
    }

    public function supports($resource, $type = null)
    {
        $ext = pathinfo($resource, PATHINFO_EXTENSION);
        return is_string($resource) && ('json' === $ext || 'conf' === $ext);
    }
}
