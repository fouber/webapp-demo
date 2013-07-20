<?php
class fis_widget_map {

    private static $arrCached = array();

    public static function lookup(&$strFilename, &$smarty){
        $strPath = self::$arrCached[$strFilename];
        if(isset($strPath)){
            return $strPath;
        } else {
            $arrConfigDir = $smarty->getConfigDir();
            foreach ($arrConfigDir as $strDir) {
                $strPath = preg_replace('/[\\/\\\\]+/', '/', $strDir . '/' . $strFilename);
                if(is_file($strPath)){
                    self::$arrCached[$strFilename] = $strPath;
                    return $strPath;
                }
            }
        }
        trigger_error('missing map file "' . $strFilename . '"', E_USER_ERROR);
    }
}

function smarty_compiler_widget($arrParams,  $smarty){
    $strResourceApiPath = preg_replace('/[\\/\\\\]+/', '/', dirname(__FILE__) . '/BigPipe/BigPipe.php');
    $strCode = '<?php if(!class_exists(\'BigPipe\')){require_once(\'' . $strResourceApiPath . '\');}';
    $strCall = $arrParams['call'];
    $bHasCall = isset($strCall);
    $strName = $arrParams['name'];
    unset($arrParams['name']);
    $arrSubParams = array();
    foreach ($arrParams as $_key => $_value) {
        if (is_int($_key)) {
            $arrFuncParams[] = "$_key=>$_value";
        } else {
            $arrFuncParams[] = "'$_key'=>$_value";
        }
    }
    $arrSubParams = 'array(' . implode(',', $arrSubParams) . ')';
    $strCode .= '$hit=BigPipe::start('.$strName.');';
    $strCode .= 'if ($hit) {';
    if($bHasCall){
        unset($arrParams['call']);
        $strTplFuncName = '\'smarty_template_function_\'.' . $strCall;
        $strCallTplFunc = 'call_user_func('. $strTplFuncName . ',$_smarty_tpl,' . $arrSubParams . ');';

        $strCode .= 'if(is_callable('. $strTplFuncName . ')){';
        $strCode .= $strCallTplFunc;
        $strCode .= '}else{';
    }
    if($strName){
        $name = trim($strName, '\'" ');
        $strCode .= '$_tpl_path=BigPipeResource::load(' . $strName . ',$_smarty_tpl->smarty);';
        $strCode .= 'if(isset($_tpl_path)){';
        if($bHasCall){
            $strCode .= '$_smarty_tpl->getSubTemplate($_tpl_path, $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, $_smarty_tpl->caching, $_smarty_tpl->cache_lifetime, array(), Smarty::SCOPE_LOCAL);';
            $strCode .= 'if(is_callable('. $strTplFuncName . ')){';
            $strCode .= $strCallTplFunc;
            $strCode .= '}else{';
            $strCode .= 'trigger_error(\'missing function define "\'.' . $strTplFuncName . '.\'" in tpl "\'.$_tpl_path.\'"\', E_USER_ERROR);';
            $strCode .= '}';
        } else {
            $strCode .= 'echo $_smarty_tpl->getSubTemplate($_tpl_path, $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, $_smarty_tpl->caching, $_smarty_tpl->cache_lifetime, '.$arrSubParams.', Smarty::SCOPE_LOCAL);';
        }
        $strCode .= '}else{';
        $strCode .= 'trigger_error(\'unable to locale resource "\'.' . $strName . '.\'"\', E_USER_ERROR);';
        $strCode .= '}';
    } else {
        trigger_error('undefined widget name in file "' . $smarty->_current_file . '"', E_USER_ERROR);
    }
    if($bHasCall){
        $strCode .= '}';
    }
    $strCode .= '}';
    $strCode .= 'BigPipe::end();';
    $strCode .= '?>';
    return $strCode;
}
