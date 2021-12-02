#!/bin/bash

# set -x

use_sudo () {
  docker ps > /dev/null
  if [ $? -ne 0 ]; then
    echo "sudo"
  fi
}

init_files() {
  if [ ! -f "./ovpn-data/openvpn-status.log" ]; then
    echo "Creating openvpn-status.log"
    touch ./ovpn-data/openvpn-status.log
    chmod 644 ./ovpn-data/openvpn-status.log
  fi

  if [ ! -d "./ovpn-data/ccd" ]; then
    echo "Creating openvpn ccd folder"
    sudo mkdir -p ./ovpn-data/ccd
  fi

  if [ ! -d "./ovpn-data/client/configs/users" ]; then
    echo "Creating openvpn client users folder"
    sudo mkdir -p ./ovpn-data/client/configs/users
  fi

  if [ ! -d "./ovpn-data/client/configs/devices" ]; then
    echo "Creating openvpn client devices folder"
    sudo mkdir -p ./ovpn-data/client/configs/devices/
  fi
}

init_db () {
  docker-compose exec mysql mysql -u root -h localhost vpn -e "describe customers" > /dev/null
  if [ $? -eq 0 ]; then
    echo "VPN tables already exists."
    return
  fi

  echo "- Creating vpn database"
  docker-compose exec mysql mysql -u root -h localhost -e "
CREATE DATABASE IF NOT EXISTS vpn
" > /dev/null

  echo "- Adding vpnuser ..."
  docker-compose exec mysql mysql -u root -h localhost -e "GRANT ALL ON vpn.* TO 'vpnuser'@'%' IDENTIFIED BY 'vpnuser*pw123';FLUSH PRIVILEGES;" > /dev/null

  echo "- Adding tables ..."
  docker-compose exec mysql mysql -u root -h localhost -e "
use vpn;
CREATE TABLE customers (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  description varchar(255) DEFAULT NULL,
  common_name varchar(100) NOT NULL,
  status int(11) NOT NULL DEFAULT '1',
  vpn_server_dir varchar(255) DEFAULT NULL,
  vpn_server_config varchar(255) DEFAULT NULL,
  vpn_server_status_log varchar(255) DEFAULT NULL,
  ca_dir varchar(255) DEFAULT NULL,
  server_port int(11) DEFAULT '1194',
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  deactivated_at datetime DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
CREATE TABLE clients (
  id int(11) NOT NULL AUTO_INCREMENT,
  customer_id int(11) NOT NULL,
  name varchar(255) NOT NULL,
  description varchar(255) DEFAULT NULL,
  common_name varchar(100) NOT NULL,
  type varchar(50) DEFAULT 'device',
  status int(11) NOT NULL DEFAULT '1',
  expiry varchar(255) DEFAULT NULL,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  deactivated_at datetime DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=latin1;
" > /dev/null

  echo "- Adding default customer ..."
  docker-compose exec mysql mysql -u root -h localhost -e "
use vpn;
INSERT INTO customers(name, description, common_name, vpn_server_dir, vpn_server_config, vpn_server_status_log, ca_dir, server_port, created_at, updated_at)
VALUES('Server1', 'Server1', 'Server1', '/etc/openvpn', '/etc/openvpn/server.conf', '/etc/openvpn/openvpn-status.log', '/etc/openvpn', 1194, now(), now());
" > /dev/null

}

delete_db() {
  echo "- Deleting vpn database"
  docker-compose exec mysql mysql -u root -h localhost -P 3306 -e "
DROP DATABASE IF EXISTS vpn
" > /dev/null
}

init_ca() {
  sudo ls ovpn-data/pki/ca.crt > /dev/null 2>&1
  if [ $? -eq 0 ]; then
    echo "CA already exists."
    return
  fi

  echo "Creating CA."
  docker run -v $PWD/ovpn-data:/etc/openvpn --rm -it kylemanna/openvpn easyrsa init-pki
  docker run -v $PWD/ovpn-data:/etc/openvpn --rm -it kylemanna/openvpn easyrsa build-ca nopass
}

init_vpn_server() {
  sudo ls ovpn-data/pki/issued/openvpnserver.crt > /dev/null 2>&1
  if [ $? -eq 0 ]; then
    echo "OpenVPN server already exists."
    return
  fi
  
  echo "Creating OpenVPN server."
  docker run -v $PWD/ovpn-data:/etc/openvpn --rm -it kylemanna/openvpn /etc/openvpn/init_openvpn_server.sh
}

start_apps () {
  echo "Starting VPN containers."
  init_files
  init_ca
  init_vpn_server

  $(use_sudo) docker-compose up -d

  sleep 15
  init_db
  echo "Done."
}

stop_apps () {
  echo "Stopping VPN containers."
  $(use_sudo) docker-compose down --remove-orphans
  echo "Done."
}

init_vpn() {
  echo "Initializing VPN."

  stop_apps
  sudo rm -fR ovpn-data/pki ovpn-data/openvpn-status.log ovpn-data/openvpn.log

  start_apps
  delete_db
  init_db
  echo "Done."
}

if [ "$1" = "start" ]; then
  start_apps
elif [ "$1" = "stop" ]; then
  stop_apps
elif [ "$1" = "initvpn" ]; then
  init_vpn
else
  echo "Usage: . bs.sh <start|stop|initvpn>"
fi

# set +x # disable verbose
