<?php
namespace OCA\NoteBook\Controller;

use Exception;
use OCA\NoteBook\AppInfo\Application;
use OCA\NoteBook\Db\NoteMapper;
use OCA\NoteBook\Service\NoteService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\Lock\LockedException;
use Throwable;

class NotesController extends OCSController {

    public function __construct(
        string             $appName,
        IRequest           $request,
        private NoteMapper $noteMapper,
       // private NoteService $noteService,
        private ?string    $userId
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     *
     * @return DataResponse
     */
    public function getUserNotes(): DataResponse {
        try {
            return new DataResponse($this->noteMapper->getNotesOfUser($this->userId));
        } catch (Exception | Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     *
     * @param string $name
     * @param string $content
     * @return DataResponse
     */
    public function addUserNote(string $name, string $content = ''): DataResponse {
        try {
            $note= $this->noteMapper->createNote($this->userId, $name, $content);
            return new DataResponse($note);
        } catch (Exception | Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     *
     * @param int $id
     * @param string|null $name
     * @param string|null $content
     * @return DataResponse
     */
    public function editUserNote(int $id, ?string $name = null, ?string $content = null): DataResponse {
        try {
            $note = $this->noteMapper->updateNote($id, $this->userId, $name, $content);
            return new DataResponse($note);
        } catch (Exception | Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     *
     * @param int $id
     * @return DataResponse
     */
    public function deleteUserNote(int $id): DataResponse {
        try {
            $note = $this->noteMapper->deleteNote($id, $this->userId);
            return new DataResponse($note);
        } catch (Exception | Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @param int $id
     * @return DataResponse
     */
    public function exportUserNote(int $id): DataResponse {
        try {
            $path = $this->exportNote($id, $this->userId);
            return new DataResponse($path);
        } catch (Exception | Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @param string $userId
     * @return Folder
     * @throws NotPermittedException
     * @throws NotFoundException
     * @throws NoUserException
     */
    private function createOrGetNotesDirectory(string $userId): Folder {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        if ($userFolder->nodeExists(Application::NOTE_FOLDER_NAME)) {
            $node = $userFolder->get(Application::NOTE_FOLDER_NAME);
            if ($node instanceof Folder) {
                return $node;
            }
            throw new Exception('/' . Application::NOTE_FOLDER_NAME . ' exists and is not a directory');
        } else {
            return $userFolder->newFolder(Application::NOTE_FOLDER_NAME);
        }
    }

    /**
     * @param int $noteId
     * @param string $userId
     * @return string
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws NoUserException
     * @throws NotFoundException
     * @throws NotPermittedException
     * @throws \OCP\DB\Exception
     * @throws GenericFileException
     * @throws LockedException
     */
    private function exportNote(int $noteId, string $userId): string {
        $noteFolder = $this->createOrGetNotesDirectory($userId);
        $note = $this->noteMapper->getNoteOfUser($noteId, $userId);
        $fileName = $note->getName() . '.txt';
        $fileContent = $note->getContent();
        if ($noteFolder->nodeExists($fileName)) {
            $node = $noteFolder->get($fileName);
            if ($node instanceof File) {
                $node->putContent($fileContent);
                return Application::NOTE_FOLDER_NAME . '/' . $fileName;
            }
            throw new Exception('/' . Application::NOTE_FOLDER_NAME . '/' . $fileName .' exists and is not a file');
        } else {
            $noteFolder->newFile($fileName, $fileContent);
            return Application::NOTE_FOLDER_NAME . '/' . $fileName;
        }
    }
}