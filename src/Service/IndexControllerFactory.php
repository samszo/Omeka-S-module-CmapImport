<?php
namespace CmapImport\Service;

use Interop\Container\ContainerInterface;
use CmapImport\Controller\IndexController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new IndexController(
            $services->get('Omeka\HttpClient')
        );
    }
}
