<?php
namespace ZoteroImport\Form;

use Omeka\Form\AbstractForm;
use Omeka\Form\Element\ResourceSelect;

class ImportForm extends AbstractForm
{
    public function buildForm()
    {
        $translator = $this->getTranslator();

        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $itemSetSelect = new ResourceSelect($serviceLocator);
        $itemSetSelect->setName('itemSet')
            ->setAttribute('required', true)
            ->setLabel('Import into')
            ->setEmptyOption('Select Item Set...')
            ->setResourceValueOptions(
                'item_sets',
                array('owner_id' => $auth->getIdentity()),
                function ($itemSet, $serviceLocator) {
                    return $itemSet->displayTitle('[no title]');
                }
            );
        $this->add($itemSetSelect);

        $this->add(array(
            'name' => 'type',
            'type' => 'radio',
            'options' => array(
                'label' =>  $translator->translate('Zotero Library Type'),
                //'info' => $translator->translate(''),
                'value_options' => array(
                    'user' => 'User',
                    'group' => 'Group',
                ),
            ),
            'attributes' => array(
                'required' => true,
            ),
        ));

        $this->add(array(
            'name' => 'id',
            'type' => 'text',
            'options' => array(
                'label' => $translator->translate('Zotero Library ID'),
                //'info' => $translator->translate(''),
            ),
            'attributes' => array(
                'required' => true,
            ),
        ));

        $this->add(array(
            'name' => 'collectionKey',
            'type' => 'text',
            'options' => array(
                'label' => $translator->translate('Zotero Collection Key'),
                //'info' => $translator->translate(''),
            ),
        ));

        $inputFilter = $this->getInputFilter();

        $inputFilter->add(array(
            'name' => 'itemSet',
            'required' => true,
            'validators' => array(
                array('name' => 'Digits'),
            ),
        ));

        $inputFilter->add(array(
            'name' => 'type',
            'required' => true,
            'filters' => array(
                array('name' => 'StringTrim'),
                array('name' => 'StringToLower'),
            ),
            'validators' => array(
                array(
                    'name' => 'InArray',
                    'options' => array(
                        'haystack' => array('user', 'group'),
                    ),
                ),
            ),
        ));

        $inputFilter->add(array(
            'name' => 'id',
            'required' => true,
            'filters' => array(
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                array('name' => 'Digits'),
            ),
        ));

        $inputFilter->add(array(
            'name' => 'collectionKey',
            'required' => false,
            'filters' => array(
                array('name' => 'StringTrim'),
                array('name' => 'Null'),
            ),
        ));
    }
}
