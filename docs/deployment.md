# Deploy on PHP

[Documentation](README.md)

Use the generated PHP front controller for an application served through PHP-FPM. The same messages, services, input mapping and resource cleanup used during development remain in place.

## On this page

- [Prepare an application](#prepare-an-application)
- [Front controller and document root](#front-controller-and-document-root)
- [Example with Caddy and PHP-FPM](#example-with-caddy-and-php-fpm)
- [Runtime environment](#runtime-environment)
- [Source caches and updates](#source-caches-and-updates)
- [Verify the deployment](#verify-the-deployment)

## Prepare an application

From **your application directory**:

```sh
composer install --no-dev --prefer-dist
composer build
```

Keep application source and generated artifacts together. The framework's installed dependency supplies the runtime declarations referenced by generated bootstrap files.

For a local check of that front controller:

```sh
php -S 127.0.0.1:3130 -t public public/compiled.php
curl http://127.0.0.1:3130/
```

PHP's built-in server is a development/checking tool. Configure your production web server and PHP-FPM for serving the deployed application.

## Front controller and document root

Set the web server document root to the application's `public/` directory. Route application requests to `public/compiled.php`, preserving the original request method and `REQUEST_URI`. Configure request body and CGI/header forwarding through your web server's PHP-FPM integration.

Keep source, configuration and `vendor/` outside the public document root. The exact socket path, process user and server configuration depend on the PHP-FPM deployment. Use the server's own FastCGI configuration documentation rather than assuming a distribution-specific socket path.

`public/index.php` remains the development entry. Point production front-controller handling at `compiled.php` after building, rather than interpreting definitions on every request.

## Example with Caddy and PHP-FPM

Set Caddy's PHP index to the generated front controller. Adjust the hostname, application path and FPM socket for your host:

```caddyfile
example.com {
    root * /srv/my-app/public
    php_fastcgi unix//run/php/phex.sock {
        index compiled.php
        capture_stderr
    }
    file_server
}
```

The `index` setting selects `compiled.php` for PHP front-controller fallback; `file_server` handles static files. See Caddy's [official php_fastcgi reference](https://caddyserver.com/docs/caddyfile/directives/php_fastcgi) for rewrite behavior, FastCGI parameters and socket options. This frontend is for PHP-FPM; the native executable serves HTTP itself.

## Runtime environment

Supply the variables required by your settings factories to the FPM worker environment. A pool can expose explicit values, for example:

```ini
env[PHEX_GREETING] = production
env[PHEX_PAGE_SIZE] = 20
```

PHP-FPM may clear inherited environment values; configure its pool deliberately. The [PHP-FPM configuration reference](https://www.php.net/manual/en/install.fpm.configuration.php) documents `clear_env` and pool environment entries.

For the optional database module, PHP needs PDO and its configured driver. A SQLite DSN points to an existing initialized database file and a directory writable by the application process. Framework builds do not create its schema.

## Source caches and updates

Use deployment-appropriate OPcache settings. When timestamp checks are disabled, replace/reload the relevant PHP workers or invalidate their code cache as part of your deployment process. A new artifact file does not make a disabled cache revalidate itself.

Deploy structural source changes together with newly generated artifacts. The [rebuild matrix](builds.md#know-when-to-rebuild) distinguishes source method changes from declaration changes.

## Verify the deployment

Exercise a normal route, typed JSON input and a rejected input case. Confirm the response content type and correlation header, expected `404`/`405` behavior and access to runtime configuration. For database applications, verify a write followed by a read through the deployed host.

Unexpected application exceptions produce a generic `500` body and a structured server-side error record. The runtime adapter controls output framing; the current API buffers complete response bodies.

For deployment as a native HTTP process, continue with [Native servers](native.md).

---

[Previous: Builds and development](builds.md) | [Next: Native servers](native.md)
