<?php

namespace bevin1984;

use Aws\Exception\AwsException;
use Aws\S3\Exception\DeleteMultipleObjectsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Exception\S3MultipartUploadException;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use yii\base\Component;

class MinioClient extends Component
{
    /**
     * @var S3ClientInterface
     */
    protected $client;

    public $key;
    public $secret;
    public $region;
    public $endpoint;
    public $bucket;
    public $baseUrl;
    public $prefix;
    public $version = 'latest';
    public $pathStyleEndpoint = true;
    public $options = [];
    public $urlDuration = 300;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        $config = [];
        $config['credentials'] = ['key' => $this->key, 'secret' => $this->secret];
        if ($this->pathStyleEndpoint === true) {
            $config['use_path_style_endpoint'] = true;
        }
        if ($this->region !== null) {
            $config['region'] = $this->region;
        }
        if ($this->baseUrl !== null) {
            $config['base_url'] = $this->baseUrl;
        }
        if ($this->endpoint !== null) {
            $config['endpoint'] = $this->endpoint;
        }
        $config['debug'] = false;
        $config['version'] = (($this->version !== null) ? $this->version : 'latest');
        $this->setPathPrefix($this->prefix);
        $this->client = new S3Client($config);
    }

    /**
     * Get the S3Client instance.
     * @return S3ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the S3Client bucket.
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Set the S3Client bucket.
     * @param $bucket
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * List the S3Client buckets.
     * @return array|mixed
     */
    public function listBuckets() {
        try {
            $data = $this->client->listBuckets();
            if ($data['Buckets']) {
                return $data['Buckets'];
            }
            return [];
        } catch (AwsException $e) {
            return [];
        }
    }

    /**
     * Create a directory.
     * @param string $dirname
     * @param array $config
     * @return bool|array
     */
    public function createDir($dirname, $config = [])
    {
        return $this->upload($dirname . '/', '', $config);
    }

    /**
     * Delete a directory.
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname)
    {
        try {
            $prefix = $this->applyPathPrefix($dirname) . '/';
            $this->client->deleteMatchingObjects($this->bucket, $prefix);
        } catch (DeleteMultipleObjectsException $exception) {
            return false;
        }
        return true;
    }

    /**
     * @param string $path
     * @return false|array
     */
    public function getSize($path)
    {
        $array = $this->getHeadObject($path);
        if(!$array) {
            return false;
        }
        return $array['contentLength'];
    }

    /**
     * @param string $path
     * @return false|array
     */
    public function getMimeType($path)
    {
        $array = $this->getHeadObject($path);
        if(!$array) {
            return false;
        }
        return $array['contentType'];
    }

    /**
     * @param string $path
     * @return false|array
     */
    public function getTimestamp($path)
    {
        $array = $this->getHeadObject($path);
        if(!$array) {
            return false;
        }
        return $array['lastModified'];
    }

    /**
     * Get all the head object of a file or directory.
     * @param string $path
     * @return false|array
     * @throws \Exception
     */
    protected function getHeadObject($path)
    {
        $command = $this->client->getCommand('headObject', [
                'Bucket' => $this->bucket,
                'Key'    => $this->applyPathPrefix($path)
            ] + $this->options);
        try {
            $result = $this->client->execute($command)->toArray();
        } catch (S3Exception $exception) {
            return false;
        }

        $array = ['path' => $path];
        $headers = [];
        if (isset($result['@metadata']['headers'])) {
            $headers = $result['@metadata']['headers'];
        }

        if(isset($headers['content-length'])) {
            $array['contentLength'] = intval($headers['content-length']);
        }
        if(isset($headers['content-type'])) {
            $array['contentType'] = $headers['content-type'];
        }
        if(isset($headers['last-modified'])) {
            $array['lastModified'] = strtotime($headers['last-modified']);
        }
        return $array;
    }

    /**
     * Get the path prefix.
     * @return string|null path prefix or null if pathPrefix is empty
     */
    public function getPathPrefix()
    {
        return $this->prefix;
    }

    /**
     * Apply the path prefix.
     * @param $path
     * @return string
     */
    public function applyPathPrefix($path)
    {
        return $this->getPathPrefix() . ltrim($path, '\\/');
    }

    /**
     * Set the path prefix.
     * @param $prefix
     */
    public function setPathPrefix($prefix)
    {
        $prefix = (string)$prefix;
        if ($prefix === '') {
            $this->prefix = null;
            return;
        }
        $this->prefix = rtrim($prefix, '\\/') . '/';
    }

    /**
     * Remove a path prefix.
     * @param string $path
     * @return string path without the prefix
     */
    public function removePathPrefix($path)
    {
        return substr($path, strlen($this->getPathPrefix()));
    }

    /**
     * Check if the path contains only directories
     * @param string $path
     * @return bool
     */
    protected function isOnlyDir($path)
    {
        return substr($path, -1) === '/';
    }

    /**
     * Check whether a file exists.
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        $key = $this->applyPathPrefix($path);
        if ($this->client->doesObjectExist($this->bucket, $key, $this->options)) {
            return true;
        }
        return false;
    }

    /**
     * Read a object.
     * @param $path
     * @return |null
     * @throws \Exception
     */
    public function read($path)
    {
        $response = $this->readObject($path);
        if ($response !== false) {
            return $response['Body']->getContents();
        }
        return null;
    }

    /**
     * Read a object as a stream.
     * @param $path
     * @return bool
     * @throws \Exception
     */
    public function readStream($path)
    {
        $response = $this->readObject($path);
        if ($response !== false) {
            return $response['Body']->detach();
        }
        return false;
    }

    /**
     * Save an object.
     * @param $path
     * @param $savePath
     * @return \Aws\ResultInterface|bool
     * @throws \Exception
     */
    public function save($path, $savePath)
    {
        return $this->readObject($path, $savePath);
    }

    /**
     * read an object.
     * @param $path
     * @return \Aws\ResultInterface|bool
     * @throws \Exception
     */
    protected function readObject($path, $savePath = null)
    {
        $options = [
            'Bucket' => $this->bucket,
                'Key' => $this->applyPathPrefix($path)
            ] + $this->options;

        if (!is_null($savePath)) {
            $options['SaveAs'] = $savePath;
        }

        $command = $this->client->getCommand('getObject', $options);
        try {
            return $this->client->execute($command);
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Get object url.
     * @param $path
     * @return string
     */
    public function getObjectUrl($path)
    {
        $key = $this->applyPathPrefix($path);
        return $this->client->getObjectUrl($this->bucket, $key);
    }

    /**
     * Get presigned url.
     * @param $path
     * @param int $duration
     * @return string
     */
    public function getPresignedUrl($path, $duration = 0)
    {
        $key = $this->applyPathPrefix($path);
        $command = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $key
        ]);

        if (!$duration) {
            $endTime = time() + $this->urlDuration;
        } else {
            $endTime = time() + $duration;
        }
        $request = $this->client->createPresignedRequest($command, $endTime);
        return (string)$request->getUri();
    }

    /**
     * List objects of a directory.
     * @param string $directory
     * @return array|bool
     */
    public function listObjects($directory = '')
    {
        $prefix = $this->applyPathPrefix(rtrim($directory, '/') . '/');
        $options = ['Bucket' => $this->bucket, 'Prefix' => ltrim($prefix, '/')];
        $result = $this->client->listObjects($options);
        if (empty($result['Contents'])) {
            return [];
        }
        $return = array_map(function ($object) {
            return [
                'key' => $this->removePathPrefix($object['Key']),
                'size' => $object['Size'],
                'timestamp' => $object['LastModified']->getTimestamp(),
            ];
        }, $result['Contents']);
        return $return;
    }

    /**
     * Delete a file.
     * @param $path
     * @return bool
     * @throws \Exception
     */
    public function delete($path)
    {
        $key = $this->applyPathPrefix($path);
        $command = $this->client->getCommand('deleteObject', [
            'Bucket' => $this->bucket,
            'Key' => $key
        ]);
        $this->client->execute($command)->toArray();
        return !$this->has($path);
    }

    /**
     * Rename a file.
     * @param $path
     * @param $newPath
     * @return bool
     * @throws \Exception
     */
    public function rename($path, $newPath)
    {
        if (!$this->copy($path, $newPath)) {
            return false;
        }
        return $this->delete($path);
    }

    /**
     * Copy a file.
     * @param string $path
     * @param string $newPath
     * @return bool
     */
    public function copy($path, $newPath)
    {
        $acl = array_key_exists('ACL', $this->options) ? $this->options['ACL'] : 'private';
        try {
            $this->client->copy($this->bucket, $this->applyPathPrefix($path), $this->bucket, $this->applyPathPrefix($newPath), $acl, $this->options);
        } catch (S3Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Write a new file.
     * @param string $path
     * @param string $contents
     * @param array $config
     * @return false|array
     */
    public function write($path, $contents, $config = [])
    {
        return $this->upload($path, $contents, $config);
    }

    /**
     * Write a new file using a stream.
     * @param string   $path
     * @param resource $resource
     * @param array $config
     * @return array|false
     */
    public function writeStream($path, $resource, $config = [])
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * Upload an object.
     * @param string          $path
     * @param string|resource $body
     * @param array           $options
     * @return array|bool
     */
    protected function upload($path, $body, $options = [])
    {
        $key = $this->applyPathPrefix($path);
        $options = $this->options + $options;
        $acl = array_key_exists('ACL', $options) ? $options['ACL'] : 'private';

        if (!$this->isOnlyDir($path)) {
            if (!isset($options['ContentLength'])) {
                if(is_resource($body)) {
                    $stat = fstat($body);
                    if (is_array($stat) || isset($stat['size'])) {
                        $options['ContentLength'] = $stat['size'];
                    }
                } else {
                    $options['ContentLength'] = strlen($body);
                }
            }
            if ($options['ContentLength'] === null) {
                unset($options['ContentLength']);
            }
        }

        try {
            $result = $this->client->upload($this->bucket, $key, $body, $acl, ['params' => $options])->toArray();
        } catch (S3MultipartUploadException $multipartUploadException) {
            return false;
        }

        if (isset($result['@metadata']['statusCode']) && $result['@metadata']['statusCode'] == 200) {
            return ['path' => $path, 'eTag' => $result['ETag']];
        }
        return false;
    }
}