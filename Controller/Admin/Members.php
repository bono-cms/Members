<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Admin;

use Cms\Controller\Admin\AbstractController;
use Members\Service\ProfileChangeService;
use Members\View\PendingChangesPresenter;
use Krystal\Stdlib\VirtualEntity;
use Krystal\Text\TextUtils;

final class Members extends AbstractController
{
    /**
     * {@inheritDoc}
     */
    protected function bootstrap()
    {
        parent::bootstrap();

        $this->view->getPartialBag()->addPartialDir(
            $this->appConfig->getModuleDir('Members') . '/View/Template/admin/partials'
        );
    }

    /**
     * Creates and renders the member form
     * 
     * @param \Krystal\Stdlib\VirtualEntity|array $member Base member entity or array of entities (one per language)
     * @param array $selected Currently selected tag ids
     * @return string
     */
    private function createForm($member, array $selected = [])
    {
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Update the member');

        return $this->view->render('form', [
            'member' => $member,
            'payload' => $this->getModuleService('memberTagService')->fetchPickerPayload(),
            'selected' => $selected
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

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Add new member');

        return $this->view->render('form', [
            'member' => $member,
            'payload' => $this->getModuleService('memberTagService')->fetchPickerPayload(),
            'selected' => []
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

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Update the member');

        $selected = $this->getModuleService('memberTagService')->fetchSelectedIds((int) $id);

        return $this->view->render('form', [
            'member' => $member,
            'payload' => $this->getModuleService('memberTagService')->fetchPickerPayload(),
            'selected' => $selected
        ]);
    }

    /**
     * Renders the full change history of a member
     *
     * @param string $id Member id
     * @return string|boolean
     */
    public function historyAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $changeService = $this->getModuleService('profileChangeService');

        $member = $memberManager->fetchById($id);

        if ($member === false) {
            return false;
        }

        $name = $member->getDisplayName();

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne($name, $this->createUrl('Members:Admin:Members@editAction', [$id]))
                   ->addOne('Change history');

        $languages = $this->getService('Cms', 'languageManager')->fetchAll(true);
        $stagingDir = $this->appConfig->getRootDir() . ProfileChangeService::STAGING_DIR;

        $presenter = new PendingChangesPresenter(function ($field) {
            return TextUtils::normalizeColumn($field);
        }, $stagingDir);

        $history = $changeService->fetchHistoryForMember((int) $id, 100);

        return $this->view->render('history', [
            'member' => $member,
            'rows' => $presenter->present($history, $languages)
        ]);
    }

    /**
     * Persists member data (both base fields and translations) and
     * the member's tag selection
     * 
     * @return string
     */
    public function saveAction()
    {
        $input = $this->request->getPost('member');
        $service = $this->getModuleService('memberManager');

        $validator = $this->createValidation();

        $validator->setFieldRule('uniqueEmail', function ($email) use ($service, $input) {
            // Treat an unchanged email as free
            if (!empty($input['id'])) {
                $member = $service->fetchById((int) $input['id']);
                if ($member && $member->getEmail() === $email) {
                    return true;
                }
            }

            return $service->isFree($email);
        }, 'This email is already taken');

        $validator->field('translation.*.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.email')
                  ->required()
                  ->addRule('email')
                  ->addRule('uniqueEmail');

        $validator->field('member.login')
                  ->required();

        $validator->field('member.password')
                  ->required(null, empty($input['id']))
                  ->addRule('minlength', null, ['min' => 6]);

        $validator->file('file')
                  ->addRule('image');

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $memberTagService = $this->getModuleService('memberTagService');

        if (!empty($input['id'])) {
            $memberId = (int) $input['id'];
            $service->update($this->request->getAll());
            $this->flashBag->set('success', 'The element has been updated successfully');
        } else {
            $service->add($this->request->getAll());
            $memberId = (int) $service->getLastId();
            $this->flashBag->set('success', 'The element has been created successfully');
        }

        $submittedTags = (array) $this->request->getPost('tags', []);
        $safeTags = $memberTagService->sanitizeSelection($submittedTags);
        $memberTagService->saveSelection($memberId, $safeTags);

        if (!empty($input['id'])) {
            return $this->json([
                'refresh' => true
            ]);
        }

        return $this->json([
            'redirect' => $this->createUrl('Members:Admin:Members@editAction', [$memberId])
        ]);
    }


    /**
     * Renders the members grid
     * 
     * @return string
     */
    public function indexAction()
    {
        $pageNumber = $this->request->getQuery('page', 1);

        $this->view->getBreadcrumbBag()
                   ->addOne('Members');

        $memberManager = $this->getModuleService('memberManager');
        $members = $memberManager->fetchAll($pageNumber, $this->getSharedPerPageCount());

        return $this->view->render('index', [
            'members' => $members,
            'paginator' => $memberManager->getPaginator(),
            'pendingCount' => $this->getModuleService('profileChangeService')->countPending()
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