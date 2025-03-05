# Project WordPress

[![Commit](https://github.com/vatu-team/project-wordpress/actions/workflows/commit.yml/badge.svg)](https://github.com/vatu-team/project-wordpress/actions/workflows/commit.yml) [![Build](https://github.com/vatu-team/project-wordpress/actions/workflows/build.yml/badge.svg)](https://github.com/vatu-team/project-wordpress/actions/workflows/build.yml)

## Project URLS

- [Readme](https://github.com/vatu-team/project-wordpress/blob/main/readme.md)
- [Local](https://www.project-wordpress.test/)
- [QA](https://project-wordpress.vatu.dev)
- [Production](https://www.project-wordpress.co.uk/)

## Dependencies

### Development

These tools are needed to develop the site locally.

- [GIT](https://git-scm.com/downloads)
- [PHP](https://php.net/)
- [MySQL](https://mysql.com/)
- [Nginx](https://www.nginx.com/)
- [NodeJS](https://nodejs.org/en/)

Most of this can be acquired using [MAMP](https://www.mamp.info/en/mamp-pro/) or [Docker](https://www.docker.com/).
Use you preferred method for developing locally.

## Documentation

During the Alpha/Beta stages, due to constant changes, documentation will be mainly written in-line. With a dedicated section being created at the first major release.

## Folder Structure

```sh
├── config
├── public
│   ├── index.php
│   ├── wp-config.php
│   ├── app
│   │   ├── plugins
│   │   ├── mu-plugins
│   │   ├── themes
│   │   └── languages
│   └── wp
├── tests
├── tools
├── vendor
├── example.env
├── package.json
└── composer.json
```

- `/public` files that need to be accessed by the public.
- `/public/app/` contains WordPress dependencies.
- `/tests/` All configs related to testing the project.
- `/tools/` Development tools not specific to the project.
- `/vendor` Dependencies install location.
- `composer.json` loads the PHP dependencies for this project.
- `example.env` sampled file with our environment variables are set.

## Setup

- Set the http authentication for composer eg `composer config -g http-basic.composer.satispress.vatu.dev <username> <password>`.
- Install composers dependencies `composer install`.
- Update environment variables in the `.env` file. Wrap values that may contain non-alphanumeric characters with quotes, or they may be incorrectly parsed. * Database variables
  - `DB_NAME` - Database name
  - `DB_USER` - Database user
  - `DB_PASSWORD `- Database password
  - `DB_HOST` - Database host
  - Optionally, you can define `DATABASE_URL` for using a DSN instead of using the variables above (e.g. `mysql://user:password@127.0.0.1:3306/db_name`)
  - `WP_ENV `- Set to environment (`development`, `staging`, `production`)
  - `WP_HOME` - Full URL to WordPress home (https://example.test)
  - `WP_SITEURL` - Full URL to WordPress including subdirectory (https://example.test/wp)
  - `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`
  - Generate with [our WordPress salts generator](https://roots.io/salts.html)

## Theme

Watch theme files for changes and compile:

```sh
npm run watch
```

Build files for deployment:

```sh
npm run build
```

## Testing

We automatically run our code against the following tests at the commit, pull request, and build stages.

- `composer run test:security`: 3rd Party Dependencies we rely on do not contain known vulnerabilities
- `composer run test:lint`: Check our code is able to run without fatal errors
- `composer run test:analysis`: Check our code is writen to the highest standards we can
- `composer run tess:compatibility`: Check our code is compatible with the projects version of PHP
- `composer run test:unit`: Unit tests written during the development of our code

## Deploying

### Git Based Deployment.

Deployable branches should be appended with `--built` (eg. `main--built`). For development environments this could be`develop--built`.

You should never push code to built branches. All code should be pushed to the corrisponding branch. Where the built branch will pull from.

## Contributing

To contribute to this project:

- Create a branch off `main` eg, `feature/feature-name`, `hotfix/broken-part`, or `{issue-number}-{name-of-feature}`
- Push code to this branch once is becomes usable. Checking against our Coding Standards `composer run test`. Pushing often giving other chance to review and run QA tests against your new code. Rebasing from `main` will let you check you code works against production code
- When finished or requiring feedback, test your code on staging, pushing it to a branch prefixed with `staging/` to deploy to this projects staging server
- Any fixes should be applied to the origin branch and merged into your staging branch
- Once ready to be signed-off, create a pull request from your original branch in to `main`, assign a review and message the appropriate developer or channel. Detailing any changes that need to be made to the database/CMS
- With sign-off and passings tests, rebase and merge your branch into `main`

## Reporting Issues

If you spot any issues please create a ticket via the project's Issue Tracker. Including the issue, the browser and operating system, and how to replicate it. If the issue is security related please use the contact information below.

## Contact

Vatu - [info@vatu.dev](info@vatu.dev)
