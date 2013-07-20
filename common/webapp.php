<?php
function getResourceMap($smarty) {
    if (!preg_match('/^\/webapp\.php/i', $_SERVER['REQUEST_URI'])) return;
    require_once(dirname(__FILE__) . '/BigPipe/BigPipe.php');

    BigPipe::setMode(BigPipe::PIPE_LINE);

    $g_ids = isset($_POST['ids']) ? $_POST['ids'] : $_GET['ids'];
    $g_response = array(
        'code' => 0,
        'res'  => array()
    );
    if (empty($g_ids)) {
        header('Content-Type: text/json');
        echo json_encode($g_response);
        exit();
    }

    if (!is_array($g_ids)) {
        $g_ids = array($g_ids);
    }

    foreach ($g_ids as $id) {
        BigPipeResource::load($id, $smarty);
    }

    $static = BigPipeResource::getCollection();
    $arr_res = $static['res'];
    
    for ($i = 0, $len = count($arr_res); $i < $len && $arr_res[$i]; $i++) {
        unset($arr_res[$i]['uri']);
    }

    $g_response['res'] = $arr_res;

    header('Content-Type: text/json');
    echo json_encode($g_response);
    exit();
}
