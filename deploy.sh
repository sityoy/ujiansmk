#!/usr/bin/env bash

set -e

exec bash "$(dirname -- "${BASH_SOURCE[0]}")/scripts/deploy-vps.sh" "$@"
