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
            ->setOption('info', $translator->translate('Required. Import items into this item set.'))
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
                'label' =>  $translator->translate('Library Type'),
                'info' => $translator->translate('Required. Is this a user or group library?'),
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
                'label' => $translator->translate('Library ID'),
                'info' => $translator->translate('Required. The user ID can be found on the "Feeds/API" section of the Zotero settings page. The group ID can be found on the Zotero group library page by looking at the URL of "Subscribe to this feed".'),
            ),
            'attributes' => array(
                'required' => true,
            ),
        ));

        $this->add(array(
            'name' => 'collectionKey',
            'type' => 'text',
            'options' => array(
                'label' => $translator->translate('Collection Key'),
                'info' => $translator->translate('Not required. The collection key can be found on the Zotero library page by looking at the URL when looking at the collection.'),
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
