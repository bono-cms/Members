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

final class TagGroup extends AbstractController
{
    /**
     * Renders the groups grid
     *
     * @return string
     */
    public function indexAction()
    {
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Tag groups');

        return $this->view->render('tag-group-index', [
            'groups' => $this->getModuleService('tagGroupService')->fetchAll()
        ]);
    }

    /**
     * Renders the empty form for a new group
     *
     * @return string
     */
    public function addAction()
    {
        return $this->createForm(new VirtualEntity(), [], 'Add tag group');
    }

    /**
     * Renders the form for an existing group
     *
     * @param string $id Group id
     * @return string|boolean
     */
    public function editAction($id)
    {
        $data = $this->getModuleService('tagGroupService')->fetchById($id);

        if ($data === false) {
            return false;
        }

        return $this->createForm($data['group'], $data['translations'], 'Edit tag group');
    }

    /**
     * Persists a group and its translations
     *
     * @return string JSON
     */
    public function saveAction()
    {
        $input = $this->request->getPost();
        $service = $this->getModuleService('tagGroupService');

        $base = [
            'code' => $input['group']['code'],
            'sort_order' => (int) $input['group']['sort_order'],
        ];

        if (!empty($input['group']['id'])) {
            $base['id'] = (int) $input['group']['id'];
        }

        $service->save($base, $input['translation']);

        if (!empty($base['id'])) {
            $this->flashBag->set('success', 'The tag group has been updated successfully');

            return $this->json([
                'refresh' => true
            ]);
        }

        $this->flashBag->set('success', 'The tag group has been created successfully');

        return $this->json([
            'redirect' => $this->createUrl('Members:Admin:TagGroup@indexAction')
        ]);
    }

    /**
     * Deletes a group by its id
     *
     * @param string $id Group id
     * @return string JSON
     */
    public function deleteAction($id)
    {
        $this->getModuleService('tagGroupService')->deleteById($id);
        $this->flashBag->set('success', 'The tag group has been removed successfully');

        return $this->json([
            'refresh' => true
        ]);
    }

    /**
     * Renders the shared form for add/edit
     *
     * @param \Krystal\Stdlib\VirtualEntity $group
     * @param array $translations
     * @param string $title
     * @return string
     */
    private function createForm($group, array $translations, $title)
    {
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Tag groups', 'Members:Admin:TagGroup@indexAction')
                   ->addOne($title);

        return $this->view->render('tag-group-form', [
            'group' => $group,
            'translations' => $translations
        ]);
    }
}