<?php
namespace ZoteroImport\Form;

use Zend\Form\Form;
use Omeka\Form\Element\ResourceSelect;
use Zend\Validator\Callback;

class ImportForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'itemSet',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Import into', // @translate
                'info' => 'Required. Import items into this item set.', // @translate
                'empty_option' => 'Select Item Set...', // @translate
                'resource_value_options' => [
                    'resource' => 'item_sets',
                    'query' => ['is_open' => true],
                    'option_text_callback' => function ($itemSet) {
                        return $itemSet->displayTitle();
                    },
                ],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'type',
            'type' => 'radio',
            'options' => [
                'label' =>  'Library Type',  // @translate
                'info' => 'Required. Is this a user or group library?', // @translate
                'value_options' => [
                    'user' => 'User', // @translate
                    'group' => 'Group', // @translate
                ],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'id',
            'type' => 'text',
            'options' => [
                'label' => 'Library ID', // @translate
                'info' => 'Required. The user ID can be found on the "Feeds/API" section of the Zotero settings page. The group ID can be found on the Zotero group library page by looking at the URL of "Subscribe to this feed".', // @translate
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'collectionKey',
            'type' => 'text',
            'options' => [
                'label' => 'Collection Key', // @translate
                'info' => 'Not required. The collection key can be found on the Zotero library page by looking at the URL when looking at the collection.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'apiKey',
            'type' => 'text',
            'options' => [
                'label' => 'API Key', // @translate
                'info' => 'Required for non-public libraries and file import.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'importFiles',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Import Files', // @translate
                'info' => 'The API key is required to import files.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'addedAfter',
            'type' => 'datetimelocal',
            'options' => [
                'format' => 'Y-m-d\TH:i',
                'label' => 'Added after', // @translate
                'info' => 'Only import items that have been added to Zotero after this datetime.', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();

        $inputFilter->add([
            'name' => 'itemSet',
            'required' => true,
            'filters' => [
                ['name' => 'Int'],
            ],
            'validators' => [
                ['name' => 'Digits'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'type',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'StringToLower'],
            ],
            'validators' => [
                [
                    'name' => 'InArray',
                    'options' => [
                        'haystack' => ['user', 'group'],
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => 'id',
            'required' => true,
            'filters' => [
                ['name' => 'Int'],
            ],
            'validators' => [
                ['name' => 'Digits'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'collectionKey',
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'Null'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'apiKey',
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'Null'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'importFiles',
            'required' => false,
            'filters' => [
                ['name' => 'ToInt'],
            ],
            'validators' => [
                [
                    'name' => 'Callback',
                    'options' => [
                        'messages' => [
                            Callback::INVALID_VALUE => 'An API key is required to import files.', // @translate
                        ],
                        'callback' => function ($importFiles, $context) {
                            return $importFiles ? (bool) $context['apiKey'] : true;
                        },
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => 'addedAfter',
            'required' => false,
        ]);
    }
}
