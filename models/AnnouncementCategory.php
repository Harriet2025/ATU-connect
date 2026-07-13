<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// ANNOUNCEMENT CATEGORY MODEL
// =========================================================
class AnnouncementCategory extends BaseModel
{
    protected static string $table = 'announcement_categories';
    protected static string $primaryKey = 'category_id';
}
