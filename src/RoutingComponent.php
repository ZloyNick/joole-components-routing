<?php

declare(strict_types=1);

namespace joole\components\routing;

use joole\components\routing\router\BaseRouter;
use joole\framework\Application;
use joole\framework\component\BaseComponent;
use joole\framework\exception\config\ComponentConfigurationException;

use function str_ends_with;

/**
 * The routing component for Joole framework
 */
class RoutingComponent extends BaseComponent
{

    /**
     * @param array $options
     * @return void
     * @throws ComponentConfigurationException
     */
    final public function init(array $options): void
    {
        if (!isset($options['routes'])) {
            throw new ComponentConfigurationException('Parameter "routes" not found at component ' . $this->getId());
        }

        $routesPath = $options['routes'];

        if (!is_dir($routesPath)) {
            !file_exists($routesPath) ?
                throw new ComponentConfigurationException('Routes path "' . $routesPath . '" not found.')
                : (!str_ends_with($routesPath, '.php') ?
                throw new ComponentConfigurationException('Routes file "' . $routesPath . '" haven\'t ".php" extension.')
                : require_once $routesPath
            );
        } else {
            foreach (scan_dir($routesPath) as $routesFile) {
                if (!str_ends_with($routesFile, '.php')) {
                    continue;
                }

                require_once $routesPath . DIRECTORY_SEPARATOR . $routesFile;
            }
        }
    }

    public function run(Application $app): void
    {
        $app->setRouter(new BaseRouter());
    }
}