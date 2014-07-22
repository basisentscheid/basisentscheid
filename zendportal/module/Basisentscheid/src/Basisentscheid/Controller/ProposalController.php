<?php
namespace Basisentscheid\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ProposalController extends AbstractActionController
{
    public function getProposalTable()
    {
        if (!isset($this->proposalTable)) {
            $sm = $this->getServiceLocator();
            $this->proposalTable = $sm->get('Basisentscheid\Model\ProposalTable');
        }
        return $this->proposalTable;
    }

    public function indexAction()
    {
        return new ViewModel(array(
            'proposals' => $this->getProposalTable()->fetchAll(),
        ));
    }

    public function addAction()
    {
        // @TODO
    }

    public function editAction()
    {
        // @TODO
    }

    public function deleteAction()
    {
        // @TODO
    }
}
