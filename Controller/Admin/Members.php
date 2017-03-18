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

final class Members extends AbstractController
{
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
}
