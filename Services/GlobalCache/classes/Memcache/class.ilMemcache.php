<?php

/**
 * Class ilMemcache
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
class ilMemcache extends ilGlobalCacheService
{

    protected static ?\Memcached $memcache_object = null;


    /**
     * @param $service_id
     * @param $component
     */
    public function __construct(int $service_id, string $component)
    {
        if (!(self::$memcache_object instanceof Memcached) && $this->getInstallable()) {
            /**
             * @var $ilMemcacheServer ilMemcacheServer
             */
            $memcached = new Memcached();

            if (ilMemcacheServer::count() > 0) {
                $memcached->resetServerList();
                $servers = array();
                $list = ilMemcacheServer::where(array( 'status' => ilMemcacheServer::STATUS_ACTIVE ))
                                        ->get();
                foreach ($list as $ilMemcacheServer) {
                    $servers[] = array(
                        $ilMemcacheServer->getHost(),
                        $ilMemcacheServer->getPort(),
                        $ilMemcacheServer->getWeight(),
                    );
                }
                $memcached->addServers($servers);
            }

            self::$memcache_object = $memcached;
        }
        parent::__construct($service_id, $component);
    }


    protected function getMemcacheObject(): ?\Memcached
    {
        return self::$memcache_object;
    }
    
    public function exists(string $key):bool
    {
        return $this->getMemcacheObject()->get($this->returnKey($key)) !== null;
    }
    

    public function set(string $key, $serialized_value, int $ttl = null): bool
    {
        return $this->getMemcacheObject()
                    ->set($this->returnKey($key), $serialized_value, (int) $ttl);
    }
    

    public function get(string $key)
    {
        return $this->getMemcacheObject()->get($this->returnKey($key));
    }
    
    public function delete(string $key):bool
    {
        return $this->getMemcacheObject()->delete($this->returnKey($key));
    }


    public function flush(bool $complete = false):bool
    {
        return $this->getMemcacheObject()->flush();
    }


    protected function getActive(): bool
    {
        if ($this->getInstallable()) {
            $stats = $this->getMemcacheObject()->getStats();

            if (!is_array($stats)) {
                return false;
            }

            foreach ($stats as $server) {
                if ($server['pid'] > 0) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }


    protected function getInstallable(): bool
    {
        return class_exists('Memcached');
    }
    
    public function getInstallationFailureReason() : string
    {
        $stats = $this->getMemcacheObject()->getStats();
        if ((!$stats[self::STD_SERVER . ':' . self::STD_PORT]['pid']) > 0) {
            return 'No Memcached-Server available';
        }
        return parent::getInstallationFailureReason();
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

    
    public function unserialize($serialized_value)
    {
        return unserialize($serialized_value);
    }
    
    public function getInfo() : array
    {
        if ($this->isInstallable()) {
            $return = array();
            $return['__cache_info'] = $this->getMemcacheObject()->getStats();
            foreach ($this->getMemcacheObject()->getAllKeys() as $key) {
                $return[$key] = $this->getMemcacheObject()->get($key);
            }

            return $return;
        }
    }


 
    public function isValid(string $key) : bool
    {
        return true;
    }
}
