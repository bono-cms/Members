<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Site\Controller\AbstractController;
use Krystal\Stdlib\VirtualEntity;

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

        return $this->view->render('member-profile', [
            'page' => $page,
            'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true),
        ]);
    }

    /**
     * Updates a profile
     * 
     * @return string
     */
    public function updateAction()
    {
        if ($this->request->isPost()) {
            $validator = $this->createValidation();

            $validator->field('name')
                      ->required()
                      ->addRule('minlength', null, ['min' => 2]);

            if ($validator->isPassed()) {
                $input = $this->request->getPost();

                // Grab a service
                $memberManager = $this->getModuleService('memberManager');
                $memberManager->update($input);

                $this->flashBag->set('success', 'Settings have been updated successfully');
                return $this->json([
                    'refresh' => true
                ]);

            } else {
                return $this->json([
                    'errors' => $validator->getErrors()
                ]);
            }
        }
    }
}