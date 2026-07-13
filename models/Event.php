<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// EVENT MODEL — calendar entries
// =========================================================
class Event extends BaseModel
{
    protected static string $table = 'events';
    protected static string $primaryKey = 'event_id';

    public static function upcoming(int $limit = 5): array
    {
        $sql = 'SELECT * FROM events WHERE start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT ' . (int) $limit;
        return self::db()->query($sql)->fetchAll();
    }
}
