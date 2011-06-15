<html>
<head>
<title>STATUS narno.net</title>
<style>
li.graph {
    list-style-type: none;
}
</style>
</head>
<body>
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
//$config = new Zend_Config_Xml('config.xml', 'production');
$cache_config = Zend_Cache::factory(
	'Output',
	'File',
	array(
		'lifetime' => null,
		'automatic_serialization' => true,
	),
	array(
		'cache_dir' => '../cache/',
		'file_name_prefix' => 'config',
	)
);
if (($config = $cache_config->load('globale')) === false) {
    $config = new Zend_Config_Xml('config.xml', 'production');
    $cache_config->save($config, 'globale');
}

// logger
$logger = new Zend_Log();
$writer = new Zend_Log_Writer_Stream('../logs/' . $config->webhost . '.log');
$logger->addWriter($writer);

// cache
$frontendOptions = array(
    'debug_header' => false,
    'caching' => true,
    'lifetime' => (int)$config->cache->page->lifetime,
    'logging' => true,
    'logger' => $logger,
    'ignore_user_abort' => true,
    'default_options' => array(
        'cache' => true,
        'make_id_with_get_variables' => false,
        'make_id_with_post_variables' => false,
        'make_id_with_session_variables' => false,
        'make_id_with_files_variables' => false,
        'make_id_with_cookie_variables' => false,
    ),
    'regexps' => array(
		'^.*$' => array('cache' => false),
		'^/$' => array('cache' => true),
	)
);
$backendOptions = array(
    'cache_dir' => $config->cache->dir,
    'file_name_prefix' => $config->cache->page->prefix,
    'cache_file_umask' => 0777,
);

// DEBUG
//Zend_Debug::dump($frontendOptions);

try {
    if (!is_dir($config->cache->dir)) {
        mkdir($config->cache->dir, 0777);
    }
    $cache = Zend_Cache::factory('Page', 'File', $frontendOptions, $backendOptions);
    //$cache->clean(Zend_Cache::CLEANING_MODE_OLD);
    $cache->start();
    $logger->log('start cache', Zend_Log::DEBUG);
    // DEBUG
    //Zend_Debug::dump($cache);
    //$cache->clean();
}
catch (Zend_Cache_Exception $e) {
    echo 'Exception: ' . $e->getCode() . "\n" . $e->__toString();
    exit();
}

// GANDI XML-RPC
$client = new Zend_XmlRpc_Client($config->gandi->api);
$client->setSkipSystemLookup(true);
try {
    $account  = $client->call('account.info', array($config->gandi->apikey));
    $vm_list  = $client->call('vm.list', array($config->gandi->apikey));
}
catch (Zend_XmlRpc_Client_FaultException $e) {
    echo 'Exception: ' . $e->getCode() . "\n" . $e->getMessage();
    exit();
}
catch (Zend_XmlRpc_Client_HttpException $e) {
    echo 'Exception: ' . $e->getCode() . "\n" . $e->getMessage();
    exit();
}

// DEBUG
//Zend_Debug::dump($account);
//Zend_Debug::dump($account['resources']['available']);
//Zend_Debug::dump($vm_list);

// account
if ($config->gandi->account->showdetails) {
    $fullname = $account['fullname'];
    $resGranted = array(
        'servers'   => $account['resources']["granted"]["servers"],
        'ips'       => $account['resources']["granted"]["ips"],
        'bandwidth' => $account['resources']["granted"]["bandwidth"],
        'memory'    => $account['resources']["granted"]["memory"],
        'cores'     => $account['resources']["granted"]["cores"],
        'disk'      => $account['resources']["granted"]["disk"],
    );
?>
<h1><?php echo $fullname; ?> on Gandi.net</h1>
<ul class="res-granted">
    <li><?php echo $resGranted['servers']; ?> server(s)</li>
    <li><?php echo $resGranted['ips']; ?> IP(s)</li>
    <li><?php echo $resGranted['bandwidth']; ?>Kbps of bandwidth</li>
    <li><?php echo $resGranted['memory']; ?>MB of memory</li>
    <li><?php echo $resGranted['cores']; ?> core(s)</li>
    <li><?php echo $resGranted['disk']; ?>MB of disk</li>
</ul>
<?php
}
?>
<h2>VM(s):</h2>
<ol class="vm-list">
<?php
foreach ($vm_list as $key => $vm) {
    //Zend_Debug::dump($vm); // DEBUG
    $vm_info = $client->call('vm.info', array($config->gandi->apikey, (int)$vm['id']));
    $hostname     = $vm_info['hostname'];
    $state        = $vm_info['state'];
    $graphCPU     = $vm_info['graph_urls']['vcpu'][0];
    $graphNetwork = $vm_info['graph_urls']['vif'][0];
    $graphHDD     = $vm_info['graph_urls']['vdi'][1];
    $date_created = new Zend_Date($vm['date_created'], Zend_Date::ISO_8601);
    $date_updated = new Zend_Date($vm['date_updated'], Zend_Date::ISO_8601);
?>
    <li class="vm-<?php echo $key; ?>">
        <h3><?php echo $hostname; ?> <em>is <?php echo $state; ?>...</em></h3>
        <ul>
<?php if ($config->gandi->vm->showdetails): ?>
<?php if ($vm['description']): ?><li>Description: <?php echo $vm['description']; ?></li><?php endif; ?>
            <li>Date created: <?php echo $date_created->toString('yyyy-MM-dd');; ?></li>
            <li>Date updated: <?php echo $date_updated->toString('yyyy-MM-dd'); ?></li>
            <li>Console: <?php echo ($vm['console']) ? 'enabled' : 'disabled'; ?></li>
            <li>Gandi AI: <?php echo ($vm['ai_active']) ? 'enabled' : 'disabled'; ?></li>
            <li>Core(s): <?php echo $vm['cores']; ?></li>
            <li>Memory: <?php echo $vm['memory']; ?> MB</li>
            <li>Max memory: <?php echo $vm['vm_max_memory']; ?> MB</li>
<?php if ($vm['flex_shares']): ?>
            <li>Flex shares: <?php echo $vm['flex_shares']; ?></li>
<?php endif; ?>
<?php endif; ?>
            <li class="graph"><img src="graph.php?url=<?php echo urlencode($graphCPU); ?>" alt="CPU" /></li>
            <li class="graph"><img src="graph.php?url=<?php echo urlencode($graphNetwork); ?>" alt="Network" /></li>
            <li class="graph"><img src="graph.php?url=<?php echo urlencode($graphHDD); ?>" alt="HDD" /></li>
        </ul>
    </li>
<?php
} // foreach
?>
</ol>
<p><em>Powered by <a href="http://doc.rpc.gandi.net/">Gandi Hosting API</a> and <a href="http://narno.com">Arnaud Ligny</a></em></p>
</body> 
</html>