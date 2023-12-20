
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

> **IMPORTANT:** notice that this service acts as a FastCGI client, that means it behaves by default with www-data permissions. That said, all files that it needs to read/write needs the proper permissions. 

## FastCGI Proxy

This server proxy the request to a FastCGI Socket or TCP service. IT is capable of serving advanced Laravel applications, WordPress websites or even single PHP files if needed. It can serve HTTPS and HTTP. It comes with WebSockets support out of the box available in the same port as the HTTP server, with messages routed via [Socket Conveyor](https://socketconveyor.com).

The `jacked:server` command, part of the Jacked Server service, provides a CLI interface to start the OpenSwoole server to serve your website via FastCGI proxy.

## Command

**Signature**

You start the server by invoked:

```
php artisan jacked:server
```

It accepts the following optional parameters:

- `--host`: Specifies the server host. Defaults to the configuration value.
- `--port`: Specifies the server port. Defaults to the configuration value.
- `--inputFile`: Specifies the input PHP file. Defaults to `public/index.php`.
- `--documentRoot`: Specifies the document root directory where assets like js files, css or images will be served from. Defaults to `public`.
- `--publicDocumentRoot`: Specifies the public document root directory. Defaults to `public`.

**Description**

This command starts the Jacked Server to serve your website via FastCGI proxy. It also provides a WebSocket server to handle WebSocket connections. It follows the configurations specified in the `config/jacked-server.php` file.

### Events

The server fires several events during its lifecycle:

- **JackedServerStarted:** Fired when the server starts.

- **JackedRequestReceived:** Fired when a new request is received.

- **JackedRequestError:** Fired when there's an error in processing the request.

- **JackedRequestFinished:** Fired when the request processing is finished.

## WebSocket

As said, this server comes with WebSocket out of the box, routed with Socket Conveyor.

It has a Laravel Broadcasting driver. That allows events to be broadcast from the server to all connections using all conveyor features. It is also possible, by using Conveyor backend client, to run a bot that responds to a specific channel automatically.

**Authorization**

The authorization is done via Laravel Sanctum. For this authorization to be activated, you'll need to install and use the package `kanata-php/laravel-broadcaster`. That package will make available the `conveyor` Broadcast driver.

To install the package:

```shell
composer install kanata-php/laravel-broadcaster
```

At this moment, it works by using JWT Token API generated for one time consumption. It serves to make sure that the frontend client authorizes before connecting to a protected channel. It uses a query string parameter "token", e.g.: `ws://localhost:8080?token=my-token-here`.

To generate a valid token to be used, you can request authorization to the server using the broadcasting url:

```
POST /broadcasting/auth HTTP/1.1
```

This will give you a single use token like follows:

```
{
    "token": string
}
```

A programmatic way is to use Conveyor's JwtToken service:

```php
use Kanata\LaravelBroadcaster\Services\JwtToken;

/** @var \Kanata\LaravelBroadcaster\Models\Token $token */
$token = JwtToken::create(
    name: 'some-token',
    userId: auth()->user()->id,
    expire: null,
    useLimit: 1,
);
``` 

> **IMPORTANT:** This token will get expired after the limited usage.
