<?php

function smarty_compiler_require($arrParams,  $smarty){
    $strName = $arrParams['name'];
    $strCode = '';
    if($strName){
        $strResourceApiPath = preg_replace('/[\\/\\\\]+/', '/', dirname(__FILE__) . '/BigPipe/BigPipeResource.php');
        $strCode .= '<?php if(!class_exists(\'BigPipe\')){require_once(\'' . $strResourceApiPath . '\');}';
        $strCode .= 'BigPipeResource::load(' . $strName . ',$_smarty_tpl->smarty);';
        $strCode .= '?>';
    }
    return $strCode;
}