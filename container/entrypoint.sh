#!/usr/bin/env sh
set -eu

# Make sure we are the same user as the one who owns the files.
# This is relevant in development when the developer's source code
# is mounted in.
if [ $(id -u) -eq 0 ] && [ -f "$CONTAINER_USERFILE" ]; then
  uid=$(stat -c %u "$CONTAINER_USERFILE")
  gid=$(stat -c %g "$CONTAINER_USERFILE")

  if [ $(id -u) -ne $uid ]; then
    userdel $CONTAINER_USER 2>/dev/null || true
    groupdel $CONTAINER_USER 2>/dev/null || true

    # Rename if already exists.
    if getent group $gid >/dev/null; then
      groupmod -n $CONTAINER_USER "$(getent group $gid | cut -d: -f1)"
    else
      groupadd -g $gid $CONTAINER_USER 2>/dev/null
    fi
    if id $uid >/dev/null 2>&1; then
      usermod -l $CONTAINER_USER "$(id -un $uid)"
    else
      useradd -m -g $gid -u $uid $CONTAINER_USER 2>/dev/null
    fi

    chown -R $CONTAINER_USER:$CONTAINER_USER /home/$CONTAINER_USER
  elif [ $uid -eq 0 ]; then
    # Running on mac with osxfs driver makes the files being owned by
    # the USER running the container as, instead of actually binding
    # the same UID/GID as the host system.
    # See: https://docs.docker.com/docker-for-mac/osxfs/#ownership
    # See: https://stackoverflow.com/a/43213455
    #
    # To handle systems that bind the UID/GID, we need to run as root
    # and switch in this entrypoint to the correct UID/GID.
    #
    # To handle this on mac, we continue as root, but copy in files from
    # the user we really wanted to run as. This way we preseve user settings.
    #
    # (Include hidden files in the copy.)
    cp -Rf --preserve=mode,timestamps /home/$CONTAINER_USER/. /root
  fi
fi

exec "$@"
