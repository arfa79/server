<?php

declare(strict_types=1);

namespace OC\FilesMetadata\Service;

use OC\FilesMetadata\Model\FilesMetadata;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\FilesMetadata\Model\IFilesMetadata;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class MetadataRequestService {
	public const TABLE_METADATA = 'files_metadata';
	public const TABLE_METADATA_INDEX = 'files_metadata_index';

	public function __construct(
		private IDBConnection $dbConnection,
		private LoggerInterface $logger
	) {
	}

	/**
	 * @param IFilesMetadata $filesMetadata
	 *
	 * @return void
	 * @throws Exception
	 */
	public function store(IFilesMetadata $filesMetadata): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert(self::TABLE_METADATA)
		   ->setValue('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT))
		   ->setValue('json', $qb->createNamedParameter(json_encode($filesMetadata->jsonSerialize())))
		   ->setValue('sync_token', $qb->createNamedParameter($this->generateSyncToken()))
		   ->setValue('last_update', $qb->createFunction('NOW()'));
		$qb->executeStatement();
	}

	/**
	 * @param int $fileId
	 *
	 * @return IFilesMetadata
	 * @throws FilesMetadataNotFoundException
	 */
	public function getMetadataFromFileId(int $fileId): IFilesMetadata {
		try {
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('json', 'sync_token')->from(self::TABLE_METADATA);
			$qb->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
			$result = $qb->executeQuery();
			$data = $result->fetch();
			$result->closeCursor();
		} catch (Exception $e) {
			$this->logger->warning(
				'exception while getMetadataFromDatabase()', ['exception' => $e, 'fileId' => $fileId]
			);
			throw new FilesMetadataNotFoundException();
		}

		if ($data === false) {
			throw new FilesMetadataNotFoundException();
		}

		$metadata = new FilesMetadata($fileId);
		$metadata->importFromDatabase($data);

		return $metadata;
	}

	/**
	 * @param int $fileId
	 *
	 * @return void
	 * @throws Exception
	 */
	public function dropMetadata(int $fileId): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::TABLE_METADATA)
		   ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param IFilesMetadata $filesMetadata
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function updateMetadata(IFilesMetadata $filesMetadata): int {
		$qb = $this->dbConnection->getQueryBuilder();
		$expr = $qb->expr();

		$qb->update(self::TABLE_METADATA)
		   ->set('json', $qb->createNamedParameter(json_encode($filesMetadata->jsonSerialize())))
		   ->set('sync_token', $qb->createNamedParameter($this->generateSyncToken()))
		   ->set('last_update', $qb->createFunction('NOW()'))
		   ->where(
			   $expr->andX(
				   $expr->eq('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT)),
				   $expr->eq('sync_token', $qb->createNamedParameter($filesMetadata->getSyncToken()))
			   )
		   );

		return $qb->executeStatement();
	}


	private function generateSyncToken(): string {
		$chars = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890';

		$str = '';
		$max = strlen($chars);
		for ($i = 0; $i < 7; $i++) {
			try {
				$str .= $chars[random_int(0, $max - 2)];
			} catch (Exception $e) {
			}
		}

		return $str;
	}
}
