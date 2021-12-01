<?php

include_once('config.inc');
include_once('lib.inc');

$mysqli = open_db($db_ip, $db_user, $db_password, $db_name);
$customer_id = intval($_POST['customer_id']);
$client_name = $_POST['client_name'];
$client_description = $_POST['client_description'];

$input_good = true;
$status = 'error';
$error_message = '';

if (strlen($client_description) > 100)
{
  $input_good = false;
  $error_message = 'Description too long!';
}

if ($input_good)
{
  $client = get_client_info($client_name, $customer_id);
  if (!empty($client)) {
    $description = $mysqli->real_escape_string($client_description);
    if (update_client_info($client_name, $customer_id, $description))
    {
      $status = 'success';
    }
    else
    {
      $error_message = 'Error updating client.';
    }
  }
  else
  {
    $error_message = 'Client not found.';
  }
}

echo json_encode(array('status' => $status, 'message' => $error_message));

close_db($mysqli);
exit();
?>
