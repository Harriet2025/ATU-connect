<?php

require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/Department.php';

class PublicController
{
    public function home(): void
    {
        $pageTitle = 'Home';
        $announcements = Announcement::latestUniversity(5);
        $events = Event::upcoming(5);
        include __DIR__ . '/../views/public/home.php';
    }

    public function universityInfo(): void
    {
        $pageTitle = 'University Information';
        $announcements = Announcement::latestUniversity(20);
        $events = Event::upcoming(10);
        include __DIR__ . '/../views/public/university-info.php';
    }

    public function departments(): void
    {
        $pageTitle = 'Departments';
        $departments = Department::all();
        include __DIR__ . '/../views/public/departments.php';
    }

    public function departmentShow(string $slug): void
    {
        $department = Department::findBySlug($slug);
        if (!$department) {
            http_response_code(404);
            include __DIR__ . '/../views/shared/404.php';
            return;
        }
        $pageTitle = $department['name'];
        $announcements = Announcement::forUser(0, $department['id'], null, 10);
        include __DIR__ . '/../views/public/department.php';
    }
}
