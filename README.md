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

|Settings| Default Value| Description|File|
|--------|--------------|------------|----|
|VPNSERVER_PUBLIC_IP | 192.168.1.1| OpenVPN Public IP address| .env|
|VPNSERVER_PUBLIC_PORT| 11194| OpenVPN Public Port| .env|
|services.web.ports|8080| Web/Nginx Public Port| docker-compose.yml|
|CUSTOMER_NAME|Server1| Default Customer/Organization Name| hard-coded|
|OpenVPN Server Network| 10.8.0.0/24| OpenVPN Server VPN network| ovpn-data/server.conf|
|OpenVPN Client User Network|10.8.200.0/24| OpenVPN client user network address space| -u option of /scripts/gvcf|
|OpenVPN Client Device Network| 10.8.1.0/24|OpenVPN client device network address space| -d option of /scripts/gvcf|

## Generate OpenVPN clients configuration file (.ovpn)

There are 2 types of OpenVPN configuration files, user and device. A user configuration file is intended for end-users. This configuration file is installed to user computer or device. The device configuration file is intended for devices or network equipments such as routers, firewalls, servers, etc. Technically there are no differences between them. The distinction is for monitoring purposes. In the Web UI, online devices and users are listed separately. 

* Generate .ovpn file for client-user with the name "johndoe"

      docker-compose exec php /scripts/gvcf.php -n johndoe -t user
  On success, the configuration file is located at ./ovpn-data/client/configs/users/johndoe.ovpn

* Generate .ovpn file for client-device with the name "edge28"

      docker-compose exec php /scripts/gvcf.php -n edge28 -t device
  On success, the configuration file is located at ./ovpn-data/client/configs/devices/edge28.ovpn
  

## Todo
* Add user authentication for web access
* Setup/install SSL cert for the web server
