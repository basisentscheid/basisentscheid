<?php
namespace Basisentscheid\Model;

class Proposal
{
    public $id;
    public $title;
    public $content;
    public $reason;
    public $issue;
    public $supporters;
    public $quorum_reached;
    public $admission_decision;
    public $state;
    public $submitted;
    public $proponents;

    public function exchangeArray($data)
    {
        // @TODO: converting types, load referenced objects, ...
        $this->id                    = (isset($data['id']))                 ? $data['id']                 : null;
        $this->title                 = (isset($data['title']))              ? $data['title']              : null;
        $this->content               = (isset($data['content']))            ? $data['content']            : null;
        $this->reason                = (isset($data['reason']))             ? $data['reason']             : null;
        $this->issue                 = (isset($data['issue']))              ? $data['issue']              : null;
        $this->supporters            = (isset($data['supporters']))         ? $data['supporters']         : null;
        $this->quorum_reached        = (isset($data['quorum_reached']))     ? $data['quorum_reached']     : null;
        $this->admission_decision    = (isset($data['admission_decision'])) ? $data['admission_decision'] : null;
        $this->state                 = (isset($data['state']))              ? $data['state']              : null;
        $this->submitted             = (isset($data['submitted']))          ? $data['submitted']          : null;
        $this->proponents            = (isset($data['proponents']))         ? $data['proponents']         : null;
    }
}
