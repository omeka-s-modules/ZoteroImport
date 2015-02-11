<?php
namespace ZoteroImport\Form;

use Omeka\Form\AbstractForm;

class ImportForm extends AbstractForm
{
    public function buildForm()
    {
        $translator = $this->getTranslator();

        $this->add(array(
            'name' => 'type',
            'type' => 'radio',
            'options' => array(
                'label' =>  $translator->translate('Library Type'),
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
                'label' => $translator->translate('Library ID'),
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
                'label' => $translator->translate('Collection Key'),
                //'info' => $translator->translate(''),
            ),
        ));
    }
}
