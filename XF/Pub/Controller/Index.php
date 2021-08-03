<?php

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Util\File;

class Index extends XFCP_Index
{
    public function actionCharacterBanner(ParameterBag $params)
    {
        if($this->isPost())
        {
            $upload = $this->request->getFile('upload', false, false);

            $upload->requireImage();
            $upload->setMaxFileSize(2097152); //2 MB

            if($upload->isValid())
            {
                $upload->transformImage();
                $dataDir = 'data://characterbanners/'.(\XF::visitor()->user_id).'/banner.'.$upload->getExtension();
                File::deleteAbstractedDirectory('data://characterbanners/'.(\XF::visitor()->user_id));
                File::copyFileToAbstractedPath($upload->getTempFile(), $dataDir);
                File::cleanUpTempFiles();
                return $this->message("Banner Uploaded");
            }
            else
            {
                return $this->error("Image must be smaller than 2MB.");
            }
        }
        return null;
    }
}