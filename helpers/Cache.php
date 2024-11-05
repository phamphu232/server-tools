<?php
namespace helpers;

class Cache
{
    private $cacheFile;

    // Constructor to set the cache file path
    public function __construct($filename = 'cache.json')
    {
        $baseDir = dirname(dirname(__FILE__));
        $this->cacheFile = "{$baseDir}/cache/{$filename}";

        // If the cache file doesn't exist, create it with an empty array
        if (!file_exists($this->cacheFile)) {
            file_put_contents($this->cacheFile, json_encode([]));
        }
    }

    // Method to retrieve data from the cache by key
    public function get($key)
    {
        $data = $this->readCacheFile();
        if (!isset($data[$key])) {
            return null;
        }
        return $data[$key];
    }

    // Method to save data to the cache
    public function set($key, $value)
    {
        $data = $this->readCacheFile();
        $data[$key] = $value;  // Update the cache with the new value

        return $this->writeCacheFile($data);
    }

    // Method to delete an item from the cache by key
    public function delete($key)
    {
        $data = $this->readCacheFile();

        if (isset($data[$key])) {
            unset($data[$key]);  // Remove the item from the array
            return $this->writeCacheFile($data);
        }

        return false;  // Return false if the key doesn't exist
    }

    // Method to clear the entire cache
    public function clear()
    {
        return file_put_contents($this->cacheFile, json_encode([])) !== false;
    }

    // Private method to read data from the JSON cache file
    private function readCacheFile()
    {
        $jsonData = file_get_contents($this->cacheFile);
        if (empty($jsonData)) {
            return [];
        }

        return json_decode($jsonData, true);
    }

    // Private method to write data to the JSON cache file
    private function writeCacheFile($data)
    {
        // $jsonData = json_encode($data, JSON_PRETTY_PRINT);
        $jsonData = json_encode($data);
        return file_put_contents($this->cacheFile, $jsonData) !== false;
    }
}
