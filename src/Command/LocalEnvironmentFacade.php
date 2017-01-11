<?php

namespace Acquia\Club\Command;

class LocalEnvironmentFacade
{

    /**
     * @param string $extension_name
     *
     * @return bool
     */
    public function isPhpExtensionLoaded($extension_name)
    {
        return extension_loaded($extension_name);
    }
}
