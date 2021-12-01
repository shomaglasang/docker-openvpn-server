<?php

include_once('config.inc');
include_once('lib.inc');

$mysqli = open_db($db_ip, $db_user, $db_password, $db_name);
$customer_id = (isset($_GET['customer_id']) && $_GET['customer_id'] != 'all') ? $_GET['customer_id'] : null;
$devices_page_num = (isset($_GET['devices_page_num'])) ? intval($_GET['devices_page_num']) : 1;

$devices = array();
$users = array();
$servers = array();

$vpn_clients = get_vpn_clients($customer_id);

foreach($vpn_clients as $vpn_client)
{
  $client_info = get_client_info($vpn_client['common_name'], $vpn_client['customer_id']);
  $h = array_merge($vpn_client, $client_info);
  switch ($client_info['type'])
  {
    case 'user':
      $users[] = $h;
      break;

    case 'device':
      $devices[] = $h;
      break;

    case 'server':
      $servers[] = $h;
      break;
  }
}

$total_devices = count($devices);
$total_users = count($users);
$total_servers = count($servers);

if ($devices_page_num > 1)
{
  $rem_front = ($devices_page_num -1) * $max_devices_per_page;
  $devices = array_slice($devices, $rem_front);
}

if (count($devices) > $max_devices_per_page)
{
  $devices = array_slice($devices, 0, $max_devices_per_page);
}

echo json_encode(array(
  'devices' => $devices,
  'total_devices' => $total_devices,
  'devices_per_page' => $max_devices_per_page,
  'devices_page_num' => $devices_page_num,
  'users' => $users,
  'total_users' => $total_users,
  'users_per_page' => $max_users_per_page,
  'users_page_num' => 1,
  'servers' => $servers,
  'total_servers' => $total_servers,
  'servers_per_page' => $max_servers_per_page,
  'servers_page_num' => 1
));

close_db($mysqli);
exit();
?>
