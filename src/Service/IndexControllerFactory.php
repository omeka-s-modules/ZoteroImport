<?php
namespace ZoteroImport\Service;

use ZoteroImport\Controller\IndexController;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllers)
    {
        $services = $controllers->getServiceLocator();
        return new IndexController(
            $services->get('Omeka\JobDispatcher'),
            $services->get('Omeka\HttpClient')
        );
    }
}
