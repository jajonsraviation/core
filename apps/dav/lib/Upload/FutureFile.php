<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\Upload;

use OCA\DAV\Connector\Sabre\Directory;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\IFile;

/**
 * Class FutureFile
 *
 * The FutureFile is a SabreDav IFile which connects the chunked upload directory
 * with the AssemblyStream, who does the final assembly job
 *
 * @package OCA\DAV\Upload
 */
class FutureFile implements \Sabre\DAV\IFile {

	/** @var Directory */
	protected $root;
	/** @var string */
	protected $name;

	static public function getFutureFileName() {
		return '.file';
	}

	static public function isFutureFile() {
		$davUploadsTarget = '/dav/uploads';

		// Check if pathinfo starts with dav uploads target and basename is future file basename
		if (isset($_SERVER['PATH_INFO'])
			&& pathinfo($_SERVER['PATH_INFO'], PATHINFO_BASENAME) === FutureFile::getFutureFileName()
			&& (strpos($_SERVER['PATH_INFO'], $davUploadsTarget) === 0)) {
			return true;
		}

		return false;
	}

	/**
	 * @param Directory $root
	 * @param string $name
	 */
	function __construct(Directory $root, $name) {
		$this->root = $root;
		$this->name = $name;
	}

	/**
	 * @inheritdoc
	 */
	function put($data) {
		throw new Forbidden('Permission denied to put into this file');
	}

	/**
	 * @inheritdoc
	 */
	function get() {
		$nodes = $this->root->getChildren();
		return AssemblyStream::wrap($nodes);
	}

	/**
	 * @inheritdoc
	 */
	function getContentType() {
		return 'application/octet-stream';
	}

	/**
	 * @inheritdoc
	 */
	function getETag() {
		return $this->root->getETag();
	}

	/**
	 * @inheritdoc
	 */
	function getSize() {
		$children = $this->root->getChildren();
		$sizes = array_map(function($node) {
			/** @var IFile $node */
			return $node->getSize();
		}, $children);

		return array_sum($sizes);
	}

	/**
	 * @inheritdoc
	 */
	function delete() {
		$this->root->delete();
	}

	/**
	 * @inheritdoc
	 */
	function getName() {
		return $this->name;
	}

	/**
	 * @inheritdoc
	 */
	function setName($name) {
		throw new Forbidden('Permission denied to rename this file');
	}

	/**
	 * @inheritdoc
	 */
	function getLastModified() {
		return $this->root->getLastModified();
	}
}
