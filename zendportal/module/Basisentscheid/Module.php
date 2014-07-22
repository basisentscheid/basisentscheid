<?php
namespace Basisentscheid;

use Basisentscheid\Model\Proposal;
use Basisentscheid\Model\ProposalTable;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'Basisentscheid\Model\ProposalTable' =>  function($sm) {
                    $tableGateway = $sm->get('ProposalTableGateway');
                    $table = new ProposalTable($tableGateway);
                    return $table;
                },
                'ProposalTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Proposal());
                    return new TableGateway('proposals', $dbAdapter, null, $resultSetPrototype);
                },
            ),
        );
    }
}
