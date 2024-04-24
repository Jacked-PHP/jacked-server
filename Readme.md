
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

If on laravel 11, add the Service Provider at the `bootstrap/providers.php`:

```php
<?php
return [
    JackedPhp\JackedServer\JackedServerProvider::class,
];
```

If on previous versions, add the Service Provider to the `config/app.php`:

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

As said, this server comes with WebSocket out of the box, routed with Socket Conveyor. To enable it, add the following setting to your `.env` file:

```dotenv
JACKED_SERVER_WEBSOCKET_ENABLED=true
```

It has a Laravel Broadcasting driver. That allows events to be broadcast from the server to all connections using all conveyor features. It is also possible, by using Conveyor backend client, to run a bot that responds to a specific channel automatically.

### Channel Broadcasting Step By Step

For this example we are connecting/interacting with the channel `actions-channel`. 

**Step 1**:

The authorization is done via Laravel Sanctum (`laravel/sanctum`). Make sure that is installed:

```shell
php artisan install:api
```

**Step 2**:

For this authorization to be activated, you'll need to install and use the packages:

- Follow Conveyor Laravel Broadcaster package's configuration (https://github.com/kanata-php/conveyor-laravel-broadcaster).
- Conveyor Server Client (PHP):
  ```shell
  composer require kanata-php/conveyor-server-client
  ``` 
- Conveyor Client (JS):
  ```shell
  npm install socket-conveyor-client
  ```
  
**Step 3**:

Prepare your code to generate a valid token to be used for a channel. You can request authorization to the server using the broadcasting url. This is the CURL equivalent:

```shell
curl -X GET "http://example.com/broadcasting/auth?channel_name=YOUR_CHANNEL_NAME" \
     -H "Accept: application/json"
```

This is the example in JS `fetch` for our example channel `actions-channel`:

```js
const getAuth = (callback) => {
    fetch('/broadcasting/auth?channel_name=actions-channel', {
        headers: {
            'Accept': 'application/json',
        },
    })
        .then(response => response.json())
        .then(data => callback(data.auth)) // this is the token (data.auth)
        .catch(error => console.error(error));
};
```

This will give you a single use token like follows:

```
{
    "auth": string
}
```

> **Important:** refer to the Laravel Broadcasting documentation for more information on how to authorize channels and how to generate tokens. There is a way to generate a token in the backend as well.

**Step 4**:

Add this to the bootstrap.js file of your Laravel app so the Conveyor client is available globally:

```js
import Conveyor from "socket-conveyor-client";

window.Conveyor = Conveyor;
```

**Step 5**:

> This is a step from the Conveyor Laravel Broadcaster package. Make sure to check at that documentation.

Build a WS Client that you can use to interact with your WS Server. The first step is to create a simple route with your websocket server information:

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/ws-client', function () {
    Auth::loginUsingId(1); // here we authorize for the sake of the example.

    $protocol = config('jacked-server.ssl-enabled') ? 'wss' : 'ws';
    $port = config('jacked-server.ssl-enabled') ? config('jacked-server.ssl-port') : config('jacked-server.port');

    return view('ws-client', [
        'protocol' => $protocol,
        'uri' => '127.0.0.1',
        'wsPort' => $port,
        'channel' => 'private-actions-channel',
    ]);
});
```

**Step 6**: 

> This is a step from the Conveyor Laravel Broadcaster package. Make sure to check at that documentation.

Implement your sample blade template that interacts with this websocket service, properly authorizing an interacting with the channel (`resources/views/ws-client.blade.php`):

```html
<html>
<head>
    <title>WS Client</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<textarea id="msg"></textarea>
<button id="btn-base">Base</button>
<button id="btn-broadcast">Broadcast</button>
<ul id="output"></ul>

<script type="text/javascript">
    // page elements
    const msg = document.getElementById('msg')
    const btnBase = document.getElementById('btn-base')
    const btnBroadcast = document.getElementById('btn-broadcast')
    const output = document.getElementById('output')

    const connect = (token) => {
        let conveyor = new window.Conveyor({
            protocol: '{{ $protocol }}',
            uri: '{{ $uri }}',
            port: {{ $wsPort }},
            channel: '{{ $channel }}',
            query: '?token=' + token,
            onMessage: (e) => output.innerHTML = e,
            onReady: () => {
                btnBase.addEventListener('click', () => conveyor.send(msg.value))
                btnBroadcast.addEventListener('click', () => conveyor.send(msg.value, 'broadcast-action'))
            },
        });
    };

    const  getAuth = (callback) => {
        fetch('/broadcasting/auth?channel_name={{ $channel }}', {
            headers: {
                'Accept': 'application/json',
            },
        })
            .then(response => response.json())
            .then(data => callback(data.auth))
            .catch(error => console.error(error));
    }

    document.addEventListener("DOMContentLoaded", () => getAuth(connect));
</script>
</body>
</html>
```

**Step 7**:

> This is a step from the Conveyor Laravel Broadcaster package. Make sure to check at that documentation.

Now you protect your channel with a "channel route" (a specific laravel detail). You do this by adding the following to your `routes/channels.php`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('actions-channel', function (User $user) {
    return true; // we are authorizing any user here
});
```

**Step 8**:

Enable Broadcaster at the `config/jacked-server.php`:

```php
<?php
[
    // ...
    'websocket' => [
        // ...
        'broadcaster' => true,
    ],
]
```

**Step 9**:

Run the server:

```shell
php artisan jacked:server
```

**Step 9**:

Now visit the `/ws-client` route. You should be able to interact with the WebSocket server there.
