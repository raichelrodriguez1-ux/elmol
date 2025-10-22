
<?php
class SimpleCache {
    private $cacheDir = 'cache/';
    private $defaultTtl = 3600; // 1 hora
    
    public function __construct() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get($key, $callback = null, $ttl = null) {
        $file = $this->cacheDir . md5($key) . '.cache';
        $ttl = $ttl ?? $this->defaultTtl;
        
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            return unserialize(file_get_contents($file));
        }
        
        if (is_callable($callback)) {
            $data = $callback();
            $this->set($key, $data);
            return $data;
        }
        
        return null;
    }
    
    public function set($key, $data) {
        $file = $this->cacheDir . md5($key) . '.cache';
        file_put_contents($file, serialize($data));
    }
    
    public function clear($pattern = null) {
        $files = glob($this->cacheDir . ($pattern ? '*' . $pattern . '*' : '*.cache'));
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
?>