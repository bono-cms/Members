<?php

/**
 * This file is part of the Bono CMS
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
     * Creates and renders the member form
     * 
     * @param \Krystal\Stdlib\VirtualEntity|array $member Base member entity or array of entities (one per language)
     * @return string
     */
    private function createForm($member)
    {
        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Update the member');

        return $this->view->render('form', [
            'member' => $member
        ]);
    }

    /**
     * Renders an empty form for a new member
     * 
     * @return string
     */
    public function addAction()
    {
        $member = new VirtualEntity();
        $member->setConfirmed(1);
        $member->setTranslatable(true);

        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Add new member');

        return $this->view->render('form', [
            'member' => $member
        ]);
    }

    /**
     * Renders the edit form for an existing member
     * 
     * @param string $id Member id
     * @return string|boolean
     */
    public function editAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $member = $memberManager->fetchById($id, true);

        if ($member === false) {
            return false;
        }

        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Update the member');

        return $this->view->render('form', [
            'member' => $member
        ]);
    }

    /**
     * Persists member data (both base fields and translations)
     * 
     * @return string
     */
    public function saveAction()
    {
        $input = $this->request->getPost('member');

        $validator = $this->createValidation();

        $validator->field('member.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.email')
                  ->required()
                  ->addRule('email');

        $validator->field('member.login')
                  ->required();

        $validator->field('member.password')
                  ->required(null, empty($input['id']))
                  ->addRule('minlength', null, ['min' => 6]);

        if ($validator->isPassed()) {
            $memberManager = $this->getModuleService('memberManager');
            $memberManager->save($this->request->getPost());

            if (!empty($input['id'])) {
                $this->flashBag->set('success', 'The element has been updated successfully');

                return $this->json([
                    'refresh' => true
                ]);
            } else {
                $this->flashBag->set('success', 'The element has been created successfully');

                return $this->json([
                    'redirect' => $this->createUrl('Members:Admin:Members@editAction', [$memberManager->getLastId()])
                ]);
            }
        } else {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }
    }

    /**
     * Renders the members grid
     * 
     * @return string
     */
    public function indexAction()
    {
        $pageNumber = $this->request->getQuery('page', 1);

        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Members');

        $memberManager = $this->getModuleService('memberManager');
        $members = $memberManager->fetchAll($pageNumber, $this->getSharedPerPageCount());

        return $this->view->render('index', [
            'members' => $members,
            'paginator' => $memberManager->getPaginator()
        ]);
    }

    /**
     * Deletes a member by their associated id
     * 
     * @param string $id Member id
     * @return string
     */
    public function deleteAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberManager->deleteById($id);

        $this->flashBag->set('success', 'Selected member has been removed successfully');

        return $this->json([
            'refresh' => true
        ]);
    }
}