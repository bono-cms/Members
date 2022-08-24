<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Admin;

use Cms\Controller\Admin\AbstractController;
use Krystal\Stdlib\VirtualEntity;

final class Members extends AbstractController
{
    /**
     * Renders form
     * 
     * @param \Krystal\Stdlib\VirtualEntity $member
     * @return string
     */
    private function createForm($member)
    {
        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne($member->getId() ? 'Update the member' : 'Add new member');

        return $this->view->render('form', [
            'member' => $member
        ]);
    }

    /**
     * Adds a member
     * 
     * @return string
     */
    public function addAction()
    {
        $member = new VirtualEntity();
        $member->setConfirmed(1);

        return $this->createForm($member);
    }

    /**
     * Renders edit form
     * 
     * @param string $id Member id
     * @return string
     */
    public function editAction($id)
    {
        $member = $this->getModuleService('memberManager')->fetchById($id);

        if ($member !== false) {
            return $this->createForm($member);
        } else {
            return false;
        }
    }

    /**
     * Saves form data
     * 
     * @return strings
     */
    public function saveAction()
    {
        $data = $this->request->getPost();

        $memberManager = $this->getModuleService('memberManager');
        $memberManager->save($data['member']);

        $new = empty($data['member']['id']);

        $this->flashBag->set('success', $new ? 'Member profile has been registered successfully' : 'Member profile has been updated successfully');

        return $new ? $memberManager->getLastId() : 1;
    }

    /**
     * Renders the grid
     * 
     * @param integer $pageNumber Current page number
     * @return string
     */
    public function indexAction($pageNumber = 1)
    {
        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Members');

        $memberManager = $this->getModuleService('memberManager');
        $members = $memberManager->fetchAll($pageNumber, $this->getSharedPerPageCount());

        // Configure pagination instance
        $paginator = $memberManager->getPaginator();
        $paginator->setUrl($this->createUrl('Members:Admin:Members@indexAction', array(), 1));

        return $this->view->render('index', array(
            'members' => $members,
            'paginator' => $paginator
        ));
    }

    /**
     * Deletes a member
     * 
     * @param string $id Member id
     * @return string
     */
    public function deleteAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberManager->deleteById($id);

        $this->flashBag->set('success', 'Selected member has been removed successfully');
        return 1;
    }
}
