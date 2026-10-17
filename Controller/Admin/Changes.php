<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Admin;

use Members\View\PendingChangesPresenter;
use Members\Service\MemberEntity;
use Cms\Controller\Admin\AbstractController;
use Krystal\Text\TextUtils;

final class Changes extends AbstractController
{
    /**
     * Renders a paginated list of pending profile change submissions
     *
     * @return string
     */
    public function indexAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Pending changes');

        $changeService = $this->getModuleService('profileChangeService');
        $memberManager = $this->getModuleService('memberManager');

        $submissions = $changeService->fetchPending($pageNumber, $this->getSharedPerPageCount());

        foreach ($submissions as &$submission) {
            $live = $memberManager->fetchById((int) $submission['member_id']);
            $submission['live'] = $live instanceof MemberEntity ? $live : null;
        }

        unset($submission);

        $languages = $this->getService('Cms', 'languageManager')->fetchAll(true);

        $presenter = new PendingChangesPresenter(function ($field) {
            return TextUtils::normalizeColumn($field);
        });

        $rows = $presenter->present($submissions, $languages);

        return $this->view->render('changes', [
            'rows' => $rows,
            'paginator' => $changeService->getPaginator()
        ]);
    }

    /**
     * Approves a pending change submission
     *
     * @param string $id Submission id
     * @return string
     */
    public function approveAction($id)
    {
        $changeService = $this->getModuleService('profileChangeService');

        if ($changeService->approve((int) $id)) {
            $this->flashBag->set('success', 'The change has been approved');
        } else {
            $this->flashBag->set('error', 'Could not approve the change');
        }

        $this->response->redirect($this->createUrl('Members:Admin:Changes@indexAction'));
    }

    /**
     * Rejects a pending change submission
     *
     * @param string $id Submission id
     * @return string
     */
    public function rejectAction($id)
    {
        $changeService = $this->getModuleService('profileChangeService');
        $reason = $this->request->getPost('reason');

        if ($changeService->reject((int) $id, $reason)) {
            $this->flashBag->set('success', 'The change has been rejected');
        } else {
            $this->flashBag->set('error', 'Could not reject the change');
        }

        $this->response->redirect($this->createUrl('Members:Admin:Changes@indexAction'));
    }
}