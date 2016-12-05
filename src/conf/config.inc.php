<?php

date_default_timezone_set('UTC');

$APP_DIR = dirname(dirname(__FILE__));
$LIB_DIR = $APP_DIR . '/lib';

$CONFIG = parse_ini_file($APP_DIR . '/conf/config.ini');
$CONFIG = array_merge($CONFIG, $_SERVER, $_ENV);

$MOUNT_PATH = $CONFIG['MOUNT_PATH'];
$EDGE_HOST = $CONFIG['EDGE_HOST'];
$EDGE_PORT = $CONFIG['EDGE_PORT'];
$EDGE_TIMEOUT = $CONFIG['EDGE_TIMEOUT'];
$EDGE_WS_VERSION = '{{VERSION}}';


// build absolute URL string
$server_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'Off') ?
    'https://' : 'http://';
$server_host = isset($_SERVER['HTTP_HOST']) ?
    $_SERVER['HTTP_HOST'] : 'geomag.usgs.gov';
$server_port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
$server_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$HOST_URL_PREFIX = $server_protocol . $server_host;
if (($server_protocol == 'http://' && $server_port !== 80) ||
    ($server_protocol == 'https://' && $server_port !== 443)) {
  // using non-standard port
  if(!strpos($HOST_URL_PREFIX, ':')) {
    // and HOST_URL_PREFIX doesn't already include port
    $HOST_URL_PREFIX .= ':' . $server_port;
  }
}
