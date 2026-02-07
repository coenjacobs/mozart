# Background and Philosophy

Mozart is designed to bridge the gap between the WordPress ecosystem and the vast packages ecosystem of PHP as a whole. Since WordPress is such an end-user friendly focussed CMS (for good reasons), there is no place within the ecosystem where an end-user would be burdened with using a developers tool like Composer. Also, since WordPress has to run on basically any hosting infrastructure, running Composer to install packages from the administration panel (trust me, I've tried - it can be done) is a mission impossible to make it happen and compatible with every server out there.

## Why not PHP-Scoper?

There are other tools that enable you to do this. [PHP-Scoper](https://github.com/humbug/php-scoper), for example. PHP-Scoper is a fantastic tool, that does the job right. But, PHP-Scoper wasn't available back when Mozart was first created. Also, PHP-Scoper has a few limitations (no support for classmap autoloaders, for example) that were and are still quite common within the WordPress ecosystem. Finally, PHP-Scoper can be quite the tool to add to your development flow, while Mozart was designed to be _simple to implement_, specifically tailored for WordPress projects.

## Key values

- Must be easily installable by a developer, preferably in a specific version.
- Distribution must be done through an existing package manager or easily maintained separately.
- Shouldn't add a whole layer of complexity to the development process, i.e. learning a whole new tool/language.

Mozart always has been and always will be geared towards solving the conflicting dependencies problem in the WordPress ecosystem, as efficiently and opinionated as possible. By being opinionated in certain ways and specifically focussed on WordPress projects, Mozart has quickly become easy to understand and implement.

## See also

- [installation.md](installation.md) — Get started with Mozart
