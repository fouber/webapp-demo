<?php
class Route {
    const REDIRECT = 1;
    const REWRITE = 2;

    static private $uri = null;
    static private $rules = array();
    private static $MIME = array(
        'bmp' => 'image/bmp',
        'css' => 'text/css',
        'doc' => 'application/msword',
        'dtd' => 'text/xml',
        'gif' => 'image/gif',
        'hta' => 'application/hta',
        'htc' => 'text/x-component',
        'htm' => 'text/html',
        'html' => 'text/html',
        'xhtml' => 'text/html',
        'ico' => 'image/x-icon',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'mocha' => 'text/javascript',
        'mp3' => 'audio/mp3',
        'mp4' => 'video/mpeg4',
        'mpeg' => 'video/mpg',
        'mpg' => 'video/mpg',
        'manifest' => 'text/cache-manifest',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'ppt' => 'application/vnd.ms-powerpoint',
        'rmvb' => 'application/vnd.rn-realmedia-vbr',
        'rm' => 'application/vnd.rn-realmedia',
        'rtf' => 'application/msword',
        'svg' => 'image/svg+xml',
        'swf' => 'application/x-shockwave-flash',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'txt' => 'text/plain',
        'vml' => 'text/xml',
        'vxml' => 'text/xml',
        'wav' => 'audio/wav',
        'wma' => 'audio/x-ms-wma',
        'wmv' => 'video/x-ms-wmv',
        'xml' => 'text/xml',
        'xls' => 'application/vnd.ms-excel',
        'xq' => 'text/xml',
        'xql' => 'text/xml',
        'xquery' => 'text/xml',
        'xsd' => 'text/xml',
        'xsl' => 'text/xml',
        'xslt' => 'text/xml'
    );


    public static function start() {
        $uri = $_SERVER['REQUEST_URI'];
        $intPos = strpos($uri, '?');
        if ($intPos !== false) {
            $uri = substr($uri, 0, $intPos);
        }
        self::$uri = $uri;
    }

    public static function rule($rule, $uri, $redirect = false) {
        self::$rules[$rule] = array(
            'uri' => $uri,
            'type' => $redirect ? self::REDIRECT : self::REWRITE
        );
    }

    public static function cycle() {
        foreach (self::$rules as $rule => $info) {
            if ($info['type'] == self::REDIRECT) {
                if (preg_match($rule, self::$uri)) {
                    header('Location: ' . $info['uri']);
                    exit();
                }
            }
            if ($file = self::hit($rule, $info['uri'])) {
                if (preg_match('/\.php$/', $file)) {
                    require_once($file);
                } else {
                    $info = pathinfo($file);
                    header('Content-Type: ' . self::$MIME[$info['extension']]);
                    echo file_get_contents($file);
                }
                exit();
            }
        }
        if (self::isStatic(self::$uri)) {
            header('Status: 404 File Not Found!');
            exit();
        }
    }

    public static function isStatic($url) {
        $ret = false;
        if (preg_match('/\.([a-z]{1,6})$/i', $url, $m)) {
            if ($m[1]) {
                $ret = in_array($m[1], array_keys(self::$MIME));
            }
        }
        return $ret;
    }

    public static function userRule() {
        $conf = dirname(__FILE__) . '/server.conf';
        if (is_file($conf)) {
            $tokens = explode("\n", file_get_contents($conf));
            foreach ($tokens as $token) {
                $arrRule = preg_split('/\s+/i', $token);
                $redirect = ($arrRule[0] == 'redirect');
                self::rule('/'.$arrRule[1].'/i', $arrRule[2], $redirect);
            }
        }
    }

    private static function hit($rule, $uri) {
        if (preg_match($rule, self::$uri, $m)) {
            unset($m[0]);
            foreach ($m as $k => $v) {
                $m['$'. $k] = $v;
            }
            return realpath(dirname(__FILE__) . '/' . str_replace(array_keys($m), array_values($m), self::$uri));
        }
        return false;
    }
}

Route::start();

Route::rule('/\/static\/(.*)/i', '/static/$1');
Route::userRule();
Route::cycle();

require('render.php');

$wp_render = new WPRender();

if (preg_match('/^\/webapp\.php/i', $_SERVER['REQUEST_URI'])) {
    require_once('webapp.php');
    getResourceMap($wp_render->getEngine());
}

$uri = $_SERVER['REQUEST_URI'];
if (strpos($uri, '?') !== false) $uri = substr($uri, 0, strpos($uri, '?'));
$token = explode('/', preg_replace('/\/$/', '', substr($uri, 1)));

$tpl = implode('/', $token) . '.tpl';

$wp_render->display($tpl);

?>
