#!/bin/bash
cd /certs

echo "Delete files from previous run"
rm -f ./rootCA.pem
rm -f ./server-cert.pem
rm -f ./server-key.pem
rm -f ./client-cert.pem
rm -f ./client-key.pem


echo "Setup CA cert";
/usr/local/bin/mkcert -install

echo "Generate server certs";
/usr/local/bin/mkcert -cert-file server-cert.pem -key-file server-key.pem localhost ::1

echo "Generate client certs";
/usr/local/bin/mkcert -client -cert-file client-cert.pem -key-file client-key.pem email@localhost

echo "Copy CA file to certs directory";
cp "$(mkcert -CAROOT)/rootCA.pem" /certs

chmod 644 /certs/client-key.pem
