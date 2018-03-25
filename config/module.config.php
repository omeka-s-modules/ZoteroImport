<?php
namespace ZoteroImport;

return [
    'api_adapters' => [
        'invokables' => [
            'zotero_imports' => Api\Adapter\ZoteroImportAdapter::class,
            'zotero_import_items' => Api\Adapter\ZoteroImportItemAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack'      => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ExportForm::class => Form\ExportForm::class,
            Form\ImportForm::class => Form\ImportForm::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'ZoteroImport\Controller\Index' => Service\IndexControllerFactory::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label'      => 'Zotero Import', // @translate
                'route'      => 'admin/zotero',
                'resource'   => 'ZoteroImport\Controller\Index',
                'pages'      => [
                    [
                        'label' => 'Import', // @translate
                        'route' => 'admin/zotero/default',
                        'action' => 'import',
                        'resource' => 'ZoteroImport\Controller\Index',
                    ],
                    [
                        'label' => 'Past imports/exports', // @translate
                        'route' => 'admin/zotero/default',
                        'action' => 'browse',
                        'resource' => 'ZoteroImport\Controller\Index',
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'zotero' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/zotero',
                            'defaults' => [
                                '__NAMESPACE__' => 'ZoteroImport\Controller',
                                'controller' => 'index',
                                'action' => 'import',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'id' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                            'default' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Temporary kept for undo.
                    'zotero-import-id' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/zotero-import/:id[/:action]',
                            'constraints' => [
                                'id' => '\d+',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'ZoteroImport\Controller',
                                'controller' => 'index',
                                'action' => 'import',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Go', // @translate
        'Export selected to Zotero', // @translate
        'Export all to Zotero', // @translate
    ],
];
