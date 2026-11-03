<?php

/**
 * Emits a standalone shell launcher that applies native server defaults and checks
 * lifecycle compatibility.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Emitter;

use Phex\Build\Config\NativeServerConfig;

/** @internal Standalone POSIX launcher; configuration is evaluated only by PHP build tools. */
final class NativeLauncherEmitter
{
    public function emit(NativeServerConfig $config): string
    {
        $source = <<<'SHELL'
#!/bin/sh
set -eu
phex_directory=$(CDPATH= cd "$(dirname "$0")" && pwd)
phex_compiled=''
if ! IFS= read -r phex_compiled < "$phex_directory/server.lifecycle" || [ "$phex_compiled" != @LIFECYCLE@ ]; then
    printf '%s\n' 'The native lifecycle changed or no successful build exists. Run composer build:native.' >&2
    exit 1
fi
phex_listen=${PHEX_LISTEN-@LISTEN@}
phex_workers=${PHEX_WORKERS-@WORKERS@}
phex_max_requests=${PHEX_MAX_REQUESTS-@MAX_REQUESTS@}
while [ "$#" -gt 0 ]; do
    case "$1" in
        --listen|--workers|--max-requests)
            if [ "$#" -lt 2 ]; then
                printf '%s\n' 'A startup option is missing its value.' >&2
                exit 1
            fi
            case "$1" in
                --listen) phex_listen=$2 ;;
                --workers) phex_workers=$2 ;;
                --max-requests) phex_max_requests=$2 ;;
            esac
            shift 2 ;;
        --listen=*) phex_listen=${1#*=}; shift ;;
        --workers=*) phex_workers=${1#*=}; shift ;;
        --max-requests=*) phex_max_requests=${1#*=}; shift ;;
        --) shift; break ;;
        *) break ;;
    esac
done
case "$phex_workers" in
    ''|*[!0-9]*) printf '%s\n' 'Native workers must be a positive decimal integer.' >&2; exit 1 ;;
    *[1-9]*) ;;
    *) printf '%s\n' 'Native workers must be a positive decimal integer.' >&2; exit 1 ;;
esac
case "$phex_max_requests" in
    ''|*[!0-9]*) printf '%s\n' 'Native max requests must be a nonnegative decimal integer.' >&2; exit 1 ;;
esac
if [ -z "$phex_listen" ]; then
    printf '%s\n' 'Native listen cannot be empty.' >&2
    exit 1
fi
exec "$phex_directory/server" --listen "$phex_listen" --workers "$phex_workers" --max-requests "$phex_max_requests" "$@"
SHELL;

        return strtr($source, [
            '@LIFECYCLE@' => escapeshellarg($config->lifecycle->value),
            '@LISTEN@' => escapeshellarg($config->listen),
            '@WORKERS@' => (string) $config->workers,
            '@MAX_REQUESTS@' => (string) $config->maxRequests,
        ])."\n";
    }
}
