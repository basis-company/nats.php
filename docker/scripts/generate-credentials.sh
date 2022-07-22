#!/bin/bash

cd /credentials

# Clean up
rm -rf ./stores
rm -rf ./keys
rm -f ./server.conf

# Generate
mkdir ./stores
mkdir ./keys
nsc init -d ./stores -n user --keystore-dir ./keys
nsc generate config --mem-resolver --config-file ./server.conf --keystore-dir ./keys
chmod -R a+rX ./
