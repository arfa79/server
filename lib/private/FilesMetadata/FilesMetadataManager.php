<?php

declare(strict_types=1);

namespace OC\FilesMetadata;

use OC\FilesMetadata\Job\UpdateSingleMetadata;
use OC\FilesMetadata\Listener\MetadataDelete;
use OC\FilesMetadata\Listener\MetadataUpdate;
use OC\FilesMetadata\Model\FilesMetadata;
use OC\FilesMetadata\Model\MetadataQuery;
use OC\FilesMetadata\Service\IndexRequestService;
use OC\FilesMetadata\Service\MetadataRequestService;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Node;
use OCP\FilesMetadata\Event\MetadataBackgroundEvent;
use OCP\FilesMetadata\Event\MetadataLiveEvent;
use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FilesMetadata\Model\IFilesMetadata;
use OCP\FilesMetadata\Model\IMetadataQuery;
use Psr\Log\LoggerInterface;

class FilesMetadataManager implements IFilesMetadataManager {
	public function __construct(
		private IEventDispatcher $eventDispatcher,
		private IJobList $jobList,
		private LoggerInterface $logger,
		private MetadataRequestService $metadataRequestService,
		private IndexRequestService $indexRequestService,
	) {
	}

	/**
	 * @param int $fileId
	 * @param bool $generate - returns an empty FilesMetadata if FilesMetadataNotFoundException
	 *
	 * @return IFilesMetadata
	 * @throws FilesMetadataNotFoundException
	 */
	public function getMetadata(int $fileId, bool $generate = false): IFilesMetadata {
		try {
			return $this->metadataRequestService->getMetadataFromFileId($fileId);
		} catch (FilesMetadataNotFoundException $e) {
			if ($generate) {
				return new FilesMetadata($fileId, true);
			}

			throw $e;
		}
	}

	public function refreshMetadata(
		Node $node,
		int $process = self::PROCESS_LIVE,
		bool $fromScratch = false,
	): IFilesMetadata {
		$metadata = null;
		if (!$fromScratch) {
			try {
				$metadata = $this->metadataRequestService->getMetadataFromFileId($node->getId());
			} catch (FilesMetadataNotFoundException $e) {
			}
		}

		if (null === $metadata) {
			$metadata = new FilesMetadata($node->getId(), true);
		}

		// is $process is LIVE, we enforce LIVE
		if ((self::PROCESS_LIVE & $process) !== 0) {
			$event = new MetadataLiveEvent($node, $metadata);
		} else {
			$event = new MetadataBackgroundEvent($node, $metadata);
		}

		$this->eventDispatcher->dispatchTyped($event);
		$this->saveMetadata($event->getMetadata());

		// if requested, we add a new job for next cron to refresh metadata out of main thread
		// if $process was set to LIVE+BACKGROUND, we run background process directly
		if ($event instanceof MetadataLiveEvent && $event->isRunAsBackgroundJobRequested()) {
			if ((self::PROCESS_BACKGROUND & $process) !== 0) {
				return $this->refreshMetadata($node, self::PROCESS_BACKGROUND);
			}

			$this->jobList->add(UpdateSingleMetadata::class, [$node->getOwner()->getUID(), $node->getId()]);
		}

		return $metadata;
	}

	/**
	 * @param IFilesMetadata $filesMetadata
	 *
	 * @return void
	 */
	public function saveMetadata(IFilesMetadata $filesMetadata): void {
		if ($filesMetadata->getFileId() === 0 || !$filesMetadata->updated()) {
			return;
		}

		try {
			if ($filesMetadata->getSyncToken() === '') {
				$this->metadataRequestService->store($filesMetadata);
			} else {
				$this->metadataRequestService->updateMetadata($filesMetadata);
			}
		} catch (DBException $e) {
			// most of the logged exception are the result of race condition
			// between 2 simultaneous process trying to create/update metadata
			$this->logger->warning('issue while saveMetadata', ['exception' => $e, 'metadata' => $filesMetadata]);

			return;
		}

		foreach ($filesMetadata->getIndexes() as $index) {
			try {
				$this->indexRequestService->updateIndex($filesMetadata, $index);
			} catch (Exception $e) {
				$this->logger->warning('...');
			}
		}
	}

	public function deleteMetadata(int $fileId): void {
		try {
			$this->metadataRequestService->dropMetadata($fileId);
		} catch (Exception $e) {
			$this->logger->warning('issue while deleteMetadata', ['exception' => $e, 'fileId' => $fileId]);
		}

		try {
			$this->indexRequestService->dropIndex($fileId);
		} catch (Exception $e) {
			$this->logger->warning('issue while deleteMetadata', ['exception' => $e, 'fileId' => $fileId]);
		}
	}

	public function getMetadataQuery(
		IQueryBuilder $qb,
		string $fileTableAlias,
		string $fileIdField
	): IMetadataQuery {
		return new MetadataQuery($qb, $fileTableAlias, $fileIdField);
	}

	public static function loadListeners(IEventDispatcher $eventDispatcher): void {
		$eventDispatcher->addServiceListener(NodeCreatedEvent::class, MetadataUpdate::class);
		$eventDispatcher->addServiceListener(NodeWrittenEvent::class, MetadataUpdate::class);
		$eventDispatcher->addServiceListener(NodeDeletedEvent::class, MetadataDelete::class);
	}
}
