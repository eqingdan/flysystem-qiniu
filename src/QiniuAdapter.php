<?php namespace EQingdan\Flysystem\Qiniu;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

/**
 * Flysystem Adapter for Rackspace.
 *
 * @package EQingdan\Flysystem\Qiniu
 */
class QiniuAdapter implements AdapterInterface
{
	protected $bucket;

	protected $domain;

	protected $auth;

	protected $uploadManager;

	protected $bucketManager;

	public function __construct($accessKey, $secretKey, $bucket, $domain)
	{
		$this->bucket = $bucket;
		$this->domain = $domain;

		$this->auth = new Auth($accessKey, $secretKey);
		$this->bucketManager = new BucketManager($this->auth);
		$this->uploadManager = new UploadManager();
	}

	protected function uploadToken()
	{
		return $this->auth->uploadToken($this->bucket);
	}

	/**
	 * Write a new file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function write($path, $contents, Config $config)
	{
		list($response, $error) = $this->uploadManager->put($this->uploadToken(), $path, $contents);
		if ($error) {
			return false;
		}

		return $response;
	}

	/**
	 * Write a new file using a stream.
	 *
	 * @param string $path
	 * @param resource $resource
	 * @param Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function writeStream($path, $resource, Config $config)
	{
		$contents = '';
		while (!feof($resource)) {
			$contents .= fread($resource, 1024);
		}

		$response = $this->write($path, $contents, $config);
		if ($response === false) {
			return $response;
		}

		if ($visibility = $config->get('visibility')) {
			$this->setVisibility($path, $visibility);
		}

		return compact('path', 'visibility');
	}

	/**
	 * Update a file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function update($path, $contents, Config $config)
	{
		$this->delete($path);
		return $this->write($path, $contents, $config);
	}

	/**
	 * Update a file using a stream.
	 *
	 * @param string $path
	 * @param resource $resource
	 * @param Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function updateStream($path, $resource, Config $config)
	{
		$this->delete($path);
		return $this->writeStream($path, $resource, $config);
	}

	/**
	 * Rename a file.
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 */
	public function rename($path, $newpath)
	{
		$response = $this->bucketManager->rename($this->bucket, $path, $newpath);
		return is_null($response);
	}

	/**
	 * Copy a file.
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 */
	public function copy($path, $newpath)
	{
		$response = $this->bucketManager->copy($this->bucket, $path, $this->bucket, $newpath);
		return is_null($response);
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function delete($path)
	{
		$response = $this->bucketManager->delete($this->bucket, $path);
		return is_null($response);
	}

	/**
	 * Delete a directory.
	 *
	 * @param string $dirname
	 *
	 * @return bool
	 */
	public function deleteDir($dirname)
	{
		return true;
	}

	/**
	 * Create a directory.
	 *
	 * @param string $dirname directory name
	 * @param Config $config
	 *
	 * @return array|false
	 */
	public function createDir($dirname, Config $config)
	{
		return ['path' => $dirname, 'type' => 'dir'];
	}

	/**
	 * Set the visibility for a file.
	 *
	 * @param string $path
	 * @param string $visibility
	 *
	 * @return array|false file meta data
	 */
	public function setVisibility($path, $visibility)
	{
		// TODO 看看如何实现，Qiniu 有 Bucket 基本的共有和私有
		return self::VISIBILITY_PUBLIC;
	}

	/**
	 * Check whether a file exists.
	 *
	 * @param string $path
	 *
	 * @return array|bool|null
	 */
	public function has($path)
	{
		list($response, $error) = $this->bucketManager->stat($this->bucket, $path);
		return is_array($response);
	}

	/**
	 * Read a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function read($path)
	{
		$contents = file_get_contents('http://'.$this->domain.'/'.$path);
		return compact('contents', 'path');
	}

	/**
	 * Read a file as a stream.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function readStream($path)
	{
		if (ini_get('allow_url_fopen')) {
			$stream = fopen('http://'.$this->domain.'/'.$path, 'r');
			return compact('stream', 'path');
		}

		return false;
	}

	/**
	 * List contents of a directory.
	 *
	 * @param string $directory
	 * @param bool $recursive
	 *
	 * @return array
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$list = [];
		$r = $this->bucketManager->listFiles($this->bucket, $directory);
		foreach ($r[0] as $v) {
			$list[] = $this->normalizeFileInfo($v);
		}
		return $list;
	}

	/**
	 * Get all the meta data of a file or directory.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getMetadata($path)
	{
		$r = $this->bucketManager->stat($this->bucket, $path);
		$r[0]['key'] = $path;
		return $this->normalizeFileInfo($r[0]);
	}

	/**
	 * Get all the meta data of a file or directory.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getSize($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * Get the mimetype of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getMimetype($path)
	{
		$response = $this->bucketManager->stat($this->bucket, $path);
		return ['mimetype' => $response[0]['mimeType']];
	}

	/**
	 * Get the timestamp of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * Get the visibility of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getVisibility($path)
	{
		// TODO: Implement getVisibility() method.
		return self::VISIBILITY_PUBLIC;
	}

	protected function normalizeFileInfo($filestat)
	{
		return array(
			'type' => 'file',
			'path' => $filestat['key'],
			'timestamp' => floor($filestat['putTime']/10000000),
			'size' => $filestat['fsize'],
		);
	}

}
