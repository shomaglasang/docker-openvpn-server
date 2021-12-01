#!/bin/bash

cd /etc/openvpn
echo "openvpnserver" | easyrsa gen-req openvpnserver nopass > /dev/null 2>&1
echo "yes" | easyrsa sign-req server openvpnserver > /dev/null 2>&1
easyrsa gen-dh
openvpn --genkey --secret /etc/openvpn/pki/ta.key
