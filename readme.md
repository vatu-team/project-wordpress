# Project WordPress

Starter WordPress project from Vatu.

## Creating a WordPress Project

### Installing PHP, Node

Befoer creating your WordPress Project, make sure that your local machine has PHP, Composer, and Node installed.

### Create a Project

With PHP and Composer installed on your local machine run, substituting `{project-name}` for your new projects name.

```sh
composer create-project vatu/project-wordpress {project-name} -s dev
```

It can also be create using docker.

```sh
docker run --rm --interactive --tty --volume $PWD:/app composer create-project vatu/project-wordpress {project_name} -s dev
```

### Renaming the Project

1. Change the project name references in `readme.md`
1. Change the project name references in `composer.json`
1. Change the project name references in `package.json`

### Initial Configuration

Create a `.env` files based off `example.env` and populate with your locat environment values.

```sh
php -r "copy('example.env', '.env');"
```

## Contact

Vatu - [info@vatu.co.uk](info@vatu.co.uk)
