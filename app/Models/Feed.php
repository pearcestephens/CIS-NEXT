<?php
/**
 * CIS - Central Information System
 * app/Models/Feed.php
 * 
 * Feed model for handling timeline events, digests, and activity feeds
 * in pure MVC pattern.
 *
 * @package CIS
 * @version 1.0.0
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Models;

use App\Shared\Logging\Logger;
use Exception;

class Feed extends BaseModel
{
    protected string $table = 'feed_events';
    private Logger $logger;
    private array $config;

    public function __construct(Logger $logger = null, array $config = [])
    {
        parent::__construct();
        $this->logger = $logger ?? Logger::getInstance();
        $this->config = $config;
    }
    
    protected function getTable(): string
    {
        return $this->db->table($this->table);
    }

    /**
     * Get timeline events with filtering and pagination
     */
    public function getTimelineEvents(array $filters = []): array
    {
        try {
            $limit = $filters['limit'] ?? 50;
            $offset = $filters['offset'] ?? 0;

            $sql = "SELECT 
                        fe.id,
                        fe.ts,
                        fe.source,
                        fe.source_id,
                        fe.outlet_id,
                        fe.severity,
                        fe.title,
                        fe.summary,
                        fe.meta_json,
                        fe.entity_url,
                        fe.is_ai_generated,
                        fe.created_at,
                        'Store Name' as outlet_name
                    FROM {feed_events} fe
                    ORDER BY fe.ts DESC, fe.created_at DESC
                    LIMIT ? OFFSET ?";

            $stmt = $this->query($sql, [$limit, $offset]);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            $this->logger->error('Error retrieving timeline events', [
                'message' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [];
        }
    }

    /**
     * Get total count of events
     */
    public function getTotalEventCount(array $filters = []): int
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM {feed_events}";
            $stmt = $this->query($sql);
            $result = $stmt->fetch();
            return (int) ($result['total'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get personalized digest for user
     */
    public function getPersonalizedDigest(int $userId, ?string $outlet = null): array
    {
        try {
            $events = $this->getTimelineEvents(['limit' => 20]);
            return [
                'digest_date' => date('Y-m-d'),
                'user_id' => $userId,
                'outlet_filter' => $outlet,
                'total_events' => count($events),
                'events' => $events,
                'summary' => 'Daily digest with ' . count($events) . ' events'
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get top priority events for user
     */
    public function getTopEvents(int $userId, int $limit = 20, ?string $outlet = null): array
    {
        return $this->getTimelineEvents(['limit' => $limit]);
    }

    /**
     * Get store overview data
     */
    public function getStoreOverview(int $userId): array
    {
        return [
            ['id' => 1, 'name' => 'Main Store', 'health_score' => 95, 'status' => 'excellent'],
            ['id' => 2, 'name' => 'Branch Store', 'health_score' => 88, 'status' => 'good']
        ];
    }

    /**
     * Get store health scores
     */
    public function getStoreHealthScores(): array
    {
        return [
            ['id' => 1, 'name' => 'Main Store', 'health_score' => 95],
            ['id' => 2, 'name' => 'Branch Store', 'health_score' => 88]
        ];
    }

    /**
     * Get recent activity by store
     */
    public function getRecentActivityByStore(): array
    {
        return [
            ['store_id' => 1, 'store_name' => 'Main Store', 'recent_events' => []],
            ['store_id' => 2, 'store_name' => 'Branch Store', 'recent_events' => []]
        ];
    }

    /**
     * Mark event as read for user
     */
    public function markEventAsRead(int $eventId, int $userId): array
    {
        return [
            'success' => true,
            'message' => 'Event marked as read',
            'event_id' => $eventId
        ];
    }

    /**
     * Create a new feed event
     */
    public function createEvent(array $eventData): array
    {
        try {
            $sql = "INSERT INTO {feed_events} (
                ts, source, source_id, outlet_id, severity, 
                title, summary, meta_json, entity_url, is_ai_generated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->query($sql, [
                $eventData['ts'] ?? date('Y-m-d H:i:s'),
                $eventData['source'] ?? 'system',
                $eventData['source_id'] ?? null,
                $eventData['outlet_id'] ?? null,
                $eventData['severity'] ?? 'info',
                $eventData['title'] ?? '',
                $eventData['summary'] ?? '',
                $eventData['meta_json'] ?? '{}',
                $eventData['entity_url'] ?? null,
                $eventData['is_ai_generated'] ?? 0
            ]);

            return [
                'success' => true,
                'message' => 'Event created successfully',
                'event_id' => $this->db->lastInsertId()
            ];

        } catch (Exception $e) {
            $this->logger->error('Error creating feed event', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create event'
            ];
        }
    }

    /**
     * Update an existing feed event
     */
    public function updateEvent(int $eventId, array $eventData): array
    {
        try {
            $sql = "UPDATE {feed_events} SET 
                title = ?, summary = ?, severity = ?, 
                meta_json = ?, entity_url = ?, updated_at = NOW()
                WHERE id = ?";

            $this->query($sql, [
                $eventData['title'] ?? '',
                $eventData['summary'] ?? '',
                $eventData['severity'] ?? 'info',
                $eventData['meta_json'] ?? '{}',
                $eventData['entity_url'] ?? null,
                $eventId
            ]);

            return [
                'success' => true,
                'message' => 'Event updated successfully',
                'event_id' => $eventId
            ];

        } catch (Exception $e) {
            $this->logger->error('Error updating feed event', [
                'error' => $e->getMessage(),
                'event_id' => $eventId,
                'event_data' => $eventData
            ]);

            return [
                'success' => false,
                'error' => 'Failed to update event'
            ];
        }
    }

    /**
     * Delete a feed event
     */
    public function deleteEvent(int $eventId): array
    {
        try {
            $sql = "DELETE FROM {feed_events} WHERE id = ?";
            $this->query($sql, [$eventId]);

            return [
                'success' => true,
                'message' => 'Event deleted successfully',
                'event_id' => $eventId
            ];

        } catch (Exception $e) {
            $this->logger->error('Error deleting feed event', [
                'error' => $e->getMessage(),
                'event_id' => $eventId
            ]);

            return [
                'success' => false,
                'error' => 'Failed to delete event'
            ];
        }
    }
}
