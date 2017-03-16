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

use Site\Controller\AbstractController as SiteController;

abstract class AbstractController extends SiteController
{
    /**
     * Renders a message
     * 
     * @param string $template
     * @param array $vars
     * @return string
     */
    protected function renderMessage($template, array $vars)
    {
        return $this->view->setTheme('messages')
                          ->setModule($this->moduleName)
                          ->disableLayout()
                          ->render($template, $vars);
    }
}
