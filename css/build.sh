#!/bin/bash
FILE=css.scss
scss $FILE
minify.css $FILE.css
