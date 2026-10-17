<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Site\Controller\AbstractController as SiteController;

abstract class AbstractController extends SiteController
{
    /**
     * {@inheritDoc}
     */
    protected function bootstrap($action)
    {
        parent::bootstrap($action);

        // View is required only for GET request
        if ($this->request->isGet()) {
            $this->loadSitePlugins();

            $this->view->setModule($this->moduleName)
                       ->setTheme('site');
            
            $this->view->addVariables([
                'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true),
                'isLoggedIn' => $this->getModuleService('memberManager')->isLoggedIn()
            ]);

            $this->view->getPartialBag()->addPartialDir(
                $this->appConfig->getModuleDir('Members') . '/View/Template/admin/partials'
            );
        }
    }
    
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