<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;
use Krystal\Iso\ISO3166\Country;

final class Directory extends AbstractController
{
    /**
     * Renders a paginated, filterable list of confirmed members
     * 
     * @return string
     */
    public function indexAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);

        $memberManager = $this->getModuleService('memberManager');
        $memberTagService = $this->getModuleService('memberTagService');

        // Filter params
        $country = $this->request->getQuery('country', null);
        $company = $this->request->getQuery('company', null);
        $tagIds = (array) $this->request->getQuery('tags', []);
        $memberIds = (array) $this->request->getQuery('member_ids', []);

        $members = $memberManager->fetchFiltered($pageNumber, 20, [
            'country' => $country,
            'company' => $company,
            'tagIds' => $tagIds,
            'memberIds' => $memberIds
        ]);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Members'))
             ->setSeo(true);

        return $this->view->render('member-directory', [
            'page' => $page,
            'members' => $members,
            'paginator' => $memberManager->getPaginator(),
            'total' => $memberManager->getPaginator()->getTotalAmount(),
            'countries' => (new Country())->getAll(),
            'companies' => $memberManager->fetchDistinctCompanies(),
            'payload' => $memberTagService->fetchPickerPayload(),
            'filterCountry' => $country,
            'filterCompany' => $company,
            'filterTags' => $tagIds
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
        $memberTagService = $this->getModuleService('memberTagService');

        $member = $memberManager->fetchPublicProfile((int) $id);

        if ($member === false) {
            return false;
        }

        $page = new VirtualEntity();
        $page->setTitle($member->getDisplayName())
             ->setSeo(true);

        return $this->view->render('member-public-profile', [
            'page' => $page,
            'member' => $member,
            'tagGroups' => $memberTagService->fetchGroupedByMember((int) $id)
        ]);
    }
    
}