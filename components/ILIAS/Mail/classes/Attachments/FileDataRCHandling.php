<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\ResourceStorage\Identification\ResourceCollectionIdentification;
use ILIAS\ResourceStorage\Collection\ResourceCollection;

trait FileDataRCHandling
{
    protected function getCurrentCollection(
        array $path_to_files,
        ilMailAttachmentStakeholder $stakeholder
    ): \ILIAS\ResourceStorage\Collection\ResourceCollection {
        $rcid = $this->storage->collection()->id();
        $collection = $this->storage->collection()->get($rcid);
        foreach ($path_to_files as $path_to_file) {
            $resource = @fopen($path_to_file, 'rb');
            $rid = $this->storage->manage()->stream(
                Streams::ofResource($resource),
                $stakeholder,
                md5(basename($path_to_file))
            );
            $collection->add($rid);
        }

        $this->storage->collection()->store($collection);

        return $collection;
    }

    public function FilesFromLegacyToIRSS(array $mail_data): array
    {
        $files = [];
        $path_to_files = [];
        $attachment = unserialize($mail_data['attachments']);
        foreach ($attachment as $file) {
            $path_to_files[] = $this->fdm->getAbsoluteAttachmentPoolPathByFilename($file);
        }
        $collection = $this->getCurrentCollection($path_to_files, new ilMailAttachmentStakeholder());
        foreach ($collection->getResourceIdentifications() as $rcid) {
            $files[] = $rcid->serialize();
        }

        return $files;
    }

    public function getIDforCollection(array $mail_data): ?ResourceCollectionIdentification
    {
        $files = [];
        $path_to_files = [];
        foreach ($mail_data as $attachment) {
            $path_to_files[] = $this->fdm->getAbsoluteAttachmentPoolPathByFilename($attachment);
        }
        $collection = $this->getCurrentCollection($path_to_files, new ilMailAttachmentStakeholder());
        $rcid = $collection->getIdentification();

        return $rcid;
    }

    public function FilesFromIRSSToLegacy(ResourceCollectionIdentification $identification): array
    {
        $files = [];
        $collection = $this->storage->collection()->get($identification);
        $all_ids = $collection->getResourceIdentifications();
        foreach ($all_ids as $id) {
            $files[] = $id->serialize();
        }
        return $files;
    }

    protected function handleAttachments(array $attachments): array
    {
        $files = [];
        foreach ($attachments as $attachment) {
            $info = $this->upload_handler->getInfoResult($attachment);
            if ($info->getFileIdentifier() !== 'unknown') {
                $src = $this->upload_handler->getStreamConsumer($attachment);
                $stored = $this->fdm->storeAsAttachment(
                    $info->getName(),
                    (string) $src->getStream()
                );
                if ($stored === false) {
                    throw new Exception("File '" . $info->getName() . "' could not be stored");
                }
                $files[] = ilFileUtils::_sanitizeFilemame($info->getName());
                $this->upload_handler->removeFileForIdentifier($attachment);
            }
        }

        return $files;
    }
}
