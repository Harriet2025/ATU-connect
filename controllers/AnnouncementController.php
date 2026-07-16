<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Helpers\FileUploader;
use Models\Announcement;
use Models\AnnouncementAttachment;
use Models\AnnouncementCategory;
use Models\Student;
use Models\Lecturer;
use Helpers\NotificationDispatcher;

// =========================================================
// ANNOUNCEMENT CONTROLLER — feed, CRUD, likes, comments, pin
// =========================================================
class AnnouncementController extends BaseController
{
    public function categories(Request $request): void
    {
        $this->requireAuth();
        Response::success(AnnouncementCategory::all('category_name'));
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $filters = [
            'category_id'   => $request->query('category'),
            'department_id' => $request->query('department'),
            'search'        => $request->query('search'),
        ];
        Response::success(Announcement::feed(array_filter($filters, fn($v) => $v !== null && $v !== '')));
    }

    public function show(Request $request, $id): void
    {
        $this->requireAuth();

        $announcement = Announcement::find((int) $id);
        if (!$announcement) {
            Response::error('Announcement not found.', HTTP_NOT_FOUND);
        }

        $announcement['attachments'] = AnnouncementAttachment::forAnnouncement((int) $id);
        $announcement['comments'] = Announcement::comments((int) $id);
        $announcement['like_count'] = Announcement::likeCount((int) $id);
        $announcement['liked_by_me'] = Announcement::hasLiked((int) $id, Auth::id());

        Response::success($announcement);
    }

    public function store(Request $request): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);

        $data = $this->validate($request->all(), [
            'title'       => 'required|max:200',
            'body'        => 'required',
            'category_id' => 'required|numeric',
        ]);

        $id = Announcement::create([
            'author_id'     => Auth::id(),
            'category_id'   => $data['category_id'],
            'department_id' => $request->input('department_id'),
            'title'         => $data['title'],
            'body'          => $data['body'],
            'priority'      => $request->input('priority', 'normal'),
        ]);

        $file = $request->file('attachment');
        if ($file) {
            try {
                $path = FileUploader::upload($file, 'announcements', APP_CONFIG['uploads']['allowed_document'], APP_CONFIG['uploads']['max_size_mb']);
                AnnouncementAttachment::create([
                    'announcement_id' => $id,
                    'file_path'       => $path,
                    'original_name'   => $file['name'],
                ]);
            } catch (\RuntimeException $e) {
                Response::error($e->getMessage(), HTTP_VALIDATION_ERROR);
            }
        }

        $departmentId = $request->input('department_id');
        if ($departmentId) {
            // audience selects who in the department is notified:
            //   all       — every student in the department (default)
            //   lecturers — only lecturers in the department (HOD/admin staff
            //               broadcast; restricted so a plain lecturer can't
            //               blast the whole department's teaching staff)
            $audience = $request->input('audience', 'all');
            if ($audience === 'lecturers' && !in_array(Auth::role(), [ROLE_HOD, ROLE_ADMIN], true)) {
                $audience = 'all';
            }

            $recipients = $audience === 'lecturers'
                ? Lecturer::lecturerUserIdsInDepartment((int) $departmentId)
                : Student::studentUserIdsInDepartment((int) $departmentId);

            foreach ($recipients as $recipientUserId) {
                if ((int) $recipientUserId === Auth::id()) continue;
                NotificationDispatcher::notify(
                    $recipientUserId,
                    'announcement',
                    'New announcement: ' . $data['title'],
                    mb_substr($data['body'], 0, 200),
                    'low',
                    $id
                );
            }
        }
        // If department_id is null the announcement is university-wide; we
        // deliberately skip mass-notifying every student here to avoid an
        // unbounded broadcast loop — a real deployment would push this
        // through a queue/batch job instead.

        Response::success(Announcement::find($id), 'Announcement created.', HTTP_CREATED);
    }

    public function update(Request $request, $id): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);

        $announcement = $this->authorizedAnnouncement((int) $id);

        $data = $request->all();
        $allowed = array_intersect_key($data, array_flip(['title', 'body', 'category_id', 'department_id', 'priority']));
        if (empty($allowed)) {
            Response::error('No valid fields to update.', HTTP_VALIDATION_ERROR);
        }

        Announcement::update((int) $id, $allowed);
        Response::success(Announcement::find((int) $id), 'Announcement updated.');
    }

    public function destroy(Request $request, $id): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);
        $this->authorizedAnnouncement((int) $id);

        Announcement::delete((int) $id);
        Response::success(null, 'Announcement deleted.');
    }

    public function pin(Request $request, $id): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);
        $announcement = $this->authorizedAnnouncement((int) $id);

        Announcement::update((int) $id, ['is_pinned' => $announcement['is_pinned'] ? 0 : 1]);
        Response::success(Announcement::find((int) $id), 'Pin state toggled.');
    }

    public function like(Request $request, $id): void
    {
        $this->requireAuth();

        if (!Announcement::find((int) $id)) {
            Response::error('Announcement not found.', HTTP_NOT_FOUND);
        }

        $liked = Announcement::toggleLike((int) $id, Auth::id());
        Response::success(['liked' => $liked, 'like_count' => Announcement::likeCount((int) $id)]);
    }

    public function comment(Request $request, $id): void
    {
        $this->requireAuth();

        if (!Announcement::find((int) $id)) {
            Response::error('Announcement not found.', HTTP_NOT_FOUND);
        }

        $data = $this->validate($request->all(), ['comment_text' => 'required']);
        $commentId = Announcement::addComment((int) $id, Auth::id(), $data['comment_text']);

        Response::success(['comment_id' => $commentId], 'Comment added.', HTTP_CREATED);
    }

    private function authorizedAnnouncement(int $id): array
    {
        $announcement = Announcement::find($id);
        if (!$announcement) {
            Response::error('Announcement not found.', HTTP_NOT_FOUND);
        }
        if ((int) $announcement['author_id'] !== Auth::id() && Auth::role() !== ROLE_ADMIN) {
            Response::error('Forbidden. You do not own this announcement.', HTTP_FORBIDDEN);
        }
        return $announcement;
    }
}
