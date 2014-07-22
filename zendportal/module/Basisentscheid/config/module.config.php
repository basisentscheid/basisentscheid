<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Basisentscheid\Controller\Proposal' => 'Basisentscheid\Controller\ProposalController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'proposal' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/proposal[/][:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Basisentscheid\Controller\Proposal',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'proposal' => __DIR__ . '/../view',
        ),
    ),
);
