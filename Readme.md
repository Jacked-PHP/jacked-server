
# Jacked Server

[![PHP Composer](https://github.com/Jacked-PHP/jacked-server/actions/workflows/main.yml/badge.svg)](https://github.com/Jacked-PHP/jacked-server/actions/workflows/main.yml)


## Overview

The Jacked Server is a composer package for **Laravel** that runs a Web Server in a few different methods. Between them, you'll find:

- FastCGI - it can point to a host directory and run the PHP files in that directory via FastCGI protocol.

## Installation

Install composer package:

```shell
composer require jacked-php/jacked-server
```

Publish configuration file:

```shell
php artisan vendor:publish --tag=jacked-server
```

Add the Service Provider to the `config/app.php`:

```php
<?php

return [
    // ...
    'providers' => ServiceProvider::defaultProviders()->merge([
        // ...
        JackedPhp\JackedServer\JackedServerProvider::class,
    ]),
    // ...
];
```

Run migrations:

```shell
php artisan migrate
```

## FastCGI Proxy

This server proxy the request to a FastCGI Socket or TCP service. IT is capable of serving WordPress websites, advanced Laravel applications or even single PHP files if needed. It can serve HTTPS and HTTP. 

The `jacked:server` command, part of the Jacked Server service, provides a CLI interface to start the OpenSwoole server to serve your website via FastCGI proxy.

### Signature

The command can be invoked using:

```
php artisan jacked:server
```

It accepts the following optional parameters:

- `--host`: Specifies the server host. Defaults to the configuration value.
- `--port`: Specifies the server port. Defaults to the configuration value.
- `--inputFile`: Specifies the input PHP file. Defaults to `public/index.php`.
- `--documentRoot`: Specifies the document root directory where assets like js files, css or images will be served from. Defaults to `public`.
- `--publicDocumentRoot`: Specifies the public document root directory. Defaults to `public`.

### Description

The command's description is:

```
JackedPHP OpenSwoole Server
```

### Execution

When executed, the command initializes a new instance of the `Server` class with the provided options and runs the server.

### Events

The server fires several events during its lifecycle:

- **JackedServerStarted:** Fired when the server starts.

- **JackedRequestReceived:** Fired when a new request is received.

- **JackedRequestError:** Fired when there's an error in processing the request.

- **JackedRequestFinished:** Fired when the request processing is finished.

### Configuration

This configuration file provides settings for the Jacked Server. The settings are organized into various sections, each catering to a specific aspect of the server's operation.

#### Running Server Details

- `host`: The **IP address** or **domain** on which the server will run. Default is `'0.0.0.0'`, meaning it will listen on all available interfaces.

- `port`: The port number on which the server will listen. Default is `8080`.

- `server-type`: Specifies the type of server. Default is `Server::POOL_MODE`. (refer to [OpenSwoole Server constructor documentation](https://openswoole.com/docs/modules/swoole-server-construct) for more information)

#### SSL Configuration

> Note that when the SSL is enabled, requests to HTTP will be redirected to HTTPS.

- **`ssl-port`**: The port number for SSL connections. Default is `443`.

- **`ssl-enabled`**: Determines if SSL is enabled. Default is `false`.

- **`ssl-cert-file`**: Path to the SSL certificate file.

- **`ssl-key-file`**: Path to the SSL key file.

#### Running Server Default Options

- **`server-protocol`**: The protocol used by the server. Default is `'HTTP/1.1'`.

- **`content-type`**: The default content type for responses. Default is `'text/html'`.

- **`input-file`**: The default input file for the server. Default is the `index.php` in the public directory.

- **`openswoole-server-settings`**: Contains settings specific to the OpenSwoole server:
    - **`document_root`**: The root directory for serving documents. Default is the public directory.
    - **`enable_static_handler`**: Determines if static content handling is enabled. Default is `true`.
    - **`static_handler_locations`**: Specifies the locations for static content. Default locations are `/imgs` and `/css`.

#### Logging

- **`log`**: Contains settings related to logging:
    - **`driver`**: The logging driver to use. Default is `'single'`.
    - **`path`**: The path where log files will be stored. Default is `logs/jacked-server.log` in the storage directory.
    - **`replace-placeholders`**: A flag to determine if placeholders in the log should be replaced. Default is `true`.

#### FastCgi Client Info

This section provides information on how to connect to the FastCGI client:

- **`host`**: The host for the FastCGI client. Default is `'127.0.0.1'`. If using a Unix socket, the format should be `unix:///path/to/php/socket`.

- **`port`**: The port for the FastCGI client. Default is `9000`. If using a Unix socket, set this to `-1`.
