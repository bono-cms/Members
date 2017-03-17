<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Site\Controller\AbstractController;
use Krystal\Stdlib\VirtualEntity;
use Krystal\Validate\Pattern;

final class Profile extends AbstractController
{
    /**
     * Renders profile page
     * 
     * @return string
     */
    public function indexAction()
    {
        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Profile');

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('My profile'))
             ->setSeo(false);

        $this->loadSitePlugins();

        return $this->view->render('member-profile', array(
            'page' => $page
        ));
    }

    /**
     * Updates a profile
     * 
     * @return string
     */
    public function updateAction()
    {
        if ($this->request->isPost()) {
            $input = $this->request->getPost();

            $formValidator = $this->createValidator(array(
                'input' => array(
                    'source' => $input,
                    'definition' => array(
                        'name' => new Pattern\name()
                    )
                )
            ));

            if ($formValidator->isValid()) {
                // Grab a service
                $memberManager = $this->getModuleService('memberManager');
                $memberManager->update($input);

                $this->flashBag->set('success', 'Settings have been updated successfully');
                return 1;

            } else {
                return $formValidator->getErrors();
            }
        }
    }
}
