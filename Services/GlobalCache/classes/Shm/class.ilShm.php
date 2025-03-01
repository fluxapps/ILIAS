<?php

/**
 * Class ilShm
 *
 * @beta http://php.net/manual/en/shmop.examples-basic.php
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
class ilShm extends ilGlobalCacheService
{

    /**
     * @var int
     */
    protected static $shm_id;
    protected static int $block_size = 0;
    
    /**
     * @description set self::$active
     * @return bool
     */
    protected function getActive(): bool
    {
        return function_exists('shmop_open');
    }
    
    /**
     * @description set self::$installable
     * @return bool
     */
    protected function getInstallable(): bool
    {
        return false;
    }


    /**
     * @param $service_id
     * @param $component
     */
    public function __construct($service_id, $component)
    {
        parent::__construct($service_id, $component);
        self::$shm_id = shmop_open(0xff3, "c", 0644, 100);
        self::$block_size = shmop_size(self::$shm_id);
    }
    
    public function exists(string $key):bool
    {
        return shm_has_var(self::$shm_id, $key);
    }
    
    /**
     * @param string   $key
     * @param          $serialized_value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, $serialized_value, int $ttl = null):bool
    {
        return shmop_write(self::$shm_id, $key, $serialized_value);
    }
    
    /**
     * @return mixed
     */
    public function get(string $key)
    {
        return shmop_read(self::$shm_id, 0, self::$block_size);
    }
    
    public function delete(string $key):bool
    {
        return shm_remove_var(self::$shm_id, $key);
    }


    public function flush(bool $complete = false):bool
    {
        // currently a partial flushing is missing
        shmop_delete(self::$shm_id);

        return true;
    }


    /**
     * @param $value
     *
     * @return mixed
     */
    public function serialize($value): string
    {
        return serialize($value);
    }


    /**
     * @param $serialized_value
     *
     * @return mixed
     */
    public function unserialize($serialized_value)
    {
        return unserialize($serialized_value);
    }
}
