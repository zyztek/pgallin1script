#! /bin/sh
# 
# @filename compress.sh
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#
mode=$1;
src=$2;
dst=$3;

if [ "$YUIPATH" = "" ]; then
    for file in *.jar
    do
       jar=$file;
    done
else
    jar=$YUIPATH;
fi

if [ ! -e $jar ]; then
    echo "    Note: $YUIPATH to YUI compressor jar file needs to be set";
    echo "          yuicompressor.jar file must exist in working dir!";
    exit 1;
fi

if [ ! "$mode" = "-a" ]; then

    src=$mode;

    if [ "$src" = "" ]; then
	echo "Usage:"
	echo "    compress.sh -a src-folder dst-folder";
	echo "    compress.sh file";
	exit 1;
    fi

    h='';
    echo $src | grep "\.user\.js" > /dev/null 2>&1;
    if [ $? -eq 0 ]; then
	h=`head -n 16 $src`;
    fi

    cat $src | grep "eval(" > /dev/null;
    if [ $? -eq 1 ]; then
	c=`java -jar $jar --type js --line-break 50 $src`;
    else
	c=`cat $src`;
    fi

    echo "$h";
    echo "$c";

    exit 0;

else

    if [ "$src" = "" -o "$dst" = "" ]; then
        echo "Usage:"
        echo "    compress.sh -a src-folder dst-folder";
        echo "    compress.sh file";
        exit 1;
    fi

    start=`pwd`;
    cd $src;

    if [ ! -d $start/$dst ]; then
	mkdir $start/$dst;
    else
	rm -r $start/$dst/*
    fi

    touch $start/compress.log;

    for file in *.jsi
    do
    echo "Processing $file";
        cat $file | grep "eval(" > /dev/null;
        if [ $? -eq 1 ]; then
            java -jar $start/$jar -v --type js --line-break 50 $file -o $start/$dst/$file >> $start/compress.log 2>&1;
        else
            cp -f $file $start/$dst/$file;
        fi
        if [ ! -e $start/$dst/$file  ]; then
            cat $start/compress.log | grep "ERROR";
            echo "Yui Compressor Error on $file!";
            exit 1;
        fi
    done

    for file in *.js
    do
        echo "Processing $file";
        java -jar $start/$jar -v --type js --line-break 50 $file -o $start/tmp >> $start/compress.log 2>&1;
        if [ ! -e $start/tmp  ]; then
	    cat $start/compress.log | grep "ERROR";
            echo "Yui Compressor Error on $file!";
            exit 1;
        fi
        echo "Process headers of $file";
        head -16 $file > $start/$dst/$file;
        cat $start/tmp >> $start/$dst/$file;
        rm $start/tmp;
    done

    rm $start/compress.log;
    cd $start
fi

exit 0;
