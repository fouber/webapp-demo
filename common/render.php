<?php
define('WP_PATH', dirname(__FILE__) . '/');
define('WP_CONFIG_PATH', WP_PATH . 'config/');

require_once(WP_PATH . '/smarty/Smarty.class.php');
require_once(WP_PATH . '/BigPipe/BigPipe.php');

class WPRender {
    static private $_tpl_engine = null;
    public function __construct() {
        if (null === $this->_tpl_engine) {
            $this->_tpl_engine = new Smarty();
            $this->_tpl_engine->setTemplateDir(WP_PATH . '/template');
            $this->_tpl_engine->setConfigDir(WP_PATH . '/config');
            $this->_tpl_engine->setPluginsDir(array(
                WP_PATH . '/plugin',
                WP_PATH . '/smarty/plugin'
            ));
            $this->_tpl_engine->setLeftDelimiter('{%');
            $this->_tpl_engine->setRightDelimiter('%}');
        }
    }

    public function getEngine() {
        return $this->_tpl_engine;
    }
    
    public function display($tpl) {
        if (strpos($tpl, ':') !== false) {
            $tpl = BigPipeResource::load($tpl, $this->_tpl_engine);
        }
        $this->_tpl_engine->display($tpl);
    }

    public function __call($method, $params) {
        call_user_func_array(array($this->_tpl_engine, $method), $params);
    }
}
