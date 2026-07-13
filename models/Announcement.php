<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// ANNOUNCEMENT MODEL — feed, likes, comments
// =========================================================
class Announcement extends BaseModel
{
    protected static string $table = 'announcements';
    protected static string $primaryKey = 'announcement_id';

    public static function feed(array $filters = []): array
    {
        $sql = 'SELECT a.*, u.full_name AS author_name, r.role_name AS author_role,
                        d.department_name, cat.category_name,
                        (SELECT COUNT(*) FROM announcement_likes al WHERE al.announcement_id = a.announcement_id) AS like_count,
                        (SELECT COUNT(*) FROM announcement_comments ac WHERE ac.announcement_id = a.announcement_id) AS comment_count
                 FROM announcements a
                 JOIN users u ON u.user_id = a.author_id
                 JOIN roles r ON r.role_id = u.role_id
                 JOIN announcement_categories cat ON cat.category_id = a.category_id
                 LEFT JOIN departments d ON d.department_id = a.department_id
                 WHERE 1 = 1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= ' AND a.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND a.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['search'])) {
            // native prepares can't reuse one named placeholder twice
            $sql .= ' AND (a.title LIKE :search_title OR a.body LIKE :search_body)';
            $params['search_title'] = '%' . $filters['search'] . '%';
            $params['search_body'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY a.is_pinned DESC, a.publish_at DESC';

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function likeCount(int $id): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS c FROM announcement_likes WHERE announcement_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetch()['c'];
    }

    public static function commentCount(int $id): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS c FROM announcement_comments WHERE announcement_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetch()['c'];
    }

    public static function hasLiked(int $announcementId, int $userId): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM announcement_likes WHERE announcement_id = :aid AND user_id = :uid');
        $stmt->execute(['aid' => $announcementId, 'uid' => $userId]);
        return (bool) $stmt->fetch();
    }

    public static function toggleLike(int $announcementId, int $userId): bool
    {
        if (self::hasLiked($announcementId, $userId)) {
            $stmt = self::db()->prepare('DELETE FROM announcement_likes WHERE announcement_id = :aid AND user_id = :uid');
            $stmt->execute(['aid' => $announcementId, 'uid' => $userId]);
            return false;
        }

        $stmt = self::db()->prepare('INSERT INTO announcement_likes (announcement_id, user_id) VALUES (:aid, :uid)');
        $stmt->execute(['aid' => $announcementId, 'uid' => $userId]);
        return true;
    }

    public static function comments(int $announcementId): array
    {
        $sql = 'SELECT c.*, u.full_name, u.profile_picture
                FROM announcement_comments c
                JOIN users u ON u.user_id = c.user_id
                WHERE c.announcement_id = :aid
                ORDER BY c.created_at ASC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['aid' => $announcementId]);
        return $stmt->fetchAll();
    }

    public static function addComment(int $announcementId, int $userId, string $text): int
    {
        $stmt = self::db()->prepare('INSERT INTO announcement_comments (announcement_id, user_id, comment_text) VALUES (:aid, :uid, :text)');
        $stmt->execute(['aid' => $announcementId, 'uid' => $userId, 'text' => $text]);
        return (int) self::db()->lastInsertId();
    }
}
