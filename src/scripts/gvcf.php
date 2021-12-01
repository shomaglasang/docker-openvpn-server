#!/usr/local/bin/php
<?php

/*
 * Generate OpenVPN configration file for device and user.
 * - generate certificate (private and public)
 * - generate ovpn client configuration file
 * - allocate VPN IP address and generate client specific configuratiion file
 */

define('RET_OK',            0);
define('RET_ERR',           1);

define('LOG_STDOUT',        0x1);
define('LOG_SYSTEM_SYSLOG', 0x2);
define('LOG_ALL',           0x3);


/*
 * Init logging.
 */
function init_log($name, $flags = LOG_PID, $fac = LOG_DAEMON)
{
  openlog($name, $flags, $fac);
}


/*
 * Log message.
 */
function do_log($msg, $out = LOG_SYSLOG, $level = LOG_INFO)
{
  switch ($out)
  {
    case LOG_STDOUT:
      echo "$msg\n";
      break;

    case LOG_SYSLOG:
      syslog($level, $msg);
      break;

    default:
      echo "$msg\n";
      syslog($level, $msg);
      break;
  }
}


/*
 * Print usage.
 */
function print_usage($code)
{
  global $prog_name;

  echo "Usage: $prog_name <-n client_name><-c customer_name>[-hvOVSDUR][-t type][-D client_description]\n" .
       " [-d device_vpn_ip_pool_network][-u user_vpn_ip_pool_network][-I device_ssh_ip][-P device_ssh_port]\n" .
       " [-N device_ssh_username][-X device_ssh_password]\n" .
       "  Where:\n" .
       "    h = Show help\n" .
       "    v = Verbose\n" .
       "    O = Overwrite existing configs/cert/key\n" .
       "    V = Do not create client VPN config\n" .
       "    S = Do not create client specific config\n" .
       "    D = Do not add client to DB\n" .
       "    t = Type (device|user). Default to device\n" .
       "    n = Client common name\n" .
       "    D = Client description\n" .
       "    c = Customer name\n" .
       "    d = Device VPN IP pool network. Default to 10.8.1\n" .
       "    u = User VPN IP pool network. Defalt 10.8.200\n" .
       "    U = Upload VPN client configuration into device\n" .
       "    R = Restart device VPN client service\n" .
       "    a = VPN Server IP address\n" .
       "    p = VPN Server Port\n" .
       "    I = Device SSH IP address\n" .
       "    P = Device SSH Port. Default to 22\n" .
       "    N = Device SSH Username. Default to pi\n" .
       "    X = Device SSH Password.\n" .
       "    C = Check customer device certificate expiry.\n" .
       "    x = Generate and update device config with expired certificate.\n" .
       "    m = Certificate due to expire in n days. Default to 1.\n" .
       "    L = Check from list of online devices.\n" .
       "    F = Check from list of devices in a CSV file.\n" .
       "    B = Bulk generate and install device configs from a CSV file.\n" .
       "    l = List of devices in a CSV file for bulk update/install.\n" .
       "    G = When used with bulk (B), generate and install otherwise no changes.\n" .
       "\n";

  exit($code);
}


/*
 * Parse arguments.
 */
function parse_args()
{
  global $argv;
  global $options;

  if (count($argv) == 1)
  {
    return(0);
  }

  $opt_str = "hvOVSDURCxLFBGt:c:n:D:a:p:d:u:I:P:N:X:m:l:";

  $opts = getopt($opt_str);
  if (!$opts)
  {
    echo "Invalid argument.\n";
    print_usage(1);
  }

  if (isset($opts['h']))
  {
    print_usage(0);
  }

  if (isset($opts['v']))
  {
    $options['verbose'] = true;
  }

  if (isset($opts['t']))
  { 
    $options['type'] = ($opts['t'] == 'user') ? 'user' : 'device';
  }

  if (isset($opts['c']))
  { 
    $options['customer_name'] = $opts['c'];
  }

  if (isset($opts['n']))
  { 
    $options['client_name'] = $opts['n'];
  }

  if (isset($opts['D']))
  { 
    $options['client_description'] = $opts['D'];
  }

  if (isset($opts['O']))
  {
    $options['overwrite'] = true;
  }

  if (isset($opts['a']))
  {
    $options['server_ip'] = $opts['a'];
  }

  if (isset($opts['p']))
  {
    $options['server_port'] = $opts['p'];
  }

  if (isset($opts['V']))
  {
    $options['create_vpn_config'] = false;
  }

  if (isset($opts['S']))
  {
    $options['create_client_config'] = false;
  }

  if (isset($opts['D']))
  {
    $options['add_client_to_db'] = false;
  }

  if (isset($opts['d']))
  {
    $options['device_vpn_ip_pool'] = $opts['d'];
  }

  if (isset($opts['u']))
  {
    $options['user_vpn_ip_pool'] = $opts['u'];
  }

  if (isset($opts['U']))
  {
    $options['upload_client_vpn_config'] = true;
  }

  if (isset($opts['R']))
  {
    $options['restart_client_vpn'] = true;
  }

  if (isset($opts['I']))
  {
    $options['client_ssh_ip'] = $opts['I'];
  }

  if (isset($opts['P']))
  {
    $options['client_ssh_port'] = $opts['P'];
  }

  if (isset($opts['N']))
  {
    $options['client_ssh_username'] = $opts['N'];
  }

  if (isset($opts['X']))
  {
    $options['client_ssh_password'] = $opts['X'];
  }

  if (isset($opts['C']))
  {
    $options['check_cert_expiry'] = true;
  }

  if (isset($opts['x']))
  {
    $options['generate_update_expired_device_cert'] = true;
  }

  if (isset($opts['m']))
  {
    $options['days_before_expiration'] = intval($opts['m']);
  }

  if (isset($opts['F']))
  {
    $options['check_devices_from_file'] = true;
  }

  if (isset($opts['B']))
  {
    $options['bulk_generate'] = true;
  }

  if (isset($opts['l']))
  {
    $options['device_list'] = $opts['l'];
  }

  if (isset($opts['G']))
  {
    $options['go_bulk'] = true;
  }
}


/*
 * Check if client already exists.
 */
function is_client_existing($name, $ccb)
{
  $cert_path = $ccb['ca_dir'] . '/pki/issued/' . $name . '.crt';

  return(file_exists($cert_path));
}


/*
 * Cleanup client data.
 * - private and public keys, cert req, etc
 */
function cleanup_client_data($client_name, $ccb)
{
  $priv_key_path = $ccb['ca_dir'] . '/pki/private/' . $client_name . '.key';
  if (file_exists($priv_key_path))
  {
    unlink($priv_key_path);
  }

  $cert_path = $ccb['ca_dir'] . '/pki/issued/' . $client_name . '.crt';
  if (file_exists($cert_path))
  {
    unlink($cert_path);
  }
}


/*
 * Create client VPN config.
 */
function create_vpn_config($options, $ccb)
{
  do_log("- Generating client VPN configuration.", LOG_ALL);

  /* generete certificate request */
  $cmd = 'cd ' . $ccb['ca_dir'] . ' && echo ' . $options['client_name'] . ' | /usr/share/easy-rsa/easyrsa gen-req ' . $options['client_name'] . ' nopass > /dev/null 2>&1';
  $output = null;
  $ret_val = null;
  $ret = exec($cmd, $output, $ret_val);
  if ($ret_val != 0)
  {
    do_log("- Error generating certificate request!", LOG_ALL);
    return(RET_ERR);
  }

  /* sign certificate request */
  $cmd = 'cd ' . $ccb['ca_dir'] . ' && echo yes  | /usr/share/easy-rsa/easyrsa sign-req client ' . $options['client_name'] . ' > /dev/null 2>&1';
  $output = null;
  $ret_val = null;
  $ret = exec($cmd, $output, $ret_val);
  if ($ret_val != 0)
  {
    do_log("- Error signing certificate request!", LOG_ALL);
    return(RET_ERR);
  }

  /* create openvpn client configuration file */
  $ovpn_path = $ccb['ovpn_dir'] . '/client/configs/' . $options['type'] . 's/' . $options['client_name'] . '.ovpn';
  $fd = fopen($ovpn_path, "w");
  if (!$fd)
  {
    do_log("- Error creating OVPN file ($ovpn_path)", LOG_ALL);
    return(RET_ERR);
  }

  fwrite($fd, "dev tun\n");
  fwrite($fd, "persist-tun\n");
  fwrite($fd, "persist-key\n");
  fwrite($fd, "cipher AES-256-CBC\n");
  fwrite($fd, "auth SHA256\n");
  fwrite($fd, "tls-client\n");
  fwrite($fd, "client\n");
  fwrite($fd, "resolv-retry infinite\n");
  fwrite($fd, "remote " . $options['server_ip'] . ' ' . $options['server_port'] . " udp4\n");
  fwrite($fd, "remote-cert-tls server\n");
  fwrite($fd, "pull\n");
  fwrite($fd, "<ca>\n");

  $ca_str = file_get_contents($ccb['ovpn_dir'] . '/pki/ca.crt');
  fwrite($fd, $ca_str);

  fwrite($fd, "</ca>\n");
  fwrite($fd, "<cert>\n");

  unset($cert_str);
  $ret_val = null;
  $cmd = '/usr/bin/openssl x509 -in ' . $ccb['ca_dir'] . '/pki/issued/' . $options['client_name'] . '.crt';
  exec($cmd, $cert_str, $ret_val);
  fwrite($fd, implode("\n", $cert_str));

  fwrite($fd, "\n</cert>\n");
  fwrite($fd, "<key>\n");

  $key_str = file_get_contents($ccb['ca_dir'] . '/pki/private/' . $options['client_name'] . '.key');
  fwrite($fd, $key_str);

  fwrite($fd, "</key>\n");
  fwrite($fd, "key-direction 1\n");
  fwrite($fd, "<tls-auth>\n");

  $ta_str = file_get_contents($ccb['ovpn_dir'] . '/pki/ta.key');
  fwrite($fd, $ta_str);

  fwrite($fd, "</tls-auth>\n");
  fclose($fd);

  do_log("-- Generated: $ovpn_path", LOG_ALL);

  return(RET_OK);
}


/*
 * Create client specific config.
 */
function create_client_config($options, $ccb)
{
  global $vpn_ip_last_octet;

  do_log("- Generating client specific configuration.", LOG_ALL);
  if ($options['type'] == 'device')
  {
    $start_ip_pool = $options['device_vpn_ip_pool'];
    $end_ip_pool = $options['user_vpn_ip_pool'];
  }
  else
  {
    $start_ip_pool = $options['user_vpn_ip_pool'];
    $a = explode('.', $start_ip_pool);
    $end_ip_pool = sprintf("%s.%s.255", $a[0], $a[1]);
  }

  do_log("-- Start VPN IP Pool: $start_ip_pool", LOG_ALL);
  do_log("-- End VPN IP Pool: $end_ip_pool", LOG_ALL);

  $clients = get_customer_clients($options['customer_name'], $options['type']);
  do_log("-- Clients found: " . count($clients), LOG_ALL);

  /* find last assigned IP */
  $last_ip = null;
  foreach($clients as $client)
  {
    if ($client['common_name'] == $options['client_name'])
    {
      continue;
    }

    if ($options['verbose'])
    {
      do_log("--- Checking IP of client $client[common_name]", LOG_ALL);
    }

    $ccf_path = sprintf("%s/ccd/%s", $ccb['ovpn_dir'], $client['common_name']);
    if (!file_exists($ccf_path))
    {
      continue;
    }

    $ip = get_ip_from_ccf($ccf_path);
    if (empty($ip))
    {
      continue;
    }

    if ($options['verbose'])
    {
      do_log("--- IP found: [$ip]", LOG_ALL);
    }

    if (empty($last_ip))
    {
      $last_ip = $ip;
    }
    else
    {
      $last_ip = get_higher_ip($last_ip, $ip);
    }
  }

  do_log("-- Last assigned IP: [$last_ip]", LOG_ALL);

  /* get next available */
  $next_ip = get_next_ip($start_ip_pool, $end_ip_pool, $last_ip);
  do_log("-- Next Available IP: [$next_ip]", LOG_ALL);

  $ccf_path = sprintf("%s/ccd/%s", $ccb['ovpn_dir'], $options['client_name']);
  $fd = fopen($ccf_path, "w");
  if ($fd === false)
  {
    do_log("-- Error creating client config file", LOG_ALL);
    return(RET_ERR);
  }

  $a = explode('.', $next_ip);
  $line = sprintf("ifconfig-push %s %s.%s.%s.%d", $next_ip,
    $a[0], $a[1], $a[2], intval($a[3]) + 1);
  fwrite($fd, "$line");

  fclose($fd);

  return(RET_OK);
}


/*
 * Get next available IP.
 */
function get_next_ip($start_ip_pool, $end_ip_pool, $last_ip)
{
  global $vpn_ip_last_octet;
  $next_ip = null;

  $start_octets = explode('.', $start_ip_pool);
  $end_octets = explode('.', $end_ip_pool);

  if (empty($last_ip))
  {
    $next_ip = sprintf("%s.%s.%s.%d", $start_octets[0], $start_octets[1],
      $start_octets[2], $vpn_ip_last_octet[0]);
  }
  else
  {
    $last_octets = explode('.', $last_ip);
    if ($last_ip >= 253)
    {
      $next_ip = sprintf("%s.%s.%d.%d", $last_octets[0], $last_octets[1],
        intval($last_octets[2]) + 1, $vpn_ip_last_octet[0]);
    }
    else
    {
      $next_ip = sprintf("%s.%s.%s.%d", $last_octets[0], $last_octets[1],
        $last_octets[2], intval($last_octets[3]) + 4);
    }
  }

  return($next_ip);
}


/*
 *  Return higher IP from the two given IP address.
 */
function get_higher_ip($ip_a, $ip_b)
{
  $a = explode('.', $ip_a);
  $b = explode('.', $ip_b);

  if (($b[0] > $a[0]) ||
    ($b[0] == $a[0] && $b[1] > $a[1]) ||
    ($b[0] == $a[0] && $b[1] == $a[1] && $b[2] > $a[2]) ||
    ($b[0] == $a[0] && $b[1] == $a[1] && $b[2] == $a[2] && $b[3] > $a[3]))
  {
    return($ip_b);
  }
  else
  {
    return($ip_a);
  }
}


/*
 * Get IP address from client specif config file.
 */
function get_ip_from_ccf($ccf_path)
{
  $ip = null;

  $fd = fopen($ccf_path, "r");
  if ($fd === false)
  {
    return($ip);
  }

  while ($line = fgets($fd))
  {
    $a = explode(" ", $line);
    $kw = trim($a[0]);
    if ($kw !== 'ifconfig-push')
    {
      continue;
    }

    $ip = trim($a[1]);
    break;
  }

  fclose($fd);

  return($ip);
}


/*
 * Get customer clients.
 */
function get_customer_clients($customer_name, $type)
{
  global $mysqli;
  $clients = array();

  $cb = get_customer_db_cb($customer_name);
  if (empty($cb))
  {
    do_log("- Customer not found in DB.", LOG_ALL);
    return(RET_ERR);
  }

  $sql = sprintf("select cl.* from clients cl join customers c on c.id=cl.customer_id where c.common_name='%s' and cl.type='%s' and cl.deactivated_at is null order by cl.common_name",
    $customer_name, $type);
  if ($result = $mysqli->query($sql, MYSQLI_USE_RESULT))
  {
    while ($row = $result->fetch_assoc())
    {
      $clients[] = array(
        'id' => $row['id'],
	'name' => $row['name'],
        'common_name' => $row['common_name'],
        'expiry' => $row['expiry']
      );
    }
  }

  return($clients);
}


/*
 * Generate client config.
 */
function generate_client_config($options, $ccb)
{
  if ($options['overwrite'])
  {
    cleanup_client_data($options['client_name'], $ccb);
  }

  if ($options['create_vpn_config'])
  {
    $ret = create_vpn_config($options, $ccb);
    if ($ret != RET_OK)
    {
      return(RET_ERR);
    }
  }

  /* create client specific configuration file */
  if ($options['create_client_config'])
  {
    $ret = create_client_config($options, $ccb);
    if ($ret != RET_OK)
    {
      return(RET_ERR);
    }
  }

  /* create client entry in database */
  if ($options['add_client_to_db'])
  {
    delete_client_from_db($options['client_name'], $options['customer_name']);
    $ret = add_client_to_db($options['client_name'], $options['client_description'], $options['customer_name'], $options['type'], $ccb);
    if ($ret != RET_OK)
    {
      return(RET_ERR);
    }
  }

  return(RET_OK);
}


/*
 * Validate client name.
 * - Allowed: alphanumeric, underscore, dash
 */
function is_valid_client_name($name)
{
  return(preg_match('/^[a-zA-Z0-9_\-]+$/', $name));
}


/*
 * Get customer db entry.
 */
function get_customer_db_cb($customer_name)
{
  global $mysqli;
  $cb = array();

  $sql = "select * from customers where common_name='" . $customer_name . "' and deactivated_at is null";
  $result = $mysqli->query($sql, MYSQLI_USE_RESULT);
  if ($result)
  {
    if ($row = $result->fetch_assoc())
    {
      $cb['id'] = $row['id'];
      $cb['name'] = $row['name'];
      $cb['common_name'] = $row['common_name'];
      $cb['description'] = $row['description'];
      $cb['ovpn_dir'] = $row['vpn_server_dir'];
      $cb['ovpn_status_log'] = $row['vpn_server_status_log'];
      $cb['ca_dir'] = $row['ca_dir'];
    }
  }

  return($cb);
}


/*
 * Get client certificate expiry.
 */
function get_client_cert_expiry($client_name, $ccb)
{
  $expiry = '';
  $cmd = sprintf("/usr/bin/openssl x509 -in %s/pki/issued/%s.crt -enddate -noout", $ccb['ca_dir'], $client_name);
  $output = null;
  $ret_val = null;
  $ret = @exec($cmd, $output, $ret_val);
  if ($ret == 0 && $ret_val == 0)
  {
    $a = explode('=', $output[0], 2);
    $expiry = $a[1];
  }

  return($expiry);
}


/*
 * Add client to customer in database.
 */
function add_client_to_db($client_name, $client_desc, $customer_name, $type, $ccb)
{
  global $mysqli;
  do_log("- Adding client/customer mapping to database.", LOG_ALL);

  $cb = get_customer_db_cb($customer_name);
  if (empty($cb))
  {
    do_log("- Customer not found in DB.", LOG_ALL);
    return(RET_ERR);
  }

  $expiry = get_client_cert_expiry($client_name, $ccb);

  $sql = sprintf("insert into clients(customer_id,name,description,common_name,type,expiry,created_at,updated_at) values(%d, '%s', '%s', '%s', '%s', '%s', now(), now())",
    $cb['id'], $client_name, $client_desc, $client_name, $type, $expiry);
  $result = $mysqli->query($sql, MYSQLI_USE_RESULT);
  if ($result === false)
  {
    do_log("- Error adding client mapping!", LOG_ALL);
    return(RET_ERR);
  }

  do_log("-- Client($type) '$client_name' added under Customer '$customer_name'.", LOG_ALL);

  return(RET_OK);
}


/*
 * Delete client from customer in database.
 */
function delete_client_from_db($client_name, $customer_name)
{
  global $mysqli;

  $cb = get_customer_db_cb($customer_name);
  if (empty($cb))
  {
    do_log("- Customer not found in DB.", LOG_ALL);
    return(RET_ERR);
  }

  $sql = sprintf("delete from clients where customer_id=%d and common_name='%s'", $cb['id'], $client_name);
  $result = $mysqli->query($sql, MYSQLI_USE_RESULT);
  if ($result === false)
  {
    do_log("- Error deleting client mapping!", LOG_ALL);
    return(RET_ERR);
  }

  return(RET_OK);
}


/*
 * Connect to client using SSH.
 */
function ssh_to_client($options, $ccb)
{
  $con = @ssh2_connect($options['client_ssh_ip'], $options['client_ssh_port']);
  if ($con === false)
  {
    do_log("-- Unable to connect to $options[client_ssh_ip]:$options[client_ssh_port]", LOG_ALL);
    return(null);
  }

  $ret = @ssh2_auth_password($con, $options['client_ssh_username'], $options['client_ssh_password']);
  if ($ret === false)
  {
    do_log("-- Incorrect username/password for $options[client_ssh_username]", LOG_ALL);
    ssh2_disconnect($con);
    return(null);
  }

  return($con);
}


/*
 * Upload client VPN configuration.
 */
function upload_client_vpn_config($options, $ccb)
{
  $ovpn_path = $ccb['ovpn_dir'] . '/client/configs/' . $options['type'] . 's/' . $options['client_name'] . '.ovpn';
  do_log("- Uploading client VPN configuration ($ovpn_path) to device.", LOG_ALL);

  /* check client vpn config */
  if (!file_exists($ovpn_path))
  {
    do_log("-- Client VPN configuration not found: $ovpn_path", LOG_ALL);
    return(RET_ERR);
  }

  $con = ssh_to_client($options, $ccb);
  if ($con === null)
  {
    return(RET_ERR);
  }

  $rem_cmd = 'sudo apt-get update --allow-releaseinfo-change && sudo apt-get install -y openvpn';
  $ret = ssh2_exec($con, $rem_cmd);
  if ($ret === false)
  {
    do_log("-- Error executing remote command: $rem_cmd", LOG_ALL);
    ssh2_disconnect($con);
    return(RET_ERR);
  }

  stream_set_blocking($ret, true);
  stream_get_contents($ret);
  fclose($ret);

  $rem_ovpn_path = 'client.conf';
  $ret = @ssh2_scp_send($con , $ovpn_path, $rem_ovpn_path, 0644);
  if ($ret === false)
  {
    do_log("-- Error uploading file to device ($rem_ovpn_path)", LOG_ALL);
    ssh2_disconnect($con);
    return(RET_ERR);
  }

  $rem_cmd = sprintf("sudo cp %s /etc/openvpn/%s", $rem_ovpn_path, $rem_ovpn_path);
  $ret = ssh2_exec($con, $rem_cmd);
  if ($ret === false)
  {
    do_log("-- Error executing remote command: $rem_cmd", LOG_ALL);
    ssh2_disconnect($con);
    return(RET_ERR);
  }

  ssh2_disconnect($con);

  return(RET_OK);
}


/*
 * Restart client VPN.
 */
function restart_client_vpn($options, $ccb)
{
  do_log("- Restarting client VPN.", LOG_ALL);

  $con = ssh_to_client($options, $ccb);
  if ($con === null)
  {
    return(RET_ERR);
  }

  $rem_cmd = "sudo systemctl restart openvpn@client";
  $ret = ssh2_exec($con, $rem_cmd);
  if ($ret === false)
  {
    do_log("-- Error executing remote command: $rem_cmd", LOG_ALL);
    ssh2_disconnect($con);
    return(RET_ERR);
  }

  ssh2_disconnect($con);

  return(RET_OK);
}


/*
 * Restart device.
 */
function restart_device($options, $ccb)
{
  do_log("- Restarting device.", LOG_ALL);

  $con = ssh_to_client($options, $ccb);
  if ($con === null)
  {
    return(RET_ERR);
  }

  $rem_cmd = "sudo systemctl restart openvpn@client && sudo systemctl reboot";
  $ret = ssh2_exec($con, $rem_cmd);
  if ($ret === false)
  {
    do_log("-- Error executing remote command: $rem_cmd", LOG_ALL);
    ssh2_disconnect($con);
    return(RET_ERR);
  }

  ssh2_disconnect($con);

  return(RET_OK);
}


/*
 * Check customer expired device certs and generate and install if requested.
 */
function check_customer_expired_client_device_certs($options, $ccb)
{
  $customer_name = $options['customer_name'];

  do_log("- Checking expired device certificates of Customer: $customer_name.", LOG_ALL);
  $cust_devices = get_customer_clients($ccb['common_name'], 'device');
  do_log("- Existing customer devices found: " . count($cust_devices), LOG_ALL);
  $cust_device_names = array();
  foreach($cust_devices as $d)
  {
    $cust_device_names[] = $d['common_name'];
  }

  if ($options['check_devices_from_file'])
  {
    do_log("- Using device list from file", LOG_ALL);

    $devices = get_expired_devices_from_file($options['device_list_file']);
  }
  else
  {
    do_log("- Using device list from online devices", LOG_ALL);
    $devices = get_expired_online_devices($options, $ccb, $cust_devices);
  }

  $n = count($devices);
  do_log("- Expired devices found: $n", LOG_ALL);
  if (empty($devices) || !$options['generate_update_expired_device_cert'])
  {
    return(RET_OK);
  }

  do_log("- Generating new certificates/configs", LOG_ALL);

  if (empty($options['client_ssh_username']) || empty($options['client_ssh_password']))
  {
    do_log("- SSH username/password unspecified! Aborting.", LOG_ALL);
    return(RET_ERR);
  }

  if (empty($options['server_ip']) || empty($options['server_port']))
  {
    do_log("- VPN Server IP and Port must be specified!", LOG_ALL);
    return(RET_ERR);
  }

  /* re-use options and bump the values */
  $options['overwrite'] = true;
  $options['create_vpn_config'] = true;
  $options['create_client_config'] = false;
  $options['add_client_to_db'] = true;
  $options['type'] = 'device';

  $i = 1;
  foreach($devices as $device)
  {
    /* if ($i > 1)
    {
      continue;
    } */

    do_log("- [$i/$n] Working on device: $device[common_name], ip: $device[ip]", LOG_ALL);
    $i++;

    /* set client name in option */
    $options['client_name'] = $device['common_name'];
    $options['client_ssh_ip'] = $device['ip'];

    $ret = generate_client_config($options, $ccb);
    if ($ret != RET_OK)
    {
      do_log("- Failed to re-generate device configuration!", LOG_ALL);
      continue;
    }

    do_log("- Device configuration successfully re-generated!", LOG_ALL);

    $ret = upload_client_vpn_config($options, $ccb);
    if ($ret != RET_OK)
    {
      do_log("- Failed to upload configuration to device!", LOG_ALL);
      continue;
    }

    $ret = restart_device($options, $ccb);
    if ($ret != RET_OK)
    {
      do_log("- Failed to restart device!", LOG_ALL);
      continue;
    }

    do_log("- New configuration successfully applied to device!", LOG_ALL);
  }
 
  return(RET_OK);
}


/*
 * Get online devices.
 */
function get_expired_online_devices($options, $ccb, $client_cbs)
{
  $devices = array();
  $i = 1;
  $n = count($client_cbs);

  $online_devices = load_customer_online_devices($ccb);
  do_log("- Online devices found: " . count($online_devices), LOG_ALL);
  if (empty($online_devices))
  {
    do_log("- No online devices found. Nothing to do!", LOG_ALL);
    return($devices);
  }

  $cur_tt = new DateTime("now");

  foreach($client_cbs as $client_cb)
  {
    $device_name = $client_cb['common_name'];
    do_log("- [$i/$n] Checking device: $device_name", LOG_ALL);
    $i++;

    $expiry_tt = new Datetime($client_cb['expiry']);
    if ($options['days_before_expiration'] > 0)
    {
      $s = sprintf("P%dD", $options['days_before_expiration']);
      $interval = new DateInterval($s);
      $interval->invert = 1;
      $expiry_tt->add($interval);
    }

    if ($cur_tt < $expiry_tt)
    {
      do_log("- Certificate still valid. Skipping.", LOG_ALL);
      continue;
    }

    do_log("- Certificate has expired!", LOG_ALL);

    if (array_key_exists($device_name, $online_devices))
    {
      do_log("- Device is online. Adding to list.", LOG_ALL);
      $devices[] = array_merge($client_cb, $online_devices[$device_name]);
    }
    else
    {
      do_log("- Device is offline. Skipping.", LOG_ALL);
    }
  }

  return($devices);
}


/*
 * Load customer online devices
 */
function load_customer_online_devices($ccb)
{
  $devices = array();
  $vpn_status_log = $ccb['ovpn_status_log'];

  $fd = fopen($vpn_status_log, "r");
  if (!$fd)
  {
    do_log("- Failed to open status log file: $vpn_status_log", LOG_ALL);
    return($devices);
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
      "public_ip" => explode(":", $a[2])[0]
    );

    $key = $a[1];
    $devices[$key] = $h;
  }

  fclose($fd);

  return($devices);
}


/*
 * Generate bulk device configs.
 */
function generate_bulk_device_configs($options, $ccb)
{
  $device_list = $options['device_list'];

  if (empty($device_list) || !file_exists($device_list))
  {
    do_log("- Device list not found: $device_list", LOG_ALL);
    return(RET_ERR);
  }

  /* load devices from file */
  $devices = load_devices_from_file($device_list);
  if ($devices == NULL)
  {
    return(RET_ERR);
  }

  $n = count($devices);
  do_log("- Devices found: $n", LOG_ALL);

  if (empty($devices))
  {
    return(RET_OK);
  }

  /* re-use options and bump the values */
  $options['overwrite'] = true;
  $options['create_vpn_config'] = true;
  $options['create_client_config'] = true;
  $options['add_client_to_db'] = true;

  $i = 1;
  $generated = 0;
  $installed = 0;
  foreach($devices as $dev_name => $dev_info)
  {
    do_log("- [$i/$n] Working on device: $dev_name / Type: $dev_info[type]", LOG_ALL);
    do_log("- IP: $dev_info[ip] / Port: $dev_info[port]", LOG_ALL);

    $i++;

    if (!$options['go_bulk'])
    {
      continue;
    }

    /* set client name in option */
    $options['client_name'] = $dev_name;
    $options['client_description'] = $dev_info['desc'];
    $options['type'] = $dev_info['type'];

    $ret = generate_client_config($options, $ccb);
    if ($ret != RET_OK)
    {
      do_log("- Failed to generate device configuration!", LOG_ALL);
      continue;
    }

    do_log("- Device configuration successfully generated!", LOG_ALL);
    $generated++;

    if (empty($dev_info['ip']) || empty($dev_info['port']))
    {
      continue;
    }

    $options['client_ssh_ip'] = $dev_info['ip'];
    $options['client_ssh_port'] = $dev_info['port'];
    if (!empty($dev_info['username']))
    {
      $options['client_ssh_username'] = $dev_info['username'];
    }

    if (!empty($dev_info['password']))
    {
      $options['client_ssh_password'] = $dev_info['password'];
    }

    if (empty($options['client_ssh_username']) || empty($options['client_ssh_password']))
    {
      continue;
    }

    $ret = upload_client_vpn_config($options, $ccb);
    if ($ret != RET_OK)
    {
      do_log("- Failed to upload configuration to device!", LOG_ALL);
      continue;
    }

    $ret = restart_device($options, $ccb);
    if ($ret != RET_OK)
    {
      do_log("- Failed to restart device!", LOG_ALL);
      continue;
    }

    do_log("- New configuration successfully applied to device!", LOG_ALL);
    $installed++;
  }

  do_log("- Generated: $generated / Installed: $installed", LOG_ALL);

  return(RET_OK);
}


/*
 * Load devices from file.
 */
function load_devices_from_file($device_list, $with_header = true)
{
  $devices = array();

  $fd = fopen($device_list, "r");
  if (!$fd)
  {
    do_log("- Error opending device list file: $device_list", LOG_ALL);
    return(RET_ERR);
  }

  $first = true;
  while ($line = fgets($fd))
  {
    /* skip header */
    if ($first && $with_header)
    {
      $first = false;
      continue;
    }

    $line= trim($line);
    /* skip commented entry */
    if ($line[0] === '#')
    {
      continue;
    }

    $a = explode(',', $line);
    /* must include at least the device name, description and type */
    if (count($a) < 3)
    {
      continue;
    }

    $d_name = trim($a[0]);
    $d_desc = trim($a[1]);
    $d_type = trim($a[2]);
    $d_ip = (count($a) > 3) ? trim($a[3]) : '';
    $d_port = (count($a) > 4) ? trim($a[4]) : '';
    $d_username  = (count($a) > 5) ? trim($a[5]) : '';
    $d_password = (count($a) > 6) ? trim($a[6]) : '';

    $devices[$d_name] = array(
      'name' => $d_name,
      'desc' => $d_desc,
      'type' => $d_type,
      'ip' => $d_ip,
      'port' => $d_port,
      'username' => $d_username,
      'password' => $d_password
    );
  }

  fclose($fd);

  return($devices);
}


/* system */
date_default_timezone_set("UTC");

/* globals */
$prog_name = $argv[0];
$options = array(
  'verbose' => false,
  'dryrun' => false,
  'type' => 'device',
  'customer_name' => getenv('CUSTOMER_NAME'),
  'client_name' => null,
  'client_description' => null,
  'overwrite' => false,
  'create_vpn_config' => true,
  'create_client_config' => true,
  'add_client_to_db' => true,
  'server_ip' => getenv('VPNSERVER_PUBLIC_IP'),
  'server_port' => getenv('VPNSERVER_PUBLIC_PORT'),
  'db_ip' => 'mysql',
  'db_name' => 'vpn',
  'db_user' => 'vpnuser',
  'db_password' => 'vpnuser*pw123',
  'device_vpn_ip_pool' => '10.8.1',
  'user_vpn_ip_pool' => '10.8.200',
  'upload_client_vpn_config' => false,
  'restart_client_vpn' => false,
  'client_ssh_ip' => null,
  'client_ssh_port' => 22,
  'client_ssh_username' => 'pi',
  'client_ssh_password' => null,
  'check_cert_expiry' => false,
  'generate_update_expired_device_cert' => false,
  'days_before_expiration' => 1,
  'check_online_devices' => true,
  'check_devices_from_file' => false,
  'bulk_generate' => false,
  'device_list' => '',
  'go_bulk' => false
);

/* VPN IP last octets */
$vpn_ip_last_octet = array(
  1, 5, 9, 13, 17,
  21, 25, 29, 33, 37,
  41, 45, 49, 53, 57,
  61, 65, 69, 73, 77,
  81, 85, 89, 93, 97,
  101, 105, 109, 113, 117,
  121, 125, 129, 133, 137,
  141, 145, 149, 153, 157,
  161, 165, 169, 173, 177,
  181, 185, 189, 193, 197,
  201, 205, 209, 213, 217,
  221, 225, 229, 233, 237,
  241, 245, 249, 253
);

init_log('gvcf');

do_log("Starting ${prog_name}", LOG_ALL);

/* parse args */
parse_args();

/* connect to database */
$mysqli = new mysqli($options['db_ip'], $options['db_user'], $options['db_password'], $options['db_name']);
if ($mysqli->connect_errno) {
  do_log("- Error connecting to DB:" . $mysqli->connect_error, LOG_ALL);
  exit(1);
}

/* check if customer exists */
if (empty($options['customer_name']) ||
  ($customer_cb = get_customer_db_cb($options['customer_name'])) == null)
{
  do_log("- Unknown/missing customer name: $options[customer_name]", LOG_ALL);
  exit(1);
}

/* check if we are auto-updating expired customer device certs */
if ($options['check_cert_expiry'])
{
  $ret = check_customer_expired_client_device_certs($options, $customer_cb);
  if ($ret != RET_OK)
  {
    exit(1);
  }
}
else if ($options['bulk_generate'])
{
  $ret = generate_bulk_device_configs($options, $customer_cb);
  if ($ret != RET_OK)
  {
    exit(1);
  }
}
else
{
  /* check if client name is valid */
  if (empty($options['client_name']) || !is_valid_client_name($options['client_name']))
  {
    do_log("- Missing/invalid client name!", LOG_ALL);
    exit(1);
  }

  /* check if client already exists */
  if (is_client_existing($options['client_name'], $customer_cb) && !$options['overwrite'] && $options['create_vpn_config'])
  {
    do_log("- Client name existing/used.", LOG_ALL);
    exit(1);
  }

  if ($options['upload_client_vpn_config'] || $options['restart_client_vpn'])
  {
    if (empty($options['client_ssh_ip']) || empty($options['client_ssh_port']) ||
      empty($options['client_ssh_username']) || empty($options['client_ssh_password']))
    {
      do_log("- Client IP/Port/Username/Password missing.", LOG_ALL);
      exit(1);
    }

    if (!filter_var($options['client_ssh_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
    {
      do_log("- Invalid client SSH IP address: $options[client_ssh_ip]", LOG_ALL);
      exit(1);
    }
  }

  $ret = generate_client_config($options, $customer_cb);
  if ($ret != RET_OK)
  {
    exit(1);
  }

  if ($options['upload_client_vpn_config'])
  {
    $ret = upload_client_vpn_config($options, $customer_cb);
    if ($ret != RET_OK)
    {
      exit(1);
    }
  }

  if ($options['restart_client_vpn'])
  {
    $ret = restart_client_vpn($options, $customer_cb);
    if ($ret != RET_OK)
    {
      exit(1);
    }
  }
}

do_log("Done.", LOG_ALL);
exit(0);

?>
