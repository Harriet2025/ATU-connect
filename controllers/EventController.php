<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Core\Database;
use Models\Event;

// =========================================================
// EVENT CONTROLLER — calendar CRUD
// =========================================================
class EventController extends BaseController
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $conditions = [];
        $params = [];
        if ($request->query('from')) {
            $conditions[] = 'start_datetime >= :from';
            $params['from'] = $request->query('from');
        }
        if ($request->query('to')) {
            $conditions[] = 'start_datetime <= :to';
            $params['to'] = $request->query('to');
        }
        if ($request->query('type')) {
            $conditions[] = 'event_type = :type';
            $params['type'] = $request->query('type');
        }
        if ($request->query('department_id')) {
            $conditions[] = 'department_id = :department_id';
            $params['department_id'] = $request->query('department_id');
        }

        $sql = 'SELECT * FROM events';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY start_datetime ASC';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    public function show(Request $request, $id): void
    {
        $this->requireAuth();

        $event = Event::find((int) $id);
        if (!$event) {
            Response::error('Event not found.', HTTP_NOT_FOUND);
        }
        Response::success($event);
    }

    public function store(Request $request): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);

        $data = $this->validate($request->all(), [
            'title'          => 'required|max:200',
            'event_type'     => 'required|in:exam,deadline,meeting,src_event,holiday,guest_lecture,seminar,other',
            'start_datetime' => 'required|date',
        ]);

        $id = Event::create([
            'created_by'     => Auth::id(),
            'title'          => $data['title'],
            'description'    => $request->input('description'),
            'event_type'     => $data['event_type'],
            'department_id'  => $request->input('department_id'),
            'location'       => $request->input('location'),
            'start_datetime' => $data['start_datetime'],
            'end_datetime'   => $request->input('end_datetime'),
        ]);

        Response::success(Event::find($id), 'Event created.', HTTP_CREATED);
    }

    public function update(Request $request, $id): void
    {
        $this->authorizedEvent((int) $id);

        $allowed = array_intersect_key($request->all(), array_flip([
            'title', 'description', 'event_type', 'department_id', 'location', 'start_datetime', 'end_datetime',
        ]));
        if (empty($allowed)) {
            Response::error('No valid fields to update.', HTTP_VALIDATION_ERROR);
        }

        Event::update((int) $id, $allowed);
        Response::success(Event::find((int) $id), 'Event updated.');
    }

    public function destroy(Request $request, $id): void
    {
        $this->authorizedEvent((int) $id);
        Event::delete((int) $id);
        Response::success(null, 'Event deleted.');
    }

    private function authorizedEvent(int $id): array
    {
        $this->requireAuth();
        $event = Event::find($id);
        if (!$event) {
            Response::error('Event not found.', HTTP_NOT_FOUND);
        }
        if ((int) $event['created_by'] !== Auth::id() && Auth::role() !== ROLE_ADMIN) {
            Response::error('Forbidden. You do not own this event.', HTTP_FORBIDDEN);
        }
        return $event;
    }
}
