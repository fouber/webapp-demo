<?php
function smarty_compiler_html($arrParams,  $smarty){
    $strResourceApiPath = preg_replace('/[\\/\\\\]+/', '/', dirname(__FILE__) . '/BigPipe/BigPipeResource.php');
    $strFramework = $arrParams['framework'];
    unset($arrParams['framework']);
    $strAttr = '';
    $strCode  = '<?php ';
    $strCode .= 'if(!class_exists(\'BigPipe\')){require_once(\'' . $strResourceApiPath . '\');}';
    $strCode .= 'BigPipe::init();';
    if (isset($strFramework)) {
        $strCode .= 'BigPipeResource::setFramework(BigPipeResource::getUri('.$strFramework.', $_smarty_tpl->smarty));';
    }
    $strCode .= ' ?>';
    foreach ($arrParams as $_key => $_value) {
        $strAttr .= ' ' . $_key . '="<?php echo ' . $_value . ';?>"';
    }
    return $strCode . "<html{$strAttr}>";
}

function smarty_compiler_htmlclose($arrParams,  $smarty){
    $strCode = '<?php ';
    $strCode .= '$_smarty_tpl->registerFilter(\'output\', array(\'BigPipe\', \'renderResponse\'));';
    $strCode .= '?>';
    $strCode .= '</html>';
    return $strCode;
}