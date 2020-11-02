#!/bin/bash
# set permissions recursively for directories and files
# set user and group if specified
# give write permission to files & directories specified in list
# give executable permission to files & directories specified in list
# last argument is treated as base directory if given, default is "./"

## print help ##
help()
{
    cat <<HELP
Pouziti:
        ./setmods.sh 
        ./setmods.sh -a -g developers -u user -b /var/www/www.example.com/www/

    Nastavi prava, uzivatele a skupiny pro dany adresar.
    Take nastavi executable flag pro soubory ze seznamu EXECUTABLE
    a writable pro seznam WRITABLE

    -a spusti chmod/chown/chgrp rekurzivne
    -g group - nastavi skupinu
    -u user - nastavi uzivatele
    -b base_dir - cesta k rootu ( default './' )
    -w nastavi soubory i adresare na others writable
    -h help
    
HELP
    exit 0
}

USER=$(whoami)
NEW_USER=''

# group guessing by machine
MACHINE=$(uname -n)
if [[ "${MACHINE}" == 'valentine.farmacie.cz' || "${MACHINE}" == 'valentine2.farmacie.cz' ]]; then
    GROUP=apache    
fi
if [[ "${MACHINE}" == 'argos.farmacie.cz' || "${MACHINE}" == 'argos2.farmacie.cz' ]]; then
    GROUP=apache    
fi

# automatic group guessing based on last element of output of 'groups' command
if [[ -z "${GROUP}" ]]; then
    GROUP=$(groups | rev | cut -d' ' -f1 | rev)
fi

#BASE_DIR='./'
#BASE_DIR="$( cd "$( dirname "$0" )" && pwd )"
BASE_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

WRITABLE=( cache log search generated_cfg pub/others pub/mails pub/mails/templates pub/content pub/content/boxes pub/content/categories pub/content/reps config_admin.ini pub/skins pub/content/resources texts texts/specific views/scripts)
EXECUTABLE=( *.sh utils/*.php utils/*.sh utils/*.py )
DMASK=775
FMASK=664

## command-line options parser ##
while getopts "hag:u:b:w" opt; do
  case "$opt" in
    h) help; exit ;;
    a) recursive_fix=y;;
    g) GROUP="$OPTARG";;
    u) NEW_USER="$OPTARG";;
    b) BASE_DIR="$OPTARG";;
    w) FMASK=666; DMASK=777;;
    [?]) help; exit;;
    :) help; exit;;
    esac
  done

# remove trailing slash
BASE_DIR="${BASE_DIR%/}/"
# set Internal Field Separator to '<newline><backspace>' , treats well files and dirs with spaces 
O=$IFS
IFS=$(echo -en "\n\b")
    
if [[ -n "${recursive_fix}" ]]; then
    echo "* setting permissions to \"$NEW_USER:$GROUP\" for files ($FMASK) and dirs ($DMASK) in $BASE_DIR"
    
    # Find all files & directories with wrong permissions or different group | exclude svn | only owner 
    # TODO: performance tweak, use find`s xargs for the loop, much faster than for-cycle in bash
    for node in $(find "${BASE_DIR}" \( \! -perm "$FMASK" -type f -o \! -perm "$DMASK" -type d -o \! -group "$GROUP" \) -a \! -path '*.svn*' -a -user "$USER" -print); do
 
        if [[ -n "${NEW_USER}" ]]; then # change owner if specified
            chown ${NEW_USER} "$node"
        fi        
        if [[ -n "${GROUP}" ]]; then # change group if specified
            chgrp ${GROUP} "$node"
        fi  
        if [[ -f "$node" ]]; then # set FMASK Permissions       
            chmod ${FMASK} "$node"
        fi
        if [[ -d "$node" ]]; then # set DMASK Permissions
            chmod ${DMASK} "$node"
        fi      


    done

    echo "* setting permissions to \"$USER:$GROUP\" for files '*.svn*' in $BASE_DIR"
    for node in $(find "${BASE_DIR}" -path '*.svn*' -user "$USER" -a \( \! -group "$GROUP" -o -type f \! -perm -go+rw -o -type d \! -perm -go+rwx \) -print); do

        if [[ -n "${NEW_USER}" ]]; then # change owner if specified
            chown ${NEW_USER} "$node"
        fi        
        if [[ -n "${GROUP}" ]]; then # change group if specified
            chgrp ${GROUP} "$node"
        fi   
        if [[ -f "$node" ]]; then # set FMASK Permissions       
            chmod go+rw "$node"
        fi
        if [[ -d "$node" ]]; then # set DMASK Permissions
            chmod go+rwX "$node"
        fi          

    done 
fi



## process executable
count=${#EXECUTABLE[*]}

echo "* setting executable for $count files"
# cycle through list
for((i=0;i<$count;i++)); do
    file=${BASE_DIR}${EXECUTABLE[${i}]}
    chmod a+x $file # executable for all users
done


## process writable
count=${#WRITABLE[*]}

echo "* setting writable for $count files"
# cycle through list
for((i=0;i<$count;i++)); do
    file=${BASE_DIR}${WRITABLE[${i}]} 
    if [[ ! -e "$file" ]]; then     
        mkdir -v "$file" # create if not exists
    fi
    # set recursive write permission
    chmod -R a+w "$file" 2>/dev/null  # writable for all users
done

# set permissions for script itself
chmod +x ${BASE_DIR}setmods.sh

# restore Internal Field Separator, default is '<space><tab><newline>'
IFS=$O


