#!/bin/bash

DOMAIN="ThreeWP_Broadcast"
cp "$DOMAIN.pot" lang/$DOMAIN.pot
OPTIONS="-s -j --no-wrap -d $DOMAIN -p lang -o $DOMAIN.pot -k_ -kp_ -kmessage_ -kerror_ -ktext_ --omit-header"
xgettext $OPTIONS *php plainview_sdk/*php plainview_sdk/wordpress/*

