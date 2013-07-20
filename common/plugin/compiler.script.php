<?php

function smarty_compiler_script($params,  $smarty){
    $strCode = '<?php ob_start(); ?>';
    return $strCode;
}

function smarty_compiler_scriptclose($params,  $smarty){
    $strResourceApiPath = preg_replace('/[\\/\\\\]+/', '/', dirname(__FILE__) . '/BigPipe/BigPipe.php');
    $strCode  = '<?php ';
    $strCode .= '$script=ob_get_clean();';
    $strCode .= 'if($script!==false){';
    $strCode .= 'if(!class_exists(\'BigPipeResource\')){require_once(\'' . $strResourceApiPath . '\');}';
    $strCode .= 'BigPipe::addScriptPool($script);';
    $strCode .= '}?>';
    return $strCode;
}