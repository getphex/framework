# Package distribution

[Documentation](README.md)

Phex develops the framework and its application template together. The template
is published automatically to a separate repository so developers can eventually
create applications through Composer without cloning the framework.

## On this page

- [Repositories and package names](#repositories-and-package-names)
- [Change the application template](#change-the-application-template)
- [Automatic publication](#automatic-publication)
- [Release packages](#release-packages)

## Repositories and package names

| Repository | Composer package | Role |
| --- | --- | --- |
| [getphex/framework](https://github.com/getphex/framework) | `getphex/framework`, library | Framework sources, CLI, tests, examples and this handbook |
| [getphex/phex](https://github.com/getphex/phex) | `getphex/phex`, project | Generated mirror of `stubs/application/` |

The framework namespace remains `Phex\`; application sources use `App\`. Installing
the framework places it under `vendor/getphex/framework/` in an application.
The application's Composer manifest requires the framework as a dependency.

The mirror is a distribution target. Maintain template changes in the framework
checkout, under `stubs/application/`. The framework's `phex new` command copies that
same template. Do not maintain a second copy by editing the mirror directly.

## Change the application template

From the **framework checkout**, create an independent development application:

```sh
composer install
php bin/phex new ../my-app --local
cd ../my-app
composer install
composer serve
```

`--local` creates an explicit mirrored Composer path dependency with a development
version alias. It is a contributor workflow; generated applications do not depend
on a framework checkout unless this option is selected. See [Installation](installation.md).

Edit the canonical template in `stubs/application/`, then run the framework's
[PHP/native checks](testing.md#framework-checks). The installed-project
checks exercise the template through its independently installed dependency.

Keep `composer.json`, README, agent conventions and the MIT notice in the template.
Build output, installed dependencies and caches remain outside version control.

## Automatic publication

`.github/workflows/split.yml` uses the pinned Illegal Studio subtree-mirror action.
Pull requests changing the template or workflow validate the packages, install and
build an independent application, and check the split without publication credentials.

A successful `Compatibility` run on `main` starts the publisher. Before publishing,
it confirms that the checked commit is still current `main` and has a successful
compatibility run for that exact SHA. Older source revisions are skipped. A manual
workflow dispatch applies the same checks. The main publication runs are serialized.

The action publishes exactly the `stubs/application/` tree to the mirror's `main`
branch. Flatten mode creates a commit as `phex-bot[bot]` for each changed tree. An
unchanged tree produces no new commit. The publisher uses fast-forward updates;
it refuses unexpected direct mirror commits rather than overwriting them.

The framework repository supplies the Actions secrets `PHEX_BOT_APP_ID` and
`PHEX_BOT_PRIVATE_KEY`. The action mints a short-lived installation token scoped
to the mirror, requests only the repository permissions needed for publication,
and revokes the token at job completion. Keep credential values outside source
control and documentation.

## Release packages

The split prepares the application repository; it does not register packages on
Packagist, create version tags or change repository visibility. Until versioned
packages are published, use the explicit local installation flow above.

Once both packages are available, the intended application command is:

```sh
composer create-project getphex/phex my-app
```

The skeleton's framework constraint must resolve to a published framework version.
Do not copy framework tags blindly to the mirror: the two packages have distinct
contents and release histories. Verify an actual `create-project` installation
before documenting a package release as available.

Updating `getphex/framework` in an existing application updates its dependency.
Template files already copied into that application remain owned by the project;
they are not replaced by framework updates.

---

[Previous: Contributing to Phex](contributing.md) | [Next: Benchmarking and profiling](benchmarks.md)
