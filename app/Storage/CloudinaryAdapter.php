<?php

namespace App\Storage;

use Cloudinary\Cloudinary;
use Cloudinary\Api\ApiResponse;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;

class CloudinaryAdapter implements FilesystemAdapter
{
    protected Cloudinary $cloudinary;

    public function __construct(Cloudinary $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function fileExists(string $path): bool
    {
        try {
            $response = $this->cloudinary->adminApi()->asset($this->getPublicId($path));
            return isset($response['public_id']);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        // Cloudinary doesn't have true directories, but we can check if files exist with this prefix
        try {
            $response = $this->cloudinary->adminApi()->assets([
                'type' => 'upload',
                'prefix' => $path,
                'max_results' => 1,
            ]);
            return !empty($response['resources']);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary');
            file_put_contents($tempFile, $contents);
            
            $options = $this->getOptions($config);
            $options['public_id'] = $this->getPublicId($path);
            
            $this->cloudinary->uploadApi()->upload($tempFile, $options);
            unlink($tempFile);
        } catch (\Exception $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary');
            $tempStream = fopen($tempFile, 'w+');
            
            stream_copy_to_stream($contents, $tempStream);
            fclose($tempStream);
            
            $options = $this->getOptions($config);
            $options['public_id'] = $this->getPublicId($path);
            
            $this->cloudinary->uploadApi()->upload($tempFile, $options);
            unlink($tempFile);
        } catch (\Exception $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function read(string $path): string
    {
        try {
            $url = $this->cloudinary->image($this->getPublicId($path))->toUrl();
            $contents = file_get_contents($url);
            
            if ($contents === false) {
                throw new \Exception('Could not read file');
            }
            
            return $contents;
        } catch (\Exception $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function readStream(string $path)
    {
        try {
            $url = $this->cloudinary->image($this->getPublicId($path))->toUrl();
            $stream = fopen($url, 'rb');
            
            if ($stream === false) {
                throw new \Exception('Could not open stream');
            }
            
            return $stream;
        } catch (\Exception $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->cloudinary->uploadApi()->destroy($this->getPublicId($path));
        } catch (\Exception $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->cloudinary->adminApi()->deleteAssetsByPrefix($path);
        } catch (\Exception $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Cloudinary doesn't need explicit directory creation, but we'll implement this method for compatibility
    }

    public function visibility(string $path): FileAttributes
    {
        // Cloudinary has its own access control which doesn't map directly to filesystem visibility
        return new FileAttributes($path, null, 'public');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Not directly supported in Cloudinary
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $response = $this->cloudinary->adminApi()->asset($this->getPublicId($path));
            return new FileAttributes($path, null, null, null, $response['resource_type'] . '/' . $response['format']);
        } catch (\Exception $exception) {
            throw UnableToRetrieveMetadata::mimeType($path, $exception->getMessage(), $exception);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $response = $this->cloudinary->adminApi()->asset($this->getPublicId($path));
            return new FileAttributes($path, null, null, strtotime($response['created_at']));
        } catch (\Exception $exception) {
            throw UnableToRetrieveMetadata::lastModified($path, $exception->getMessage(), $exception);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $response = $this->cloudinary->adminApi()->asset($this->getPublicId($path));
            return new FileAttributes($path, $response['bytes']);
        } catch (\Exception $exception) {
            throw UnableToRetrieveMetadata::fileSize($path, $exception->getMessage(), $exception);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $response = $this->cloudinary->adminApi()->assets([
                'type' => 'upload',
                'prefix' => $path,
                'max_results' => 500,
            ]);

            foreach ($response['resources'] as $resource) {
                $attributes = new FileAttributes(
                    $resource['public_id'],
                    $resource['bytes'],
                    'public',
                    strtotime($resource['created_at']),
                    $resource['resource_type'] . '/' . $resource['format']
                );
                yield $attributes;
            }
        } catch (\Exception $exception) {
            // Return empty list on error
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->cloudinary->uploadApi()->rename(
                $this->getPublicId($source),
                $this->getPublicId($destination)
            );
        } catch (\Exception $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            // Cloudinary doesn't have a native copy, so we download and re-upload
            $content = $this->read($source);
            $this->write($destination, $content, $config);
        } catch (\Exception $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * Get Cloudinary options from Flysystem Config
     */
    protected function getOptions(Config $config): array
    {
        $options = [];
        
        // Add resource type (image, video, raw)
        if ($config->has('resource_type')) {
            $options['resource_type'] = $config->get('resource_type');
        }
        
        // Handle video-specific options
        if ($config->has('resource_type') && $config->get('resource_type') === 'video') {
            // Video adaptive streaming formats
            if ($config->has('streaming_profile')) {
                $options['streaming_profile'] = $config->get('streaming_profile');
            }
        }

        // Add any other Cloudinary specific options
        if ($config->has('folder')) {
            $options['folder'] = $config->get('folder');
        }

        return $options;
    }

    /**
     * Get a clean public ID from a path
     */
    protected function getPublicId(string $path): string
    {
        // Remove extension and clean the path
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (!empty($extension)) {
            return substr($path, 0, strlen($path) - strlen($extension) - 1);
        }
        
        return $path;
    }
} 