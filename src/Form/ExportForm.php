<?php
namespace ZoteroImport\Form;

use Zend\Form\Element;
use Zend\Form\Form;
use ZoteroImport\Job\Export;

class ExportForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'batch_action',
            'type' => Element\Hidden::class,
        ]);
        $this->add([
            'name' => 'resource_type',
            'type' => Element\Hidden::class,
        ]);
        $this->add([
            'name' => 'resource_ids',
            'type' => Element\Hidden::class,
        ]);
        $this->add([
            'name' => 'query',
            'type' => Element\Hidden::class,
        ]);

        $this->add([
            'name' => 'type',
            'type' => Element\Radio::class,
            'options' => [
                'label' =>  'Library Type',  // @translate
                'info' => 'Is this a user or group library?', // @translate
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
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Library ID', // @translate
                'info' => 'The user ID can be found on the "Feeds/API" section of the Zotero settings page. The group ID can be found on the Zotero group library page by looking at the URL of "Subscribe to this feed".', // @translate
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'collectionKey',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Collection keys', // @translate
                'info' => 'A list of collection keys, that can be found on the Zotero library page by looking at the URL when looking at a collection.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'apiKey',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'API Key', // @translate
                'info' => 'Required to write in libraries.', // @translate
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'syncFiles',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Export files', // @translate
            ],
        ]);

        $actionOptions = [
            Export::ACTION_CREATE => 'Create a new item', // @translate
            Export::ACTION_REPLACE => 'Replace all metadata and files of the item', // @translate
        ];
        $this->add([
            'name' => 'action',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Action for already exported items', // @translate
                'info' => 'The default "Create" creates items in Zotero, without check, so duplicate may be created if no date is set. The changes in Zotero are kept separately.
"Replace" removes all properties of the items that were already exported, and fill them with the Omeka data and files.
In case of multiple duplicates, only the first one is updated.', // @translate
                'value_options' => $actionOptions,
            ],
            'attributes' => [
                'id' => 'action',
                'class' => 'chosen-select',
            ],
        ]);

        $this->add([
            'name' => 'addedAfter',
            'type' => Element\DateTimeLocal::class,
            'options' => [
                'format' => 'Y-m-d\TH:i',
                'label' => 'Added after', // @translate
                'info' => 'Only export items that have been added to Omeka after this datetime.', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();

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
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'syncFiles',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'action',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'StringToLower'],
            ],
            'validators' => [
                [
                    'name' => 'InArray',
                    'options' => [
                        'haystack' => array_keys($actionOptions),
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
