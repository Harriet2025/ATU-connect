<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Helpers\FileUploader;
use Models\Document;

// =========================================================
// DOCUMENT CONTROLLER — notice board CRUD
// =========================================================
class DocumentController extends BaseController
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $conditions = [];
        $params = [];
        if ($request->query('category')) {
            $conditions[] = 'category = :category';
            $params['category'] = $request->query('category');
        }
        if ($request->query('search')) {
            $conditions[] = 'title LIKE :search';
            $params['search'] = '%' . $request->query('search') . '%';
        }
        if ($request->query('pinned') !== null) {
            $conditions[] = 'is_pinned = :pinned';
            $params['pinned'] = (int) $request->query('pinned');
        }

        Response::success(Document::feed($conditions, $params));
    }

    public function show(Request $request, $id): void
    {
        $this->requireAuth();

        $document = Document::find((int) $id);
        if (!$document) {
            Response::error('Document not found.', HTTP_NOT_FOUND);
        }
        Response::success($document);
    }

    public function store(Request $request): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);

        $data = $this->validate($request->all(), [
            'title' => 'required|max:200',
        ]);

        $file = $request->file('file');
        if (!$file) {
            Response::error('A document file is required.', HTTP_VALIDATION_ERROR);
        }

        try {
            $path = FileUploader::upload($file, 'documents', APP_CONFIG['uploads']['allowed_document'], APP_CONFIG['uploads']['max_size_mb']);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), HTTP_VALIDATION_ERROR);
        }

        $id = Document::create([
            'uploaded_by' => Auth::id(),
            'title'       => $data['title'],
            'file_path'   => $path,
            'category'    => $request->input('category'),
        ]);

        Response::success(Document::find($id), 'Document uploaded.', HTTP_CREATED);
    }

    public function update(Request $request, $id): void
    {
        $document = $this->authorizedDocument((int) $id);

        $allowed = array_intersect_key($request->all(), array_flip(['title', 'category']));
        if (empty($allowed)) {
            Response::error('No valid fields to update.', HTTP_VALIDATION_ERROR);
        }

        Document::update((int) $id, $allowed);
        Response::success(Document::find((int) $id), 'Document updated.');
    }

    public function destroy(Request $request, $id): void
    {
        $this->authorizedDocument((int) $id);
        Document::delete((int) $id);
        Response::success(null, 'Document deleted.');
    }

    public function pin(Request $request, $id): void
    {
        $this->requireRole(ANNOUNCEMENT_ROLES);
        $document = $this->authorizedDocument((int) $id);

        Document::update((int) $id, ['is_pinned' => $document['is_pinned'] ? 0 : 1]);
        Response::success(Document::find((int) $id), 'Pin state toggled.');
    }

    private function authorizedDocument(int $id): array
    {
        $this->requireAuth();
        $document = Document::find($id);
        if (!$document) {
            Response::error('Document not found.', HTTP_NOT_FOUND);
        }
        if ((int) $document['uploaded_by'] !== Auth::id() && Auth::role() !== ROLE_ADMIN) {
            Response::error('Forbidden. You do not own this document.', HTTP_FORBIDDEN);
        }
        return $document;
    }
}
