<?php
namespace ZoteroImport\Form;

use Omeka\Form\AbstractForm;
use Omeka\Form\Element\ResourceSelect;
use Zend\Validator\Callback;

class ImportForm extends AbstractForm
{
    public function buildForm()
    {
        $serviceLocator = $this->getServiceLocator();
        $itemSetSelect = new ResourceSelect($serviceLocator);
        $itemSetSelect->setName('itemSet')
            ->setAttribute('required', true)
            ->setLabel('Import into') // @translate
            ->setOption('info', 'Required. Import items into this item set.') // @translate
            ->setEmptyOption('Select Item Set...') // @translate
            ->setResourceValueOptions(
                'item_sets',
                array('owner_id' => $auth->getIdentity()),
                function ($itemSet, $serviceLocator) {
                    return $itemSet->displayTitle();
                }
            );
        $this->add($itemSetSelect);

        $this->add(array(
            'name' => 'type',
            'type' => 'radio',
            'options' => array(
                'label' =>  'Library Type',  // @translate
                'info' => 'Required. Is this a user or group library?', // @translate
                'value_options' => array(
                    'user' => 'User', // @translate
                    'group' => 'Group', // @translate
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
                'label' => 'Library ID', // @translate
                'info' => 'Required. The user ID can be found on the "Feeds/API" section of the Zotero settings page. The group ID can be found on the Zotero group library page by looking at the URL of "Subscribe to this feed".', // @translate
            ),
            'attributes' => array(
                'required' => true,
            ),
        ));

        $this->add(array(
            'name' => 'collectionKey',
            'type' => 'text',
            'options' => array(
                'label' => 'Collection Key', // @translate
                'info' => 'Not required. The collection key can be found on the Zotero library page by looking at the URL when looking at the collection.', // @translate
            ),
        ));

        $this->add(array(
            'name' => 'apiKey',
            'type' => 'text',
            'options' => array(
                'label' => 'API Key', // @translate
                'info' => 'Required for non-public libraries and file import.', // @translate
            ),
        ));

        $this->add(array(
            'name' => 'importFiles',
            'type' => 'checkbox',
            'options' => array(
                'label' => 'Import Files', // @translate
                'info' => 'The API key is required to import files.', // @translate
            ),
        ));

        $this->add(array(
            'name' => 'addedAfter',
            'type' => 'datetime-local',
            'options' => array(
                'format' => 'Y-m-d\TH:i',
                'label' => 'Added after', // @translate
                'info' => 'Only import items that have been added to Zotero after this datetime.', // @translate
            ),
        ));

        $inputFilter = $this->getInputFilter();

        $inputFilter->add(array(
            'name' => 'itemSet',
            'required' => true,
            'filters' => array(
                array('name' => 'Int'),
            ),
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
                array('name' => 'Int'),
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

        $inputFilter->add(array(
            'name' => 'apiKey',
            'required' => false,
            'filters' => array(
                array('name' => 'StringTrim'),
                array('name' => 'Null'),
            ),
        ));

        $inputFilter->add(array(
            'name' => 'importFiles',
            'required' => false,
            'filters' => array(
                array('name' => 'ToInt'),
            ),
            'validators' => array(
                array(
                    'name' => 'Callback',
                    'options' => array(
                        'messages' => array(
                            Callback::INVALID_VALUE => 'An API key is required to import files.', // @translate
                        ),
                        'callback' => function ($importFiles, $context) {
                            return $importFiles ? (bool) $context['apiKey'] : true;
                        },
                    ),
                ),
            ),
        ));

        $inputFilter->add(array(
            'name' => 'addedAfter',
            'required' => false,
        ));
    }
}
