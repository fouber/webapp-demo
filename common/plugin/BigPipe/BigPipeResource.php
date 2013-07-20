<?php

class BigPipeResource {
    private static $arrMap = array();
    private static $arrLoaded = array();
    private static $arrStaticCollection = array();
    //收集require.async组件
    private static $arrRequireAsyncCollection = array();
    private static $arrScriptPool = array();

    public static $framework = null;

    public static function reset(){
        self::$arrMap = array();
        self::$arrLoaded = array();
        self::$arrStaticCollection = array();
        self::$arrScriptPool = array();
        self::$framework  = null;
    }

    public static function getCollection($clean = false) {
        $ret = self::$arrStaticCollection;
        if ($clean) {
            self::$arrStaticCollection = array();
        }
        return $ret;
    }

    public static function setFramework($strFramework) {
        self::$framework = $strFramework;
    }

    public static function getFramework() {
        return self::$framework;
    }

    public static function getUri($strName, $smarty) {
        $intPos = strpos($strName, ':');
        if($intPos === false){
            $strNamespace = '__global__';
        } else {
            $strNamespace = substr($strName, 0, $intPos);
        }
        if(isset(self::$arrMap[$strNamespace]) || self::register($strNamespace, $smarty)) {
            $arrMap = &self::$arrMap[$strNamespace];
            $arrRes = &$arrMap['res'][$strName];
            if (isset($arrRes)) {
                return $arrRes['uri'];
            }
        }
    }

    public static function getTemplate($strName, $smarty) {
        //绝对路径
        return $smarty->joined_template_dir . str_replace('/template', '', self::getUri($strName, $smarty));
    }



    public static function getResourceMap() {
        $ret = '';
        if (self::$arrRequireAsyncCollection) {
            $arrResourceMap = array();
            foreach (self::$arrRequireAsyncCollection as $id => $arrRes) {
                $deps = array();
                if (!empty($arrRes['deps'])) {
                    foreach ($arrRes['deps'] as $strName) {
                        if ($arrRes['type'] !== 'css') {
                            continue;
                        }
                        $deps[] = $strName;
                    }
                }
                $arrResourceMap['res'][$id] = array(
                    'url' => $arrRes['uri'],
                    'deps' => $deps
                );
            }
            $ret = str_replace('\\/', '/', json_encode($arrResourceMap));
        }
        return  $ret;
    }

    public static function register($strNamespace, $smarty){
        if($strNamespace === '__global__'){
            $strMapName = 'map.json';
        } else {
            $strMapName = $strNamespace . '-map.json';
        }
        $arrConfigDir = $smarty->getConfigDir();
        foreach ($arrConfigDir as $strDir) {
            $strPath = preg_replace('/[\\/\\\\]+/', '/', $strDir . '/' . $strMapName);
            if(is_file($strPath)){
                self::$arrMap[$strNamespace] = json_decode(file_get_contents($strPath), true);
                return true;
            }
        }
        return false;
    }

    /**
     * 分析组件依赖
     * @param array $arrRes  组件信息
     * @param Object $smarty  smarty对象
     * @param bool $async   是否异步
     */
    private static function loadDeps($arrRes, $smarty, $async) {
        //require.async
        if (isset($arrRes['extras']) && isset($arrRes['extras']['async'])) {
            foreach ($arrRes['extras']['async'] as $uri) {
                self::load($uri, $smarty, true);
            }
        }
        if(isset($arrRes['deps'])){
            foreach ($arrRes['deps'] as $strDep) {
                self::load($strDep, $smarty, $async);
            }
        }
    }

    /**
     * 已经分析到的组件在后续被同步使用时在异步组里删除。
     * @param $strName
     */
    private static function delAsyncDeps($strName) {
        $arrRes = self::$arrRequireAsyncCollection[$strName];
        if ($arrRes['deps']) {
            foreach ($arrRes['deps'] as $strDep) {
                if (isset(self::$arrRequireAsyncCollection[$strDep])) {
                    self::delAsyncDeps($strDep);
                }
            }
        }
        //已经分析过的并且在其他文件里同步加载的组件，重新收集在同步输出组
        self::$arrStaticCollection['js'][] = self::$arrRequireAsyncCollection[$strName]['uri'];
        unset(self::$arrRequireAsyncCollection[$strName]);
    }

    /**
     * 加载组件以及组件依赖
     * @param $strName      id
     * @param $smarty       smarty对象
     * @param bool $async   是否为异步组件（only JS）
     * @return mixed
     */
    public static function load($strName, $smarty, $async = false){
        if(isset(self::$arrLoaded[$strName])) {
            //同步组件优先级比异步组件高
            if (!$async && isset(self::$arrRequireAsyncCollection[$strName])) {
                self::delAsyncDeps($strName);
            }
            return self::$arrLoaded[$strName];
        } else {
            $intPos = strpos($strName, ':');
            if($intPos === false){
                $strNamespace = '__global__';
            } else {
                $strNamespace = substr($strName, 0, $intPos);
            }
            if(isset(self::$arrMap[$strNamespace]) || self::register($strNamespace, $smarty)){
                $arrMap = &self::$arrMap[$strNamespace];
                $arrRes = &$arrMap['res'][$strName];
                if(isset($arrRes)) {
                    $type = $arrRes['type'];
                    $isStatic = ($type == 'js' || $type == 'css');
                    unset($arrRes['type']);
                    if(isset($arrRes['pkg'])){
                        $arrPkg = &$arrMap['pkg'][$arrRes['pkg']];
                        $strURI = $arrPkg['uri'];
                        foreach ($arrPkg['has'] as $strResId) {
                            self::$arrLoaded[$strName] = $strURI;
                        }
                        self::$arrStaticCollection['pkg'][$arrRes['pkg']] = $arrPkg;
                        foreach ($arrPkg['has'] as $strResId) {
                            $arrHasRes = &$arrMap[$strResId];
                            if ($arrHasRes) {
                                self::loadDeps($arrHasRes, $smarty, $async);
                            }
                        }
                    } else {
                        $strURI = $arrRes['uri'];
                        self::$arrLoaded[$strName] = $strURI;
                        self::loadDeps($arrRes, $smarty, $async);
                    }
                    if ($isStatic) {
                        if (BigPipe::getMode() === BigPipe::PIPE_LINE
                            && isset($_COOKIE['FIS_PAGE_LOADED_FLAG'])) {
                            unset($arrRes['content']);
                        } else if (BigPipe::getMode() === BigPipe::QUICKLING) {
                            unset($arrRes['content']);
                        }
                        if (BigPipe::getMode() !== BigPipe::NO_SCRIPT) {
                            unset($arrRes['uri']);
                        }
                        if ($async && $arrRes['type'] === 'js') {
                            self::$arrRequireAsyncCollection[$strName] = $arrRes;
                        } else {
                            $arrRes['id'] = $strName;
                            self::$arrStaticCollection['res'][$type][] = $arrRes;
                        }
                    }
                    return $strURI;
                } else {
                    self::triggerError($strName, 'undefined resource "' . $strName . '"', E_USER_NOTICE);
                }
            } else {
                self::triggerError($strName, 'missing map file of "' . $strNamespace . '"', E_USER_NOTICE);
            }
        }
        self::triggerError($strName, 'unknown resource load error', E_USER_NOTICE);
    }

    /**
     * 用户代码自定义js组件，其没有对应的文件
     * 只有有后缀的组件找不到时进行报错
     * @param $strName       组件ID
     * @param $strMessage    错误信息
     * @param $errorLevel    错误level
     */
    private static function triggerError($strName, $strMessage, $errorLevel) {
        $arrExt = array(
            'js',
            'css',
            'tpl',
            'html',
            'xhtml',
        );
        if (preg_match('/\.('.implode('|', $arrExt).')$/', $strName)) {
            trigger_error(date('Y-m-d H:i:s') . '   ' . $strMessage, $errorLevel);
        }
    }
}