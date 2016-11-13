#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");

function logger($string) {
  $string = escapeshellarg($string);
  shell_exec("logger $string");
}

################################################################

if ( ! is_file("/boot/config/plugins/ca.docker.autostart/settings.json") ) {
  return;
}
$managed = json_decode(file_get_contents("/boot/config/plugins/ca.docker.autostart/settings.json"),true);
$DockerTemplates = new DockerTemplates();
$info = $DockerTemplates->getAllInfo();

logger("CA Docker Autostart Manager Starting");

foreach ($managed as $container) {
  $containerName = $container['name'];
  $containerDelay = $container['delay'];
  if ( $info[$containerName]['autostart'] ) {
    logger("$containerName set to autostart via unRaid.  Skipping autostart");
    continue;
  }
  if ( ! $info[$containerName] ) {
    logger("$containerName no longer installed.  Skipping autostart");
    continue;
  }
  logger("$containerDelay seconds sleep before starting $containerName");
  sleep($containerDelay);
  logger("Starting $containerName");
  exec("docker start $containerName");
}
logger("CA Docker Autostart Manager Finished");
?>

