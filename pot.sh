#!/bin/bash

DOMAIN="ThreeWP_Broadcast"
cp "$DOMAIN.pot" lang/$DOMAIN.pot
OPTIONS="-s -j --no-wrap -d $DOMAIN -p lang -o $DOMAIN.pot --omit-header"
xgettext $OPTIONS ThreeWP_*php
