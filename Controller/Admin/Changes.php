<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Admin;

use Krystal\Text\TextUtils;
use Cms\Controller\Admin\AbstractController;
use Members\Service\ProfileChangeService;
use Members\View\PendingChangesPresenter;

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

        $submissions = $changeService->fetchPending($pageNumber, $this->getSharedPerPageCount());

        $languages = $this->getService('Cms', 'languageManager')->fetchAll(true);
        $stagingDir = $this->appConfig->getRootDir() . ProfileChangeService::STAGING_DIR;

        $presenter = new PendingChangesPresenter(function ($field) {
            return TextUtils::normalizeColumn($field);
        }, $stagingDir);

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
            $this->flashBag->set('success', $this->translator->translate('The change has been approved'));
        } else {
            $this->flashBag->set('error', $this->translator->translate('Could not approve the change'));
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
            $this->flashBag->set('success', $this->translator->translate('The change has been rejected'));
        } else {
            $this->flashBag->set('error', $this->translator->translate('Could not reject the change'));
        }

        $this->response->redirect($this->createUrl('Members:Admin:Changes@indexAction'));
    }
}