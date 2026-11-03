# Directory structure

[Documentation](README.md)

A Phex application is an independent Composer project. Your shared application code lives in `app/`; runtime entry points and build configuration have explicit locations.

## On this page

- [Application layout](#application-layout)
- [Definitions and runtime values](#definitions-and-runtime-values)
- [Entry points](#entry-points)
- [Generated files](#generated-files)
- [Framework checkout](#framework-checkout)

## Application layout

```text
my-app/
├── app/
│   ├── Config/          # Validated application settings and their factories
│   ├── Http/Action/     # Typed routing endpoints
│   ├── Http/Handlers.php
│   └── Input/           # Input DTOs and explicit decoders
├── bootstrap/app.php   # PHP development container creation
├── config/
│   ├── build.php        # Source roots and generated application settings
│   ├── server.php       # Native lifecycle and startup defaults
│   └── services.php     # Explicit bindings and service lifetimes
├── routes/web.php      # Endpoint routes and names
├── public/
│   ├── index.php        # Build-free PHP development entry
│   └── compiled.php     # Generated PHP entry
├── native/
│   ├── main.php         # Native request-lifecycle entry
│   └── application.php  # Native retained-application entry
├── var/
│   ├── generated/       # Generated containers and static bootstraps
│   └── build/           # Native executable, launcher and lifecycle marker
├── vendor/
└── composer.json
```

Keep one autoloadable application type per file, matching the `App\` PSR-4 namespace. The [Quickstart](quickstart.md) adds `app/Support/Greeter.php`; adding focused folders as the application grows is supported.

## Definitions and runtime values

`config/services.php` describes how objects are constructed. `routes/web.php` describes endpoint selection. The PHP development backend interprets these definitions; build tooling validates the same definitions and emits direct PHP calls.

Runtime configuration values belong in typed settings DTOs created from [environment readers](configuration.md). Source defaults are part of your application artifact. Pass credentials through the runtime environment.

`config/server.php` is build-host metadata. It selects the native lifecycle and defaults for the generated launcher. It is distinct from application settings read by the running program.

## Entry points

Use `public/index.php` during PHP development. Use `public/compiled.php` after `composer build`, including with PHP-FPM. Use the generated native executable or launcher after `composer build:native`.

Keep ElephC worker registration in `native/application.php`, outside the shared `app/` source root. The PHP entry points do not load it. Generated runtime graphs load explicit declarations and exclude development reflection and Composer startup.

## Generated files

Treat `var/generated/` and `var/build/` as build output. Change definitions or source and regenerate the output; a subsequent build replaces manual artifact edits.

A successful native build creates `server`, `server.lifecycle` and `start`. Deploy these together when using the launcher. Database files and other runtime data have their own storage lifecycle and should not be replaced with code artifacts.

## Framework checkout

The framework repository contains `src/`, shared compatibility cases, build-host tests, `examples/` and `stubs/application/`. The template is automatically published to the `getphex/phex` mirror; see
[Package distribution](distribution.md). Its Composer commands support framework development. The commands in a newly created application's manifest are smaller; [CLI reference](cli.md) distinguishes the two working directories.

---

[Previous: Your first endpoint](quickstart.md) | [Next: Request and application lifecycle](lifecycle.md)
