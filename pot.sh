#!/bin/bash

# Create the POT files for Broadcast and Blog Groups.

xgettext -s -c --no-wrap -d threewp_broadcast\ThreeWP_Broadcast -p lang -o "lang/ThreeWP_Broadcast.pot" \
    --omit-header -k_ -kerror_ -kdescription_ -kmessage_ -kheading_ -klabel_ -kname_ -koption_ -kp_ -ktext_ -kvalue_ \
    src/sdk/sdk/*php src/sdk/sdk/form2/inputs/*php src/sdk/sdk/form2/inputs/traits/*php src/sdk/sdk/wordpress/*php \
    src/*php \
    src/traits/*php

xgettext -s -c --no-wrap -d threewp_broadcast\blog_groups\ThreeWP_Broadcast_Blog_Groups -p lang -o "lang/ThreeWP_Broadcast_Blog_Groups.pot" \
    --omit-header -k_ -kerror_ -kdescription_ -kmessage_ -kheading_ -klabel_ -kname_ -koption_ -kp_ -ktext_ -kvalue_ ThreeWP_Broadcast_Blog_Groups.php src/sdk/sdk/*php src/sdk/sdk/form2/inputs/*php src/sdk/sdk/form2/inputs/traits/*php \
    src/blog_groups/*php \
    src/sdk/sdk/wordpress/*php
