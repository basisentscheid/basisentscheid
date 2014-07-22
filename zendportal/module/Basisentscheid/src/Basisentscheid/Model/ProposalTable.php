<?php
namespace Basisentscheid\Model;

use Zend\Db\TableGateway\TableGateway;

class ProposalTable
{
    protected $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function fetchAll()
    {
        $resultSet = $this->tableGateway->select();
        return $resultSet;
    }

    public function getProposal($id)
    {
        $id  = (int) $id;
        $rowset = $this->tableGateway->select(array('id' => $id));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $id");
        }
        return $row;
    }

    public function saveProposal(Proposal $proposal)
    {
        // @TODO: validation, converting, foreign keys, ...
        $data = array(
            'title'              => $proposal->title,
            'content'            => $proposal->content,
            'reason'             => $proposal->reason,
            'issue'              => $proposal->issue,
            'supporters'         => $proposal->supporters,
            'quorum_reached'     => $proposal->quorum_reached,
            'admission_decision' => $proposal->admission_decision,
            'state'              => $proposal->state,
            'submitted'          => $proposal->submitted,
            'proponents'         => $proposal->proponents,
        );

        $id = (int)$proposal->id;
        if ($id == 0) {
            $this->tableGateway->insert($data);
        } else {
            if ($this->getProposal($id)) {
                $this->tableGateway->update($data, array('id' => $id));
            } else {
                throw new \Exception('Form id does not exist');
            }
        }
    }

    public function deleteProposal($id)
    {
        // @TODO: cascade deletion if needed
        $this->tableGateway->delete(array('id' => $id));
    }
}
