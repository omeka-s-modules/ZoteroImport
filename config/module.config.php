<?php
return array(
    'api_adapters' => array(
        'invokables' => array(
            'zotero_imports' => 'ZoteroImport\Api\Adapter\Entity\ZoteroImportAdapter',
        ),
    ),
    'entity_manager' => array(
        'mapping_classes_paths' => array(
            OMEKA_PATH . '/module/ZoteroImport/src/Model/Entity',
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'ZoteroImport\Controller\Index' => 'ZoteroImport\Controller\IndexController',
        ),
    ),
    'view_manager' => array(
        'template_path_stack'      => array(
            OMEKA_PATH . '/module/ZoteroImport/view',
        ),
    ),
    'navigation' => array(
        'admin' => array(
            array(
                'label'      => 'Zotero Import',
                'route'      => 'zotero-import',
                'controller' => 'ZoteroImport',
                'action'     => 'index',
                'resource'   => 'ZoteroImport\Controller\Index',
            ),
        ),
    ),
    'router' => array(
        'routes' => array(
            'zotero-import' => array(
                'type' => 'Literal',
                'options' => array(
                    'route' => '/admin/zotero-import',
                    'defaults' => array(
                        '__NAMESPACE__' => 'ZoteroImport\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                        ),
                    ),
                    'id' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/:controller/:id[/[:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id'         => '\d+',
                            ),
                            'defaults' => array(
                                'action' => 'show',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
);
