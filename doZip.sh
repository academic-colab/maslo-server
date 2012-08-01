#!/bin/sh

cd $1;
rm  "$2";
zip -r "$2" "$3";
