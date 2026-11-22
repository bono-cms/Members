<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */
namespace Members\Service;

use InvalidArgumentException;
use Members\Storage\MySQL\EventMapper;
use RuntimeException;

/**
 * Owns every business rule of the events pipeline.
 *
 * Controllers (web, admin, API) talk only to this service. It is
 * the single place where status transitions happen, where the
 * ownership check is applied, and where the location string is
 * resolved into coordinates.
 *
 * The mapper only reads and writes rows; this service decides
 * what is allowed to be written and in which state, and wraps
 * the raw rows into VirtualEntity instances for the views.
 */
final class EventService
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const MAX_TITLE_LENGTH = 200;
    const MAX_LINK_LENGTH = 500;
    const MAX_LOCATION_LENGTH = 255;

    /**
     * Event mapper instance
     *
     * @var EventMapper
     */
    private $mapper;

    /**
     * Location parser instance
     *
     * @var LocationParser
     */
    private $locationParser;

    /**
     * State initialization
     *
     * @param EventMapper $mapper
     * @param LocationParser $locationParser
     * @return void
     */
    public function __construct(EventMapper $mapper, LocationParser $locationParser)
    {
        $this->mapper = $mapper;
        $this->locationParser = $locationParser;
    }

    /**
     * Converts a database row into an EventEntity
     *
     * @param array $row
     * @return EventEntity
     */
    private function toEntity(array $row)
    {
        $entity = new EventEntity();
        $entity->setId(isset($row['id']) ? (int) $row['id'] : null)
            ->setCreatorId(isset($row['creator_id']) && $row['creator_id'] !== null ? (int) $row['creator_id'] : null)
            ->setTitle(isset($row['title']) ? $row['title'] : '')
            ->setDescription(isset($row['description']) ? $row['description'] : '')
            ->setLink(isset($row['link']) ? $row['link'] : '')
            ->setLocationName(isset($row['location_name']) ? $row['location_name'] : '')
            ->setLatitude(isset($row['latitude']) && $row['latitude'] !== null ? (float) $row['latitude'] : null)
            ->setLongitude(isset($row['longitude']) && $row['longitude'] !== null ? (float) $row['longitude'] : null)
            ->setLocationSource(isset($row['location_source']) ? $row['location_source'] : EventEntity::LOCATION_SOURCE_NONE)
            ->setStartsAt(isset($row['starts_at']) ? $row['starts_at'] : '')
            ->setEndsAt(isset($row['ends_at']) ? $row['ends_at'] : '')
            ->setStatus(isset($row['status']) ? $row['status'] : EventEntity::STATUS_PENDING)
            ->setRejectionReason(isset($row['rejection_reason']) ? $row['rejection_reason'] : '')
            ->setViewsCount(isset($row['views_count']) ? (int) $row['views_count'] : 0)
            ->setClicksCount(isset($row['clicks_count']) ? (int) $row['clicks_count'] : 0)
            ->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : '')
            ->setCreatorName(isset($row['creator_name']) ? $row['creator_name'] : null)
            ->setCreatorAvatar(isset($row['creator_avatar']) ? $row['creator_avatar'] : null);

        return $entity;
    }

    /**
     * Converts a collection of rows into entities
     *
     * @param array $rows
     * @return array
     */
    private function toEntities(array $rows)
    {
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->toEntity($row);
        }

        return $result;
    }

    /**
     * Creates an event on behalf of a regular member.
     *
     * @param int $userId Acting member id
     * @param array $data Form payload
     * @throws InvalidArgumentException If required fields are missing or invalid
     * @throws RuntimeException If the insert fails
     * @return int New event id
     */
    public function createForUser($userId, array $data)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid user id is required to create an event');
        }

        $prepared = $this->preparePayload($data);
        $prepared['creator_id'] = $userId;
        $prepared['status'] = self::STATUS_PENDING;
        $prepared['rejection_reason'] = null;
        $prepared['created_at'] = date('Y-m-d H:i:s');

        $ok = $this->mapper->insertEvent($prepared);

        if (!$ok) {
            throw new RuntimeException('Failed to create the event');
        }

        return (int) $this->mapper->getLastId();
    }

    /**
     * Creates an event on behalf of an administrator.
     *
     * @param array $data Form payload
     * @param bool $isOfficial Whether the event is official (no author)
     * @param int|null $adminId Admin id when not official, or null
     * @throws InvalidArgumentException If required fields are missing or invalid
     * @throws RuntimeException If the insert fails
     * @return int New event id
     */
    public function createByAdmin(array $data, $isOfficial = false, $adminId = null)
    {
        $prepared = $this->preparePayload($data);
        $prepared['creator_id'] = $isOfficial ? null : ($adminId === null ? null : (int) $adminId);
        $prepared['status'] = self::STATUS_APPROVED;
        $prepared['rejection_reason'] = null;
        $prepared['created_at'] = date('Y-m-d H:i:s');

        $ok = $this->mapper->insertEvent($prepared);

        if (!$ok) {
            throw new RuntimeException('Failed to create the event');
        }

        return (int) $this->mapper->getLastId();
    }

    /**
     * Updates an existing event.
     *
     * @param int $id Event id
     * @param array $data Form payload
     * @param int $actorId Acting user id
     * @param bool $isAdmin Whether the actor is an administrator
     * @throws InvalidArgumentException If the event does not exist or the actor has no access
     * @return bool
     */
    public function update($id, array $data, $actorId, $isAdmin)
    {
        $id = (int) $id;
        $existing = $this->mapper->findByPk($id);

        if ($existing === false) {
            throw new InvalidArgumentException('The event does not exist');
        }

        if (!$this->isOwnerOrAdmin($existing, $actorId, $isAdmin)) {
            throw new InvalidArgumentException('You are not allowed to edit this event');
        }

        $prepared = $this->preparePayload($data);

        if ($isAdmin && $existing['status'] === self::STATUS_REJECTED) {
            $prepared['status'] = self::STATUS_APPROVED;
            $prepared['rejection_reason'] = null;
        }

        return $this->mapper->updateEvent($id, $prepared);
    }

    /**
     * Approves a pending event.
     *
     * @param int $id Event id
     * @throws InvalidArgumentException If the event does not exist
     * @return bool
     */
    public function approve($id)
    {
        $id = (int) $id;
        $existing = $this->mapper->findByPk($id);

        if ($existing === false) {
            throw new InvalidArgumentException('The event does not exist');
        }

        if ($existing['status'] !== self::STATUS_PENDING) {
            return false;
        }

        return $this->mapper->updateEvent($id, [
            'status' => self::STATUS_APPROVED,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Rejects a pending event with an optional reason.
     *
     * @param int $id Event id
     * @param string|null $reason Optional rejection reason
     * @throws InvalidArgumentException If the event does not exist
     * @return bool
     */
    public function reject($id, $reason = null)
    {
        $id = (int) $id;
        $existing = $this->mapper->findByPk($id);

        if ($existing === false) {
            throw new InvalidArgumentException('The event does not exist');
        }

        if ($existing['status'] !== self::STATUS_PENDING) {
            return false;
        }

        return $this->mapper->updateEvent($id, [
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason === null ? null : mb_substr((string) $reason, 0, 500),
        ]);
    }

    /**
     * Deletes an event.
     *
     * @param int $id Event id
     * @param int $actorId Acting user id
     * @param bool $isAdmin Whether the actor is an administrator
     * @throws InvalidArgumentException If the event does not exist or the actor has no access
     * @return bool
     */
    public function delete($id, $actorId, $isAdmin)
    {
        $id = (int) $id;
        $existing = $this->mapper->findByPk($id);

        if ($existing === false) {
            throw new InvalidArgumentException('The event does not exist');
        }

        if (!$this->isOwnerOrAdmin($existing, $actorId, $isAdmin)) {
            throw new InvalidArgumentException('You are not allowed to delete this event');
        }

        return $this->mapper->deleteEvent($id);
    }

    /**
     * Returns a single event by id as an entity, or false when not found.
     *
     * @param int $id
     * @return VirtualEntity|bool
     */
    public function findById($id)
    {
        $row = $this->mapper->findByPk((int) $id);

        return $row === false ? false : $this->toEntity($row);
    }

    /**
     * Returns an event only if the given member owns it.
     *
     * @param int $id
     * @param int $memberId
     * @return VirtualEntity|bool
     */
    public function findByIdForOwner($id, $memberId)
    {
        $row = $this->mapper->findByPk((int) $id);

        if ($row === false) {
            return false;
        }

        if ((int) $row['creator_id'] !== (int) $memberId) {
            return false;
        }

        return $this->toEntity($row);
    }

    /**
     * Parses a map URL into coordinates.
     *
     * @param string $url
     * @return array|null
     */
    public function parseLocation($url)
    {
        $coords = $this->locationParser->parse($url);

        if ($coords === null) {
            return null;
        }

        return [
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'source' => 'url',
        ];
    }

    /**
     * Fetches a paginated list of events created by a member.
     *
     * @param int $creatorId
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchByCreatorId($creatorId, $pageNumber, $itemsPerPage)
    {
        return $this->toEntities($this->mapper->fetchByCreatorId((int) $creatorId, (int) $pageNumber, (int) $itemsPerPage));
    }

    /**
     * Fetches a paginated list of approved events for the public catalog.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchApproved($pageNumber, $itemsPerPage)
    {
        return $this->toEntities($this->mapper->fetchApproved((int) $pageNumber, (int) $itemsPerPage));
    }

    /**
     * Fetches a paginated list of all events for the admin grid.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @param array $filters
     * @return array
     */
    public function fetchAllForAdmin($pageNumber, $itemsPerPage, array $filters = [])
    {
        return $this->toEntities($this->mapper->fetchAllForAdmin((int) $pageNumber, (int) $itemsPerPage, $filters));
    }

    /**
     * Returns the paginator instance from the mapper.
     *
     * @return \Krystal\Paginate\PaginatorInterface
     */
    public function getPaginator()
    {
        return $this->mapper->getPaginator();
    }

    /**
     * Returns aggregate counters grouped by status.
     *
     * @return array
     */
    public function fetchStatusBreakdown()
    {
        return $this->mapper->fetchStatusBreakdown();
    }

    /**
     * Counts events in a given status.
     *
     * @param string $status
     * @return int
     */
    public function countByStatus($status)
    {
        return $this->mapper->countByStatus($status);
    }

    /**
     * Validates and normalizes the event form payload.
     *
     * @param array $data
     * @throws InvalidArgumentException If required fields are missing or invalid
     * @return array
     */
    private function preparePayload(array $data)
    {
        $title = isset($data['title']) ? trim((string) $data['title']) : '';

        if ($title === '') {
            throw new InvalidArgumentException('The event title is required');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException('The event title is too long');
        }

        $locationName = isset($data['location_name']) ? trim((string) $data['location_name']) : '';

        if ($locationName === '') {
            throw new InvalidArgumentException('The location name is required');
        }

        if (mb_strlen($locationName) > self::MAX_LOCATION_LENGTH) {
            throw new InvalidArgumentException('The location name is too long');
        }

        $startsAt = isset($data['starts_at']) ? trim((string) $data['starts_at']) : '';

        if ($startsAt === '' || strtotime($startsAt) === false) {
            throw new InvalidArgumentException('A valid start date and time is required');
        }

        $link = isset($data['link']) ? trim((string) $data['link']) : '';

        if ($link !== '' && mb_strlen($link) > self::MAX_LINK_LENGTH) {
            throw new InvalidArgumentException('The link is too long');
        }

        $endsAt = null;

        if (!empty($data['ends_at'])) {
            $endsAt = trim((string) $data['ends_at']);

            if (strtotime($endsAt) === false) {
                $endsAt = null;
            }
        }

        $latitude = null;
        $longitude = null;
        $locationSource = 'none';

        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $latitude = $this->toFloat($data['latitude']);
            $longitude = $this->toFloat($data['longitude']);

            if ($this->isValidCoordinate($latitude, $longitude)) {
                $locationSource = isset($data['location_source']) && $data['location_source'] === 'url' ? 'url' : 'manual';
            } else {
                $latitude = null;
                $longitude = null;
            }
        }

        return [
            'title' => $title,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'link' => $link === '' ? null : $link,
            'location_name' => $locationName,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_source' => $locationSource,
            'starts_at' => date('Y-m-d H:i:s', strtotime($startsAt)),
            'ends_at' => $endsAt === null ? null : date('Y-m-d H:i:s', strtotime($endsAt)),
        ];
    }

    /**
     * Checks whether the actor may edit/delete the event.
     *
     * @param array $event Event row
     * @param int $actorId
     * @param bool $isAdmin
     * @return bool
     */
    private function isOwnerOrAdmin(array $event, $actorId, $isAdmin)
    {
        if ($isAdmin) {
            return true;
        }

        return (int) $event['creator_id'] === (int) $actorId;
    }

    /**
     * Coerces a value to float.
     *
     * @param mixed $value
     * @return float
     */
    private function toFloat($value)
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Checks whether a lat/lng pair is within valid ranges.
     *
     * @param float $lat
     * @param float $lng
     * @return bool
     */
    private function isValidCoordinate($lat, $lng)
    {
        return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
    }
}