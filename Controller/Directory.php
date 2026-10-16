<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;

final class Directory extends AbstractController
{
    /**
     * Renders a paginated list of confirmed members
     * 
     * @return string
     */
    public function indexAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);

        $memberManager = $this->getModuleService('memberManager');
        $members = $memberManager->fetchConfirmed($pageNumber, 20);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Members'))
             ->setSeo(true);

        return $this->view->render('member-directory', [
            'page' => $page,
            'members' => $members,
            'paginator' => $memberManager->getPaginator()
        ]);
    }

    /**
     * Renders a public profile of a confirmed member
     * 
     * @param string $id Member ID
     * @return string|boolean
     */
    public function profileAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $member = $memberManager->fetchPublicProfile((int) $id);

        if ($member === false) {
            return false; // Triggers the 404 handler
        }

        $name = $member->getName();

        if (empty($name)) {
            $name = $member->getLogin();
        }

        $page = new VirtualEntity();
        $page->setTitle($name)
             ->setSeo(true);

        return $this->view->render('member-public-profile', [
            'page' => $page,
            'member' => $member
        ]);
    }
}