<?php
include_once('config.inc');
include_once('lib.inc');


echo "db_ip: [$db_ip]\n";
echo "db_port: [$db_port]\n";
echo "db_user: [$db_user]\n";
echo "db_password: [$db_password]\n";
echo "db_name: [$db_name]\n";
$mysqli = open_db("mysql", $db_user, $db_password, $db_name, $db_port);

?>
