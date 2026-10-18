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

final class Tag extends AbstractController
{
    /**
     * Renders the tags grid of a group
     *
     * @param string $groupId Group id
     * @return string|boolean
     */
    public function indexAction($groupId)
    {
        $groupData = $this->getModuleService('tagGroupService')->fetchById($groupId);

        if ($groupData === false) {
            return false;
        }

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Tag groups', 'Members:Admin:TagGroup@indexAction')
                   ->addOne($groupData['group']->getName());

        return $this->view->render('tag-index', [
            'group' => $groupData['group'],
            'tags' => $this->getModuleService('tagService')->fetchAllByGroup($groupId)
        ]);
    }

    /**
     * Renders the empty form for a new tag inside a group
     *
     * @param string $groupId Group id
     * @return string|boolean
     */
    public function addAction($groupId)
    {
        $groupData = $this->getModuleService('tagGroupService')->fetchById($groupId);

        if ($groupData === false) {
            return false;
        }

        $tag = new VirtualEntity();
        $tag->setGroupId((int) $groupId);

        return $this->createForm($groupData['group'], $tag, [], 'Add tag');
    }

    /**
     * Renders the form for an existing tag
     *
     * @param string $id Tag id
     * @return string|boolean
     */
    public function editAction($id)
    {
        $data = $this->getModuleService('tagService')->fetchById($id);

        if ($data === false) {
            return false;
        }

        $groupData = $this->getModuleService('tagGroupService')->fetchById($data['tag']->getGroupId());

        if ($groupData === false) {
            return false;
        }

        return $this->createForm($groupData['group'], $data['tag'], $data['translations'], 'Edit tag');
    }

    /**
     * Persists a tag and its translations
     *
     * @return string JSON
     */
    public function saveAction()
    {
        $input = $this->request->getPost();
        $service = $this->getModuleService('tagService');

        $base = [
            'group_id' => (int) $input['tag']['group_id'],
            'code' => $input['tag']['code'],
            'sort_order' => (int) $input['tag']['sort_order'],
            'is_active' => !empty($input['tag']['is_active'])
        ];

        if (!empty($input['tag']['id'])) {
            $base['id'] = (int) $input['tag']['id'];
        }

        $service->save($base, $input['translation']);

        if (!empty($base['id'])) {
            $this->flashBag->set('success', 'The tag has been updated successfully');

            return $this->json([
                'refresh' => true
            ]);
        }

        $this->flashBag->set('success', 'The tag has been created successfully');

        return $this->json([
            'redirect' => $this->createUrl('Members:Admin:Tag@indexAction', [$base['group_id']])
        ]);
    }

    /**
     * Deletes a tag by its id
     *
     * @param string $id Tag id
     * @return string JSON
     */
    public function deleteAction($id)
    {
        $this->getModuleService('tagService')->deleteById($id);
        $this->flashBag->set('success', 'The tag has been removed successfully');

        return $this->json([
            'refresh' => true
        ]);
    }

    /**
     * Renders the shared form for add/edit
     *
     * @param \Krystal\Stdlib\VirtualEntity $group
     * @param \Krystal\Stdlib\VirtualEntity $tag
     * @param array $translations
     * @param string $title
     * @return string
     */
    private function createForm($group, $tag, array $translations, $title)
    {
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Tag groups', 'Members:Admin:TagGroup@indexAction')
                   ->addOne($group->getName(), $this->createUrl('Members:Admin:Tag@indexAction', [$group->getId()]))
                   ->addOne($title);

        return $this->view->render('tag-form', [
            'group' => $group,
            'tag' => $tag,
            'translations' => $translations
        ]);
    }
}