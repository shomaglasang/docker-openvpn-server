# OpenVPN Server and Web Interface for Docker
OpenVPN server in a Docker container complete with a Web Interface/PHP, MySQL and ovpn config generator script.

## Quick Start
* Edit the public IP and port of the OpenVPN server in the .env file.

      VPNSERVER_PUBLIC_IP=192.168.1.1
      VPNSERVER_PUBLIC_PORT=11194
     
* The default public port of the web server is 8080. Edit the ports value of the web service section in the docker-compose.yml file.

      ports:
      - 8080:80
      
* Bootstrap the containers from the bootstrap script. This sets up everything. It will prompt for the CA name. You should do this first before "docker-compose" or docker commands.

      ./bs.sh start
  
## Settings  

## Generate OpenVPN clients configuration file (.ovpn)

* Generate .ovpn file for client-user with the name "johndoe"

      docker-compose exec php /scripts/gvcf.php -n johndoe -t user
  On success, the configuration file is located at ./ovpn-data/client/configs/users/johndoe.ovpn

* Generate .ovpn file for client-device with the name "edge28"

      docker-compose exec php /scripts/gvcf.php -n edge28 -t device
  On success, the configuration file is located at ./ovpn-data/client/configs/devices/edge28.ovpn
  

## Todo
* Add user authentication for web access
* Setup/install SSL cert for the web server
