<?php
namespace ZoteroImport\Service;

use ZoteroImport\Form\ImportForm;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ImportFormFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $elements)
    {
        $auth = $elements->getServiceLocator()->get('Omeka\AuthenticationService');
        $form = new ImportForm();
        $form->setAuth($auth);
        return $form;
    }
}
