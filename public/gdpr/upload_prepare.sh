#!/bin/bash


# Adresar, ve kterem je index.php
WWWDIR=vyvoj
# Adresar, kde se chystaji uploady (musi existovat)
UPLDIR=upl

while getopts ":s:t:quhpmi" opt; do
    case $opt in
  s)
      WWWDIR=$OPTARG
      ;;
  t)
      UPLDIR=$OPTARG
      ;;
  q)
      pridatsql=y
      ;; 
  i)
      initial=y
      ;; 
  u)
      doupload=y
      ;;
  h)
      printhelp=y
      doexit=y
      ;;
  p)
      notar=y
      ;;
  m)
      minimalistic=y
      ;;
  \?)
      echo "Neznamy prepinac: -$OPTARG"
      printhelp=y
      doexit=y
      ;;
  :)
      echo "Je treba zadat argument k prepinaci -$OPTARG"
      printhelp=y
      doexit=y
      ;;
    esac
done

if [ -n "$printhelp" ]; then
    echo "Skript pripravi balicek k uploadu a pripadne provede i skript"
    echo "pre-upload, ktery pripravi balicek k nahrani uploaderem."
    echo " -s source_dir - adresar, v kterem je instalace inBoxu s index.php"
    echo "    default je $WWWDIR"
    echo " -t target_dir - adresar, pro skladovani docasnych souboru pro upload"
    echo "    default je $UPLDIR"
    echo " -q pridat i adresar target_dir/upl/sql pokud je v nem nejaky soubor"
    echo " -i provest inicialni upload"
    echo " -u provest skript pre-upload bez ptani"
    echo "    NENI DOPORUCENO, vzdy je vhodne zkontrolovat posledni upravene soubory"
    echo " -p nevytvaret balicek pro upload, pouze nahraje data do $UPLDIR/www"
  echo " -m pripravit maly balicek bez library/Zend"

fi
if [ -n "$doexit" ]; then
    exit 1
fi


#######################################
# KOPIROVANI
#######################################
echo "Uklizim docasny adresar..."
rm -rf $UPLDIR/www/*
rm -rf $UPLDIR/www/*.*
#mkdir $UPLDIR/www
mkdir $UPLDIR/www/pub
mkdir $UPLDIR/www/pub/skins

echo "Kopiruji admin/"
#cp --preserve=timestamps -R $WWWDIR/admin $UPLDIR/www/admin
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/admin $UPLDIR/www/
echo "Kopiruji controllers/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/controllers $UPLDIR/www/

echo "Kopiruji library/"
if [ -z "$minimalistic" ]; then
  rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/library $UPLDIR/www/
else
  echo "Vynechavam library/Zend"
  echo "Vynechavam library/mpdf"
  rsync --copy-links  --exclude="library/Zend/" --exclude="library/mpdf/" --exclude '.svn' -r -t $WWWDIR/library $UPLDIR/www/
fi

if [ -z "$initial" ]; then
  echo "Mazu library/Phc/_config_log.php"
  rm $UPLDIR/www/library/Phc/_config_log.php
else
  echo "Nastavuji library/Phc/_config_log.php"
  sed -i -e's/TEST\"\,\ [01]/TEST\",\ 0/g' $UPLDIR/www/library/Phc/_config_log.php
  echo "Kopiruji config_db.ini a config_admin.ini"
  cp -v $WWWDIR/config_db.ini $UPLDIR/www/
  cp -v $WWWDIR/config_admin.ini $UPLDIR/www/
  echo "Kopiruji texts/"
  rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/texts $UPLDIR/www/  
fi

echo "Kopiruji models/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/models $UPLDIR/www/
echo "Kopiruji texts/ (krome specific)"
rsync --copy-links  -t -r --exclude '.svn' --exclude 'specific' $WWWDIR/texts $UPLDIR/www/
echo "Kopiruji views/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/views $UPLDIR/www/
echo "Kopiruji soubory v korenu webu/"
#cp --preserve=timestamps $WWWDIR/* $UPLDIR/www/ 2> /dev/null
rsync --copy-links  -t --exclude 'config_db.ini' --exclude 'config_admin.ini' --exclude '*/' $WWWDIR/* $UPLDIR/www/
rsync --copy-links  -t --exclude '*/' $WWWDIR/.* $UPLDIR/www/

echo "Kopiruji pub/css/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/pub/css $UPLDIR/www/pub/
echo "Kopiruji pub/img/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/pub/img $UPLDIR/www/pub/
echo "Kopiruji pub/scripts/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/pub/scripts $UPLDIR/www/pub/
cp -L --preserve=timestamps $WWWDIR/pub/* $UPLDIR/www/pub/ 2> /dev/null
cp -L --preserve=timestamps $WWWDIR/pub/.* $UPLDIR/www/pub/ 2> /dev/null
echo "Kopiruji pub/skins/default/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/pub/skins/default $UPLDIR/www/pub/skins/
echo "Kopiruji utils/"
rsync --copy-links  -t -r --exclude '.svn' $WWWDIR/utils $UPLDIR/www/
echo "Nastavuji prava docasnemu adresari..."
chmod -R ugo+rwx $UPLDIR/www/

# Ziskame cislo revize kterou prave uploadujeme
VYVOJ_REV=`cat .inbox_instance.info | grep -E "^vyvojrevision *=" | sed 's/.*\?=[^0-9]*\([0-9]\+\)$/\1/'`

#--pojmenujeme soubor, tak jak by asi mel vypadat
TMP_FILE_NAME="$(date +'%y%m%d')_r${VYVOJ_REV}_"

#--zjistime, zda existuje soubor pro upload s urcitym poradovym cislem a zatoven
# urcime poradove cislo
CISLO=1
FILE_NAME=${TMP_FILE_NAME}
for s in ${UPLDIR}/${FILE_NAME}*; do
  #--tato podminka je zde proto, ze pokud nebyl zadny soubor odpovidajici expanzi s *
  #-- pro dany DATUM_*, tak se do s stejne ulozil retezec z * a navysilo
  #-- to zbytecne citac a delalo chybu
  if [ ${s%"*"} = "${s}" ]; then
    CISLO=$((CISLO+1))
  fi
done

if [ $CISLO -lt 10 ]; then
 CISLO="0${CISLO}"
fi

FILE_NAME=${TMP_FILE_NAME}${CISLO}


# Optame se na pridani SQL skriptu k provedeni
#cd $UPLDIR
if ! [ ! -d $UPLDIR/sql -o `ls -a $UPLDIR/sql | wc -l ` -eq 2 ] && [ -z "$pridatsql" ] && [ -z "$notar" ]; then
    echo -n "Chcete pridat i adresar ${UPLDIR}/sql s SQL skripty k provedeni? (v tuto chvili je mozne do ${UPLDIR}/www pridat dalsi soubory rucne - napriklad pro inicialni upload)[n]"
    read pridatsql
fi

# Zatarujeme
if [ -z "$notar" ]; then
    echo "Vytvarim TAR balicek..."
    # Musime nastavit specialni prava kvuli bezpecnosti na ostrem
    find $UPLDIR/www/ -type f -exec chmod 644 {} \;
    find $UPLDIR/www/ -type d -exec chmod 755 {} \;
    chmod ugo+x $UPLDIR/www/utils/*
    chmod ug+x $UPLDIR/www/setmods.sh

    if [ "$pridatsql" = "y" -o "$pridatsql" = "a" ]; then
        echo "Pridavam do balicku i adresar ${UPLDIR}/sql"
        # Pridame sql i do vyvoj kvuli snazsimu spusteni na ostrem
        chmod -R ugo=rwx $UPLDIR/sql/
        cp -R ${UPLDIR}/sql ${UPLDIR}/www/
        mv ${UPLDIR}/www/sql ${UPLDIR}/www/_sql
        tar -z -c -h -C $UPLDIR -f $UPLDIR/$FILE_NAME.tar.gz www sql
        # odstranime obsah sql - update skript tam sam nahrava potrebne skripty
        rm -rf $UPLDIR/sql/*
    else
    tar -z -c -h -C $UPLDIR -f $UPLDIR/$FILE_NAME.tar.gz www
    fi

    # Nastavime prava zapisu pro vsechny pro dany tar
    chmod guo+rwX $UPLDIR/$FILE_NAME.tar.gz
    # Nastavime prava v pomocnem adresari
    chmod -R ugo+rwx $UPLDIR/www/

    echo ""
    echo "Subor: ${UPLDIR}/${FILE_NAME}.tar.gz"
fi
#cd ..

echo "Hotovo!"
echo
echo "Posledni zmenene soubory:"
~lekarnik/scripts/last_mod_files -n 7 -v ${UPLDIR}/www
echo


# Zeptame se, jestli se ma zrovna vykonat uploadovaci skript pre-upload
# Ptame se pokud nebyl zadan parametr
if [ -z "$doupload" ] && [ -z "$notar" ]; then
    echo -n "Chcete provest skript pre-upload? [n]"
    read doupload
fi

if [ "$doupload" = "y" -o "$doupload" = "a" ] && [ -z "$notar" ]; then
    # Nacteme konfiguraci
    counter=0
    while read line
    do
  config[counter]=$line
  counter=`expr $counter + 1`;
    done < .inbox_instance.info

    PROJECT=${config[0]}
    INFOTEXT="DB: ${config[1]}, ${config[2]}"
    WEBURL=${config[2]}
    FILE_TO_UPL=${UPLDIR}/${FILE_NAME}.tar.gz

    # Provedeme pre-upload
    counter=0
    while read line
    do
  output[counter]=${line}
  counter=`expr $counter + 1`;
    done < <(echo n | ~lekarnik/scripts/pre-upload ${PROJECT} ostry ${FILE_TO_UPL})

    for n in 0 1 2 3 4 5
    do
  echo "${output[n]}"
    done
    echo ${INFOTEXT}
    echo
    #echo ${FILE_TO_UPL}

    # Provedeme nastaveni revize ktera je na ostrem v .inbox_instance.info
    sed -i 's/^\(ostryrevision\ *\)=.*$/\1='${VYVOJ_REV}'/' .inbox_instance.info
fi
