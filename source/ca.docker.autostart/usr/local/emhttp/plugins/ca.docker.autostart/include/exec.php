<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");

$paths['settings'] = "/boot/config/plugins/ca.docker.autostart/settings.json";
$paths['settingsRAM'] = "/tmp/ca.docker.autostart/settings.json";
$paths['autostartFile'] = "/var/lib/docker/unraid-autostart";

$defaultDelay = 5;

function searchArray($array,$key,$value) {
  if ( function_exists("array_column") && function_exists("array_search") ) {   # faster to use built in if it works
    $result = array_search($value, array_column($array, $key));   
  } else {
    $result = false;
    for ($i = 0; $i <= max(array_keys($array)); $i++) {
      if ( $array[$i][$key] == $value ) {
        $result = $i;
        break;
      }
    }
  }
  return $result;
}

function getPost($setting,$default = false) {
  return isset($_POST[$setting]) ? urldecode(($_POST[$setting])) : $default;
}
function getPostArray($setting) {
  return $_POST[$setting];
}

function readJsonFile($filename) {
  return json_decode(@file_get_contents($filename),true);
}

function writeJsonFile($filename,$jsonArray) {
  file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
function resetOrder($list) {
  foreach ($list as $entry) {
    $newList[] = $entry;
  }
  return $newList;
}

function populate($selectedContainer = false) {
  global $paths, $defaultDelay;

  $networkINI = parse_ini_file("/usr/local/emhttp/state/network.ini",true);
  $defaultIP = $networkINI['eth0']['IPADDR:0'];
  $managedContainers = readJsonFile($paths['settingsRAM']);
  if ( ! $managedContainers ) {
    $managedContainers = array();
  }
  
  $DockerTemplates = new DockerTemplates();
  $info = $DockerTemplates->getAllInfo();
  $dockerContainers = array_keys($info);

  $available = "<br><table>";
  foreach ($dockerContainers as $container) {
    $flag = false;
    foreach ($managedContainers as $testContainer) {
      if ($testContainer['name'] == $container) {
        $flag = true;
        break;
      }
    }
    if ($flag) {
      continue;
    }
    $selected = ($container == $selectedContainer) ? "selectedContainer" : ""; 
    $available .= "<tr id=$container class='container unraid $selected' onclick='selectContainer(this.id);'>";
    $available .= "<td width=60px><img src=".$info[$container]['icon']." width=48px; height=48px></td>";
    $available .= "<td><strong>$container</strong></td>";
    $autostart = $info[$container]['autostart'] ? "checked" : "";
    $available .= "<td>";
    $available .= "<span id='autoText$container'>";
    $available .= $autostart ? "<font color='red'>Autostart ON</font>" : "Autostart OFF";
    $available .= "</span>";
    $available .= "<div class='autostart-switch-button-background' style='width:25px; height:11px;' onclick='changeunRaidAutoStart(this);'>";
    $class = $autostart ? "unRaidAutoButton" : "";
    $available .= "<div id='divButton$container' class='autostart-switch-button-button $class' style='width:12px; height:11px;'>";
    $available .= "</div></div>";
    $available .= "</tr>";
  }
  $available .= "</table>";
  $plgManage = "<br><table>";
  foreach ($managedContainers as $container) {
    $containerName = $container['name'];
    $container['delay'] = $container['delay'] ? $container['delay'] : $defaultDelay;
    $containerDelay = "value='".$container['delay']."'";
    $containerPort = $container['port'] ? "value='".$container['port']."'" : "";
    $containerIP = $container['IP'] ? $container['IP'] : $defaultIP;

    if ( ! $info[$containerName] ) {
      continue;
    }
    $selected = ($containerName == $selectedContainer) ? "selectedContainer" : "";
    $plgManage .= "<tr id=$containerName class='container managed $selected' onclick=selectContainer(this.id);>";
    $plgManage .= "<td width=60px><img src=".$info[$containerName]['icon']." width=48px; height=48px></td>";
    $plgManage .= "<td><strong>$containerName</strong></td>";
    $plgManage .= "<td><strong>IP</strong><input id='".$containerName."IP' type='text' style='width:80px;' value='$containerIP' onchange=changeDelay('$containerName');>";
    $plgManage .= "<strong>Port</strong> <input id='".$containerName."Port' placeholder='N/A' type='text' style='width:40px;' $containerPort onchange=changeDelay('$containerName');></td>";
    $plgManage .= "<td>Timeout Delay: <input id='".$containerName."Delay' type='text' placeholder='Delay In Seconds' style='width:40px' $containerDelay onchange=changeDelay('$containerName');></td>";
    $plgManage .= "</tr>";
  }
  $plgManage .= "</table>";
  
  $script = '<script>$("#available").html("'.$available.'");$("#managed").html("'.$plgManage.'");</script>';
  return $script;
}

switch ($_POST['action']) {
  case "initialize" :
    exec("mkdir -p /tmp/ca.docker.autostart");
    exec("mkdir -p /boot/config/plugins/ca.docker.autostart");
    $managedContainers = readJsonFile($paths['settings']);
    if ( ! $managedContainers ) {
      $managedContainers = array();
    }
    $DockerTemplates = new DockerTemplates();
    $info = $DockerTemplates->getAllInfo();
    foreach ($managedContainers as $container) {
      if ( $info[$container['name']] ) {
        $newManaged[] = $container;
      }
    }
    writeJsonFile($paths['settingsRAM'],$newManaged);
    echo populate();
    break;
    
  case "moveright":
    $managed = readJsonFile($paths['settingsRAM']);
    $container = getPost("container");
    $app['name'] = $container;
    $app['delay'] = $defaultDelay;
    $managed[] = $app;
    
    writeJsonFile($paths['settingsRAM'],$managed);
    echo populate($container);
    break;
  case "moveleft":
    $managed = readJsonFile($paths['settingsRAM']);
    $container = getPost("container");
    $indexes = array_keys($managed);
    foreach ($indexes as $index) {
      if ($managed[$index]['name'] == $container) {
        unset($managed[$index]);
        break;
      }
    }
    $managed = resetOrder($managed);
    writeJsonFile($paths['settingsRAM'],$managed);
    echo populate($container);
    break;
  case "moveup":
    $managed = readJsonFile($paths['settingsRAM']);
    $container = getPost("container");
    
    $index = searchArray($managed,"name",$container);
    if ($index === false) {
      break;
    }
    if ($index == 0) {
      return;
    }
    $temp1 = $managed[$index-1];
    $temp2 = $managed[$index];
    $managed[$index-1] = $temp2;
    $managed[$index] = $temp1;
    writeJsonFile($paths['settingsRAM'],$managed);
    echo populate($container);
    break;
  case "movedown":
    $managed = readJsonFile($paths['settingsRAM']);
    $container = getPost("container");
    
    $index = searchArray($managed,"name",$container);
    if ($index === false) {
      break;
    }
    if ($index == count($managed)-1) {
      return;
    }
    $temp1 = $managed[$index+1];
    $temp2 = $managed[$index];
    $managed[$index+1] = $temp2;
    $managed[$index] = $temp1;
    writeJsonFile($paths['settingsRAM'],$managed);
    echo populate($container);
    break;
  case "changeDelay":
    $managed = readJsonFile($paths['settingsRAM']);
    $container = trim(getPost("container"));
    $containerDelay = getPost("containerDelay");
    $containerPort = getPost("containerPort");
    $containerIP = getPost("containerIP");
    $containerDelay = intval($containerDelay);
    if ( ! $containerDelay ) {
      $containerDelay = 0;
    }
    if ( $containerDelay <=0 ) {
      $containerDelay = " ";
    }
    if ( $containerPort <= 0 ) {
      $containerPort = "";
    }
    
    $index = searchArray($managed,"name",$container);
    if ( $index === false) {
      break;
    }
    $managed[$index]['delay'] = $containerDelay;
    $managed[$index]['port'] = $containerPort;
    $managed[$index]['IP'] = $containerIP;
    writeJsonFile($paths['settingsRAM'],$managed);
    echo $containerDelay;
    break;
  case "apply":
    $unRaidAutostart = getPostArray("autostart");
    $managed = readJsonFile($paths['settingsRAM']);
    if ( ! is_array($managed) ) {
      $managed = array();
    }
    writeJsonFile($paths['settings'],$managed);

    $autostart = explode("\n",@file_get_contents($paths['autostartFile']));
    foreach ($managed as $container) {
      $index = array_search($container['name'],$autostart);
      if ( $index === false ) {
        continue;
      }
      unset($autostart[$index]);
    }
    file_put_contents($paths['autostartFile'],implode("\n",$autostart));
    if ( ! is_array($unRaidAutostart) ) {
			$unRaidAutostart = array();
		}
    unset($autostartFile);
    foreach ($unRaidAutostart as $unRaidContainer) {
      $count = 1;
      if ( $unRaidContainer[1] == "true" ) {
        $autostartFile .= str_replace("Check","",$unRaidContainer[0],$count)."\n";
      }
    }
    file_put_contents("/var/lib/docker/unraid-autostart",$autostartFile);
    echo " ";
    break;
}
?>