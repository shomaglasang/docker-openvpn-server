<?php

function open_db($db_ip, $db_user, $db_password, $db_name, $db_port = "3306")
{
  $mysqli = new mysqli($db_ip, $db_user, $db_password, $db_name, $db_port);
  if ($mysqli->connect_errno) {
    echo "Error connecting to DB:" . $mysqli->connect_error;
    exit;
  }

  return($mysqli);
}


function close_db($mysqli)
{
  $mysqli->close();
}


function get_vpn_clients($customer_id = null)
{
  $vpn_servers = get_vpn_servers($customer_id);
  $vpn_clients = array();

  foreach ($vpn_servers as $server)
  {
    $vpn_status_log = $server['vpn_server_status_log'];
    if (empty($vpn_status_log))
    {
      continue;
    }

    $fd = fopen($vpn_status_log, "r");
    if (!$fd)
    {
      echo "Failed to open file\n";
      continue;
    }

    $found = false;
    while (($line = fgets($fd)) !== false)
    {
      $a = explode(",", $line);
      if (empty($a))
      {
        continue;
      }

      if (!$found)
      {
        if ($a[0] == 'Virtual Address')
        {
          $found = true;
        }

        continue;
      }

      if (count($a) != 4)
      {
        break;
      }

      $h = array("ip" => $a[0],
        "common_name" => $a[1],
        "public_ip" => explode(":", $a[2])[0],
        "customer_id" => $server['id'],
        "customer_name" => $server['name']
      );

      $key = $a[1] . '-' . $a[0];
      $vpn_clients[$key] = $h;
    }

    fclose($fd);
  }

  return($vpn_clients);
}


function get_vpn_servers($customer_id = null)
{
  global $mysqli;
  $servers = array();

  $sql = "select * from customers where deactivated_at is null";
  if (!empty($customer_id))
  {
    $sql .= " and id=$customer_id";
  }

  $sql .= " order by common_name";

  if ($result = $mysqli->query($sql))
  {
    while ($row = $result->fetch_assoc())
    {
      $servers[] = array(
        'id' => $row['id'],
        'name' => $row['name'],
        'common_name' => $row['common_name'],
        'description' => $row['description'],
        'vpn_server_dir' => $row['vpn_server_dir'],
        'vpn_server_config' => $row['vpn_server_config'],
        'vpn_server_status_log' => $row['vpn_server_status_log'],
        'vpn_server_port' => $row['server_port']
      );
    }
  }

  return($servers);
}


function get_client_info($device_cn, $customer_id)
{
  global $mysqli;
  $info = array();

  $sql = "select cl.*,c.common_name as customer_cn,c.name as customer_name,c.ca_dir " .
    "from clients cl join customers c on c.id=cl.customer_id where cl.common_name='" . $device_cn . "' and c.id=" . $customer_id;
  $result = $mysqli->query($sql);
  if ($result && ($row = $result->fetch_assoc()))
  {
    $info['type'] = $row['type'];
    $info['name'] = $row['name'];
    $info['description'] = $row['description'];
    $info['status'] = $row['status'];
    $info['customer_cn'] = $row['customer_cn'];
    $info['customer_name'] = $row['customer_name'];
    $info['cert_expiry'] = $row['expiry'];
  }

  return($info);
}


function update_client_info($device_cn, $customer_id, $desc)
{
  global $mysqli;

  $sql = sprintf("update clients set description='%s' where customer_id=%d and common_name='%s'",
    $desc, $customer_id, $device_cn);

  $result = $mysqli->query($sql);
  return(($result) ? true: false);
}

function get_vpn_client_servers($clients)
{
  $servers = array();

  foreach($clients as $client)
  {
    if (array_key_exists($client['customer_id'], $servers))
    {
      continue;
    }

    $servers[$client['customer_id']] = array(
      'id' => $client['customer_id'],
      'name' => $client['customer_name']
    );
  }

  return($servers);
}

?>
