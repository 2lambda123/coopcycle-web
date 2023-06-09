<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Organization;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\ImportQueue as TaskImportQueue;
use AppBundle\Message\ImportTasks;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\DBAL\Driver\PDOException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportTasksHandler implements MessageHandlerInterface
{
    private $objectManager;
    private $taskImportsFilesystem;
    private $spreadsheetParser;
    private $validator;
    private $liveUpdates;
    private $hashids;
    private $logger;

    public function __construct(
        EntityManagerInterface $objectManager,
        Filesystem $taskImportsFilesystem,
        TaskSpreadsheetParser $spreadsheetParser,
        ValidatorInterface $validator,
        LiveUpdates $liveUpdates,
        string $secret,
        LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->taskImportsFilesystem = $taskImportsFilesystem;
        $this->spreadsheetParser = $spreadsheetParser;
        $this->validator = $validator;
        $this->liveUpdates = $liveUpdates;
        $this->logger = $logger;
        $this->hashids = new Hashids($secret, 8);
    }

    public function __invoke(ImportTasks $message)
    {
        RemotePushNotificationManager::disable();

        $decoded = $this->hashids->decode($message->getToken());
        if (count($decoded) !== 1) {
            $this->logger->error(sprintf('Token "%s" could not be decoded', $message->getToken()));
            return;
        }

        $taskGroupId = current($decoded);

        $taskGroup = $this->objectManager
            ->getRepository(TaskGroup::class)
            ->find($taskGroupId);

        if (!$taskGroup) {
            $this->logger->error(sprintf('TaskGroup #%d does not exist', $taskGroupId));
            return;
        }

        // Download file locally
        $tempDir = sys_get_temp_dir();
        $tempnam = tempnam($tempDir, 'coopcycle_task_import');

        if (false === file_put_contents($tempnam, $this->taskImportsFilesystem->read($message->getFilename()))) {
            $this->logger->error('Could not write temp file');
            return;
        }

        $this->updateQueueStatus($message, 'started');

        try {

            $tasks = $this->spreadsheetParser->parse($tempnam, [
                'date' => $message->getDate()
            ]);

            $this->logger->info(sprintf('Importing %d tasks…', count($tasks)));

            foreach ($tasks as $task) {
                $violations = $this->validator->validate($task);
                if (count($violations) > 0) {
                    throw new \Exception($violations->get(0)->getMessage());
                }
            }

        } catch (\Exception $e) {

            $this->logger->error(sprintf('Error importing file %s, "%s"', $message->getFilename(), $e->getMessage()));

            $this->liveUpdates->toAdmins('task_import:failure', [
                'token' => $message->getToken(),
                'message' => $e->getMessage(),
            ]);
            $this->updateQueueStatus($message, 'failed', $e->getMessage());

            unlink($tempnam);
            return;
        }

        try {

            foreach ($tasks as $task) {
                $task->setGroup($taskGroup);

                if (null !== $message->getOrgId()) {
                    $organization = $this->objectManager
                        ->getRepository(Organization::class)
                        ->find($message->getOrgId());
                    if (null !== $organization) {
                        $task->setOrganization($organization);
                    }
                }

                $this->objectManager->persist($task);
            }
            $this->objectManager->flush();

        } catch (DriverException $e) {

            $this->logger->error(sprintf('Error importing file %s, "%s"', $message->getFilename(), $e->getMessage()));

            $this->liveUpdates->toAdmins('task_import:failure', [
                'token' => $message->getToken(),
                'message' => $e->getMessage(),
            ]);
            $this->updateQueueStatus($message, 'failed', $e->getMessage());

            unlink($tempnam);
            return;
        }

        $this->logger->info(sprintf('Finished importing file %s', $message->getFilename()));
        $this->liveUpdates->toAdmins('task_import:success', [
            'token' => $message->getToken()
        ]);
        $this->updateQueueStatus($message, 'completed');

        unlink($tempnam);
    }

    private function updateQueueStatus(ImportTasks $message, string $status, $error = null)
    {
        if (null !== $message->getQueueId()) {

            $taskImportQueue = $this->objectManager
                ->getRepository(TaskImportQueue::class)
                ->find($message->getQueueId());

            if (null !== $taskImportQueue) {
                $taskImportQueue->setStatus($status);
                if ('started' === $status) {
                    $taskImportQueue->setStartedAt(new \DateTime());
                } else {
                    $taskImportQueue->setFinishedAt(new \DateTime());
                }
                if (null !== $error) {
                    $taskImportQueue->setError($error);
                }
                $this->objectManager->flush();
            }
        }
    }
}
