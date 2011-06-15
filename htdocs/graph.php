<?php
define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);
define('BP', dirname(dirname(dirname(dirname(__FILE__))))); // ../../../

// Zend Framework
$paths[] = BP . DS . 'zf/library';
$original_include_path = get_include_path();
$appPath = implode(PS, $paths);
set_include_path($appPath . PS . $original_include_path);
require 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

// config
$config = new Zend_Config_Xml('config.xml', 'production');

$frontendOptions = array(
    'debug_header' => false,
    'caching' => true,
    'lifetime' => (int)$config->cache->graph->lifetime,
);
$backendOptions = array(
    'cache_dir' => $config->cache->dir,
    'file_name_prefix' => $config->cache->graph->prefix,
    'cache_file_umask' => 0777,
);
$cache = Zend_Cache::factory('Output', 'File', $frontendOptions, $backendOptions);

header('Content-Type: image/png');
$url = $_GET['url'];
$http = new Zend_Http_Client($url);
$http->setConfig(array('timeout' => 5));
$http->setHeaders('User-Agent', $_SERVER['HTTP_USER_AGENT']);
$http->setHeaders('Referer', 'https://' . $http->getUri()->getHost() . '/');
$response = $http->request('GET');
$headers = $response->getHeaders();
if ($response->isSuccessful() !== false && $headers['Content-type'] == 'image/png') {
    $img = $response->getRawBody();
    $cacheID = md5($url);
    if (!($cache->start($cacheID))) {
       imagepng(imagecreatefromstring($img));
       $cache->end();
    }
}
else {
    $img = imagecreatefrompng('./graph_unavailable.png');
    //$cacheID = 'graph_unavailable';
    $cacheID = md5($url);
    if (!($cache->start($cacheID))) {
       imagepng($img);
       $cache->end();
    }
}