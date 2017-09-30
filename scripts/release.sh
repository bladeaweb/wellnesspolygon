#!/bin/bash
#
# Prepare release by copying all required files to dst project.

release() {
  # Array of directories to copy.
  declare -a DIRS=(
    docroot/sites/all/modules/custom
    docroot/sites/all/themes/custom
    tests/behat
  )

  CURDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  DSTDIR=$CURDIR/../../wellnesspolygon
  SOURCEDIR=$CURDIR/..

  if [ ! -d "$SOURCEDIR" ]; then
    echo "Source directory $SOURCEDIR does not exist"
    return 1
  fi

  if [ ! -d "$DSTDIR" ]; then
    echo "Destination directory $DSTDIR does not exist"
    return 1
  fi

  for dir in "${DIRS[@]}"
  do
     rm -rf "$DSTDIR/$dir"
     cp -R "$SOURCEDIR/$dir" "$DSTDIR/$dir"
  done
}

read -r -p "Are you sure? [y/N] " response
case $response in
    [yY][eE][sS]|[yY])
        release
        ;;
    *)
        echo "Cancelled"
        ;;
esac
