<?php
require_once(dirname(__FILE__) . '/BigPipeResource.php');

class BigPipe {
    const CSS_LINKS_HOOK = '<!--[FIS_CSS_LINKS_HOOK]-->';

    const NO_SCRIPT = 1;
    const QUICKLING = 2;
    const PIPE_LINE = 3;

    static public $collection = array();
    static private $_session_id = 0;
    static private $_filter = array();
    static private $_context = array();
    static private $_contextMap = array();
    static private $_mode = null;
    static private $_pagelets = array();
    static private $_title = '';

    static private function init() {
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_ajax) {
            self::setMode(self::QUICKLING);
        } else {
            self::setMode(self::PIPE_LINE);
        }
        self::filter($_GET['pagelets']);
    }

    static public function setMode($mode){
        if (self::$_mode === null) {
            self::$_mode = isset($mode) ? intval($mode) : 1;
        }
        if (!self::$_mode) {
            self::$_mode = 1;
        }
    }

    static public function getMode() {
        return self::$_mode;
    }

    static public function setTitle($title){
        self::$_title = $title;
        return $title;
    }

    static public function getTitle(){
        return self::$_title;
    }

    static public function getPagelets(){
        return self::$_pagelets;
    }

    static public function start($id = null) {
        self::init();
        $id = empty($id) ? '__elm_' . self::$_session_id ++ : str_replace(array(':', '/'), '_', preg_replace('/\.tpl$/i', '', $id));
        $hit = true;
        switch(self::$_mode) {
            case self::NO_SCRIPT:
                if(empty($id)){
                    echo '<div>';
                } else {
                    echo '<div id="' . $id . '">';
                }
                break;
            case self::QUICKLING:
                $hit = self::$_filter[$id];
            case self::PIPE_LINE:
                $context = array( 'id' => $id );
                $parent = self::$_context;
                if(!empty($parent)){
                    $parent_id = $parent['id'];
                    self::$_contextMap[$parent_id] = $parent;
                    $context['parent_id'] = $parent_id;
                    if($parent['hit']) {
                        $hit = true;
                    } else if($hit && self::$_mode === self::QUICKLING){
                        unset($context['parent_id']);
                    }
                }
                $context['hit'] = $hit;
                self::$_context = $context;
                echo '<div id="' . $id . '">';
                ob_start();
                break;
        }
        return $hit;
    }

    static public function end() {
        $ret = true;
        if(self::$_mode !== self::NO_SCRIPT){
            $html = ob_get_clean();
            $pagelet = self::$_context;

            if($pagelet['hit']){
                unset($pagelet['hit']);
                $pagelet['html'] = $html;
                self::$_pagelets[] = &$pagelet;
                unset($pagelet);
                self::$collection = array_merge(self::$collection, BigPipeResource::getCollection(true));
            } else {
                $ret = false;
            }
            $parent_id = self::$_context['parent_id'];
            if(isset($parent_id)){
                self::$_context = self::$_contextMap[$parent_id];
                unset(self::$_contextMap[$parent_id]);
            } else {
                self::$_context = null;
            }
        }
        echo '</div>';
        return $ret;
    }

    static public function filter($ids) {
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $id) {
            self::$_filter[$id] = true;
        }
    }

    static public function getCollection() {
        if (self::$_mode === self::NO_SCRIPT) {
            return array_merge(self::$collection, BigPipeResource::getCollection());
        }
        return self::$collection;
    }

    static public function addScript($code) {
        if(self::$_context['hit'] || BigPipe::getMode() !== BigPipe::QUICKLING){
            $name = empty(self::$_context) ? 'page' : 'pagelet';
            self::$collection['script'][$name][] = $code;
        }
    }

    public static function cssHook(){
        return self::CSS_LINKS_HOOK;
    }

    public static function renderCss($strContent) {
        $intPos = strpos($strContent, self::CSS_LINKS_HOOK);
        if($intPos !== false){
            $strContent = substr_replace($strContent, self::render('css'), $intPos, strlen(self::CSS_LINKS_HOOK));
        }
        return $strContent;
    }

    public static function renderResponse($strContent){
        $strContent = self::display($strContent);
        BigPipeResource::reset();
        return $strContent;
    }

    public static function render($type){
        $arrStaticCollection = &self::$collection;
        $html = '';
        if ($type === 'js') {
            //require.resourceMap要在mod.js加载以后执行
            if (BigPipeResource::getFramework()) {
                $html .= '<script type="text/javascript" src="' . BigPipeResource::getFramework() . '"></script>' . PHP_EOL;
            }
            $resourceMap = BigPipeResource::getResourceMap();
            if ($resourceMap) {
                $html .= '<script type="text/javascript">';
                $html .= 'require.resourceMap('.$resourceMap.');';
                $html .= '</script>';
            }
        }
        if(!empty($arrStaticCollection['res'][$type])){
            $arrStatic = &$arrStaticCollection['res'][$type];

            if($type === 'js') {
                foreach ($arrStatic as $arrRes) {
                    if ($arrRes['uri'] === self::$framework) {
                        continue;
                    }
                    if (self::$_mode === self::NO_SCRIPT) {
                        $html .= '<script type="text/javascript" src="' . $arrRes['uri'] . '"></script>' . PHP_EOL;
                    } else if (self::$_mode === self::PIPE_LINE) {
                        $html .= '<script type="text/javascript">' . $arrRes['content'] . '</script>';
                    }
                }
            } else if($type === 'css'){
                foreach ($arrStatic as $arrRes) {
                    if (self::$_mode === self::NO_SCRIPT) {
                        $html .= '<link rel="stylesheet" type="text/css" href="'. $arrRes['uri'] . '" />';
                    } else if (self::$_mode === self::PIPE_LINE) {
                        $html .= '<style type="text/css">' . $arrRes['content'] . '</style>' . PHP_EOL;
                    }
                }
            }
        }
        return $html;
    }

    public static function addScriptPool($str){
        self::addScript($str);
    }

    public static function renderScriptPool(){
        $html='';
        self::display($html);
        return $html;
    }

    static public function display($html) {
        $mode = self::getMode();
        $collection = self::getCollection();

        $pagelets = self::getPagelets();
        $script = $collection['script'];
        unset($collection['script']);
        if (isset($script['pagelet'])) {
            $script['pagelet'] = implode("\n", $script['pagelet']);
        }
        if (isset($script['page'])) {
            $script['page'] = implode("\n", $script['page']);
        }
        if ($script) {
            $collection['res']['script'] = $script;
        }

        switch($mode){
            case self::NO_SCRIPT:
                $html = self::renderCss($html);
                foreach($collection['res']['js'] as $js){
                    $html .= '<script src="' . $js['uri'] . '" type="text/javascript"></script>';
                    $html .= "\n";
                }
                foreach($collection['res']['script'] as $code){
                    $html .= '<script type="text/javascript">!function(){';
                    $html .= implode($code, "}();!function() {");
                    $html .= '}();</script>';
                }
                break;
            case self::QUICKLING:
                header('Content-Type: text/json;');
                $html = json_encode(array(
                    'title' => BigPipe::getTitle(),
                    'pagelets' => $pagelets,
                    'script' => $script,
                    'resource_map' => $collection
                ));
                break;
            case self::PIPE_LINE:
                //$html = self::renderCss($html);
                $html .= '<script type="text/javascript">';
                $html .= "\n";
                if(isset($script)){
                    $html .= 'BigPipe.onPageReady(function(){';
                    if(isset($script['pagelet'])){
                        $html .= "\n";
                        $html .= $script['pagelet'];
                    }
                    if(isset($script['page'])){
                        $html .= "\n";
                        $html .= $script['page'];
                    }
                    $html .= '});';
                }
                $html .= '</script>';
                $html .= "\n";
                foreach($pagelets as $index => $pagelet){
                    $id = '__cnt_' . $index;
                    $html .= '<code style="display:none" id="' . $id . '"><!-- ';
                    $html .= str_replace(
                        array('\\', '-->'),
                        array('\\\\', '--\\>'),
                        $pagelet['html']
                    );
                    unset($pagelet['html']);
                    if (!$pagelet['script']) {
                        unset($pagelet['script']);
                    }
                    $pagelet['html_id'] = $id;
                    $html .= ' --></code>';
                    $html .= "\n";
                    $html .= '<script type="text/javascript">';
                    $html .= "\n";
                    $html .= 'BigPipe.onPageletArrived(';
                    $html .= json_encode($pagelet);
                    $html .= ');';
                    $html .= "\n";
                    $html .= '</script>';
                    $html .= "\n";
                }
                $html .= "\n";
                $html .= '<script type="text/javascript">';
                $html .= "\n";
                $html .= 'BigPipe.register(';
                if(empty($collection)){
                    $html .= '{}';
                } else {
                    $html .= json_encode($collection);
                }
                $html .= ');';
                $html .= "\n";
                $html .= '</script>';
                break;
        }
        return $html;
    }
}