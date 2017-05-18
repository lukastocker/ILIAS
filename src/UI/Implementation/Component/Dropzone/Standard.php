<?php
/**
 * Class Standard
 *
 * Implementation of a dropzone which provides a message inside the dropzone.
 *
 * @author  nmaerchy <nm@studer-raimann.ch>
 * @date    05.05.17
 * @version 0.0.2
 *
 * @package ILIAS\UI\Implementation\Component\Dropzone
 */

namespace ILIAS\UI\Implementation\Component\Dropzone;

use ILIAS\UI\Implementation\Component\ComponentHelper;

class Standard extends Dropzone implements \ILIAS\UI\Component\Dropzone\Standard {
	use ComponentHelper;

	private $message = "";

	/**
	 * @inheritDoc
	 */
	public function withMessage($message) {
		$this->checkStringArg("message", $message);
		$clonedFileDropzone = clone $this;
		$clonedFileDropzone->message = $message;
		return $clonedFileDropzone;
	}


	/**
	 * @inheritDoc
	 */
	public function getMessage() {
		return $this->message;
	}
}