# What is MediaWiki?

MediaWiki is a free and open-source wiki app, used to power wiki websites such
as Wikipedia, Wiktionary and Commons, developed by the Wikimedia Foundation and
others.

> [wikipedia.org/wiki/MediaWiki](https://en.wikipedia.org/wiki/MediaWiki)

# How to use this image

    docker run --name some-mediawiki --link some-mysql:mysql -v /local/data/path:/data:rw -d wikimedia/mediawiki

Partial explanation of arguments:

 - `--link` allows you to connect this container with a database container. See `Configure Database` below for more details.
 - `-v` is used to mount a shared folder with the container. See `Shared Volume` below for more details.

 Having troubling accessing your MediaWiki server? See `Accessing MediaWiki` below for help.

## Extra configuration options

Use the following environmental variables to generate a `LocalSettings.php` and perform automatic installation of MediaWiki. If you don't include these, you'll need to go through the installation wizard. See `Installation Wizard` below for more details. Please see [Manual:Configuration_settings](https://www.mediawiki.org/wiki/Manual:Configuration_settings) for details about what these configuration variables do.

 - `-e MEDIAWIKI_SITE_SERVER=` (**required** set this to the server host and include the protocol (and port if necessary) like `http://my-wiki:8080`; configures `$wgServer`)
 - `-e MEDIAWIKI_SITE_NAME=` (defaults to `MediaWiki`; configures `$wgSitename`)
 - `-e MEDIAWIKI_SITE_LANG=` (defaults to `en`; configures `$wgLanguageCode`)
 - `-e MEDIAWIKI_ADMIN_USER=` (defaults to `admin`; configures default administrator username)
 - `-e MEDIAWIKI_ADMIN_PASS=` (defaults to `rosebud`; configures default administrator password)
 - `-e MEDIAWIKI_UPDATE=true` (defaults to `false`; run `php maintenance/update.php`)
 - `-e MEDIAWIKI_SLEEP=` (defaults to `0`; delays startup of container, useful when using Docker Compose)

As mentioned, this will generate the `LocalSettings.php` file that is required by MediaWiki. If you mounted a shared volume (see `Shared Volume` below), the generated `LocalSettings.php` will be automatically moved to your share volume allowing you to edit it. If a `CustomSettings.php` file exists in your data file, a `require('/data/CustomSettings.php');` will be appended to the generated `LocalSettings.php` file.

## Choosing MediaWiki version

We currently track latest MediaWiki production branches, as run on
wikipedia.org.

 - `wikimedia/mediawiki:latest` (currently uses `1.27-wmf9`)

To use one of these pre-built containers, simply specify the tag as part of the `docker run` command:

    docker run --name some-mediawiki --link mysql -v /local/data/path:/data:rw -d wikimedia/mediawiki

## Docker Compose

See https://github.com/gwicke/mediawiki-containers for a fully-featured docker
compose setup with VisualEditor & other services.

## Configure Database

The example above uses `--link` to connect the MediaWiki container with a running [mysql](https://hub.docker.com/_/mysql/) container. This is probably not the best idea for use in production, keeping data in docker containers can be dangerous.

### Using Postgres

You can use Postgres instead of MySQL as your database server using the `:postgres` tag:

    docker run --name some-mediawiki --link some-postgres:postgres -v /local/data/path:/data:rw -d wikimedia/mediawiki:postgres

### Using Database Server

You can use the following environment variables for connecting to another database server:

 - `-e MEDIAWIKI_DB_TYPE=...` (defaults to `mysql`, but can also be `postgres`)
 - `-e MEDIAWIKI_DB_HOST=...` (defaults to the address of the linked database container)
 - `-e MEDIAWIKI_DB_PORT=...` (defaults to the port of the linked database container or to the default for specified db type)
 - `-e MEDIAWIKI_DB_USER=...` (defaults to `root` or `postgres` based on db type being `mysql`, or `postgres` respsectively)
 - `-e MEDIAWIKI_DB_PASSWORD=...` (defaults to the password of the linked database container)
 - `-e MEDIAWIKI_DB_NAME=...` (defaults to `mediawiki`)
 - `-e MEDIAWIKI_DB_SCHEMA`... (defaults to `mediawiki`, applies only to when using postgres)

If the `MEDIAWIKI_DB_NAME` specified does not already exist on the provided MySQL
server, it will be created automatically upon container startup, provided
that the `MEDIAWIKI_DB_USER` specified has the necessary permissions to create
it.

To use with an external database server, use `MEDIAWIKI_DB_HOST` (along with
`MEDIAWIKI_DB_USER` and `MEDIAWIKI_DB_PASSWORD` if necessary):

    docker run --name some-mediawiki \
        -e MEDIAWIKI_DB_HOST=10.0.0.1
        -e MEDIAWIKI_DB_PORT=3306 \
        -e MEDIAWIKI_DB_USER=app \
        -e MEDIAWIKI_DB_PASSWORD=secure \
        wikimedia/mediawiki

## Shared Volume

If provided mount a shared volume using the `-v` argument when running `docker run`, the mediawiki container will automatically look for a `LocalSettings.php` file and `images`, `skins` and `extensions` folders. When found symbolic links will be automatically created to the respsective file or folder to replace the ones included with the default MediaWiki install. This allows you to easily configure (`LocalSettings.php`), backup uploaded files (`images`), and customize (`skins` and `extensions`) your instance of MediaWiki.

It is highly recommend you mount a shared volume so uploaded files and images will be outside of the docker container.

By default the shared volume must be mounted to `/data` on the container, you can change this using by using `-e MEDIAWIKI_SHARED=/new/data/path`.

Additionally if a `composer.lock` **and** a `composer.json` are detected, the container will automatically download [composer](https://getcomposer.org) and run `composer install`. Composer can be used to install additional extensions, skins and dependencies.

## Accessing MediaWiki

If you'd like to be able to access the instance from the host without the container's IP, standard port mappings can be used using the `-p` or `-P` argument when running `docker run`. See [docs.docker.com](https://docs.docker.com/reference/run/#expose-incoming-ports) for more help.

    docker run --name some-mediawiki --link some-mysql:mysql -p 8080:80 -v /local/data/dir:data:rw -d wikimedia/mediawiki

Then, access it via `http://localhost:8080` or `http://host-ip:8080` in a browser.

### Installation Wizard

The first time you access your new MediaWiki instance, you'll be navigated through an installation wizard. The purpose of which is to setup the default database and to generate a configuration file, `LocalSettings.php`.

After using the installation wizard, save a copy of the generated `LocalSettings.php` to your data volume (`-v /local/data/dir:/data:rw`).

If you're using `--link` to connect with a database, you'll be requested to specify the database host, user, password and name. Run `exec some-mediawiki printenv | grep 'MYSQL\|DB\|POSTGRES'` to view the environmental variables relating to the linked database. The database user will not be included, for that use `root` or `postgres` depending on whether you're using mysql or postgres respsectively.

### Docker Machine

If you're using Docker Machine, using `http://localhost:8080` won't work, instead you'll need to run:

    docker-machine ip default

And access your instance of MediaWiki at:

    http://$(docker-machine ip default):8080/

### boot2docker

If you're using boot2docker, using `http://localhost:8080` won't work, instead you'll need to run:

    boot2docker ip

And access your instance of MediaWiki at:

    http://$(boot2docker ip):8080/

## Enabling SSL/TLS/HTTPS

To enable SSL on your server, place your certificate files inside your mounted share volume as `ssl.key`, `ssl.crt` and `ssl.bundle.crt`.  Then add `-e MEDIAWIKI_ENABLE_SSL=true` to your `docker run` command. This will enable the ssl module for Apache and force your instance of mediawik to SSL-only, redirecting all requests from port 80 (http) to 443 (https). Also be sure to include [`-P` or `-p 443:443`](https://docs.docker.com/reference/run/#expose-incoming-ports) in your `docker run` command.

**Note** When enabling SSL, you must update the `$wgServer` in your `LocalSettings.php` to include `https://` as the prefix. If using automatic install, update the `MEDIAWIKI_SITE_SERVER` environmental variable.
