#!/bin/sh
# Builds dist/glpi-etiquetas-<version>.tar.bz2 with the plugin inside an "etiquetas/" folder,
# as GLPI expects. Files marked export-ignore in .gitattributes are left out.
set -e
cd "$(dirname "$0")/.."

VERSION=$(sed -n "s/.*'PLUGIN_ETIQUETAS_VERSION', '\([^']*\)'.*/\1/p" setup.php)
if [ -z "$VERSION" ]; then
    echo "Version not found in setup.php" >&2
    exit 1
fi
# On GitHub Actions the tag must match the version declared in setup.php
if [ -n "$GITHUB_REF_NAME" ] && [ "$GITHUB_REF_NAME" != "$VERSION" ]; then
    echo "Tag $GITHUB_REF_NAME does not match version $VERSION in setup.php" >&2
    exit 1
fi

mkdir -p dist
git archive --format=tar --prefix=etiquetas/ HEAD | bzip2 -9 > "dist/glpi-etiquetas-$VERSION.tar.bz2"
echo "dist/glpi-etiquetas-$VERSION.tar.bz2"
