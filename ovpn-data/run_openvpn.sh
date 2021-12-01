#!/bin/bash

cd /etc/openvpn
mkdir -p /dev/net
if [ ! -c /dev/net/tun ]; then
  mknod /dev/net/tun c 10 200
fi

openvpn --config /etc/openvpn/server.conf
