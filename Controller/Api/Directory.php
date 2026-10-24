<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Api;

use Krystal\Iso\ISO3166\Country;

final class Directory extends AbstractApiController
{
    /**
     * GET /api/v1/members
     *
     * Returns a paginated, filtered list of confirmed members along with
     * everything the client needs to render the filter UI.
     *
     * Query params:
     *   page    - pagination page (defaults to 1)
     *   country - filter by ISO 3166-1 alpha-2 code
     *   company - filter by company name
     *   tags[]  - filter by tag ids
     *
     * @return string JSON
     */
    public function indexAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $perPage = 20;

        $country = $this->request->getQuery('country', null);
        $company = $this->request->getQuery('company', null);
        $tagIds = (array) $this->request->getQuery('tags', []);

        $memberManager = $this->getModuleService('memberManager');
        $memberTagService = $this->getModuleService('memberTagService');

        $members = $memberManager->fetchFiltered($pageNumber, $perPage, [
            'country' => $country,
            'company' => $company,
            'tagIds' => $tagIds
        ]);

        $rows = [];
        foreach ($members as $member) {
            $rows[] = $this->presentPublic($member);
        }

        $paginator = $memberManager->getPaginator();

        return $this->json([
            'data' => $rows,
            'meta' => [
                'page' => $paginator->getCurrentPage(),
                'perPage' => $paginator->getItemsPerPage(),
                'total' => $paginator->getTotalAmount(),
                'totalPages' => $paginator->getLastPage()
            ],
            'filters' => [
                'applied' => [
                    'country' => $country,
                    'company' => $company,
                    'tags' => $tagIds
                ],
                'available' => [
                    'countries' => $memberManager->fetchAvailableCountries(),
                    'companies' => $memberManager->fetchDistinctCompanies(),
                    'tags' => $memberTagService->fetchPickerPayload()
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/members/profile?id=42
     *
     * Returns a single public profile with the member's tags.
     *
     * @return string JSON
     */
    public function profileAction()
    {
        $id = (int) $this->request->getQuery('id', 0);

        if ($id <= 0) {
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'Missing member id']);
        }

        $memberManager = $this->getModuleService('memberManager');
        $memberTagService = $this->getModuleService('memberTagService');

        $member = $memberManager->fetchPublicProfile($id);

        if ($member === false) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'Member not found']);
        }

        return $this->json([
            'user' => $this->presentPublic($member),
            'tags' => $memberTagService->fetchGroupedByMember($id)
        ]);
    }

    /**
     * Shapes a MemberEntity for public API output. Excludes email, phone,
     * and address.
     *
     * @param \Members\Service\MemberEntity $member
     * @return array
     */
    private function presentPublic($member): array
    {
        return [
            'id' => $member->getId(),
            'name' => $member->getDisplayName(),
            'title' => $member->getTitle(),
            'company' => $member->getCompany(),
            'country' => $member->getCountry(),
            'bio' => $member->getBio(),
            'avatar' => $member->hasAvatar() ? $member->getAvatarUrl('128x128') : null
        ];
    }
}