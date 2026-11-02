"""Explicit runtime environments for reproducible HTTP measurements."""

from dataclasses import dataclass
import os
import re
from collections.abc import Mapping, Sequence

PHP_CONFIGURATION = ('PHPRC', 'PHP_INI_SCAN_DIR')
DEMO_OPTIONS = ('PHEX_GREETING', 'PHEX_PAGE_SIZE', 'PHEX_UPPERCASE_GREETING', 'PHP_CLI_SERVER_WORKERS')


def validate_names(names: Sequence[str]) -> tuple[str, ...]:
    result = tuple(sorted(set(names)))
    for name in result:
        if re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]*', name) is None:
            raise ValueError('Invalid environment variable name: '+repr(name))
    return result


@dataclass(frozen=True)
class RuntimeEnvironment:
    values: dict[str, str]
    policy: str
    forwarded_names: tuple[str, ...]

    def describe(self) -> dict:
        return {'policy': self.policy, 'entries': len(self.values),
                'bytes': sum(len(os.fsencode(key))+len(os.fsencode(value))+2 for key, value in self.values.items()),
                'forwarded_names': list(self.forwarded_names),
                'php_configuration_names': [name for name in PHP_CONFIGURATION if name in self.values]}


def runtime_environment(policy: str = 'controlled', names: Sequence[str] = (),
                        source: Mapping[str, str] | None = None) -> RuntimeEnvironment:
    inherited = dict(os.environ if source is None else source)
    forwarded = validate_names(names)
    missing = [name for name in forwarded if name not in inherited]
    if missing:
        raise ValueError('Requested environment variables are unset: '+', '.join(missing))
    if policy == 'controlled':
        values = {'PATH': '/usr/bin:/bin'}
        values.update({name: inherited[name] for name in PHP_CONFIGURATION if name in inherited})
    elif policy == 'inherited':
        values = dict(inherited)
        for name in DEMO_OPTIONS:
            values.pop(name, None)
    else:
        raise ValueError('Unknown runtime environment policy: '+repr(policy))
    values.update({name: inherited[name] for name in forwarded})
    values['GOMAXPROCS'] = '1'
    return RuntimeEnvironment(values, policy, forwarded)


def fpm_environment_lines(names: Sequence[str]) -> list[str]:
    # Resolve values in the FPM master. Never interpolate values into INI syntax.
    return ['env['+name+'] = $'+name for name in validate_names(names)]
