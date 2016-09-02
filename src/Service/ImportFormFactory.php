<?php
namespace ZoteroImport\Service;

use ZoteroImport\Form\ImportForm;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ImportFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $auth = $services->get('Omeka\AuthenticationService');
        $form = new ImportForm();
        $form->setAuth($auth);
        return $form;
    }
}
