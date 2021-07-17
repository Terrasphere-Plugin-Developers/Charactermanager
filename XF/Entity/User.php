<?php

namespace Terrasphere\Charactermanager\XF\Entity;

class User extends XFCP_User
{
    public function canLinkCharacterSheet() : int
    {
        return (int) \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
    }
}