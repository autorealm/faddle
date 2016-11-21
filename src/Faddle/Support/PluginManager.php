<?php namespace Faddle\Support;

//declare(strict_types=1);

/**
 * 插件管理器类
 */
class PluginManager {

    private $plugins;
    private $booter;
    private $pluginsBooted = false;

    /**
     * 
     */
    public function __construct() {
        $this->plugins = [];
        $this->booter = $this->getBooter();
        $this->registerBooter();
    }

    private function registerBooter() {
        //$cb = function() {
            $this->pluginsBooted = true;
            $this->booter->bootPlugins();
        //};
        //$cb = $cb->bindTo($this);
        
    }

    public function registerPlugin($plugin) {
        $this->plugins[get_class($plugin)] = $plugin;
        if ($this->pluginsBooted) {
            $this->booter->loadPlugin($plugin);
        }
    }

    public function removePlugin(string $name) {
        unset($this->plugins[$name]);
    }

    public function hasPlugin(string $name) {
        return isset($this->plugins[$name]);
    }

    public function getPlugins() {
        return $this->plugins;
        //return iterator_to_array($this->plugins);
    }

    public function getPlugin(string $name) {
        if (!isset($this->plugins[$name])) {
            $msg = 'Could not find a registered plugin named "%s"';
            throw new \Exception(sprintf($msg, $name));
        }

        return $this->plugins[$name];
    }

    private function getBooter() {
        return new PluginBooter($this) ;
    }

}

class PluginBooter {

    private $loading = [];
    private $loaded = [];
    private $pluggable;

    public function __construct($pluggable) {
        $this->pluggable = $pluggable;
    }

    public function bootPlugins() {
        foreach($this->pluggable->getPlugins() as $plugin) {
            $this->loadPlugin($plugin);
        }
    }

    public function loadPlugin(Plugin $plugin) {
        if ($this->notLoaded($plugin)) {
            $this->startLoading($plugin);
            $this->handlePluginDependencies($plugin);
            $plugin->boot();
            $this->finishLoading($plugin);
        } else {
            
        }
    }

    private function notLoaded(Plugin $plugin) {
        return !in_array(get_class($plugin), $this->loaded);
    }

    private function startLoading(Plugin $plugin) {
        $this->loading[] = get_class($plugin);
    }

    private function finishLoading(Plugin $plugin) {
        $name = get_class($plugin);
        $this->loading = array_diff($this->loading, [$name]);
        $this->loaded[] = $name;
    }

    private function isLoading(Plugin $plugin) {
        return in_array(get_class($plugin), $this->loading);
    }

    private function handlePluginDependencies(Plugin $plugin) {
        if ($plugin instanceof PluginDependentPlugin) {
            foreach ($plugin->depends() as $reqPluginName) {
                if (!$this->pluggable->hasPlugin($reqPluginName)) {
                    $msg = '%s requires a plugin that is not registered: %s.';
                    throw new \Exception(sprintf($msg, get_class($plugin), $reqPluginName));
                }

                $reqPlugin = $this->pluggable->getPlugin($reqPluginName);
                if ($this->isLoading($reqPlugin)) {
                    $msg = 'A circular dependency was found with %s requiring %s.';
                    throw new \Exception(sprintf($msg, get_class($plugin), $reqPluginName));
                }
                $this->loadPlugin($reqPlugin);
            }
        }
    }

}

interface Plugin {

    /**
     * Perform any actions that should be completed by your Plugin before the
     * primary execution of your app is kicked off.
     */
    public function boot();

    /**
     * Return an array of plugin names that this plugin depends on.
     *
     * @return array
     */
    public function depends();

}
