
# Jacked Server


## Overview

Jacked Server is a WebServer that support HTTP and WebSocket. Jacked Server is built with PHP/OpenSwoole. It doesn't only have the traditional approach for OpenSwoole servers: it also supports FastCGI (PHP-FPM)! That makes it more reliable when powering up your PHP applications that are not ready for a [Reactor Architecture](https://openswoole.com/how-it-works).

## Installation

Install composer package:

```shell
git clone https://github.com/Jacked-PHP/jacked-server.git
```

Navigate to that folder and run it:

```shell
./jackit run
```

This will display a Hello world in the browser at the location mentioned in the terminal (usually at the address http://localhost:8080).

To execute this server, serving a Laravel application, you can run:

```shell
/jackit run --publicDocumentRoot=/var/www/my-laravel-app/public --documentRoot=/var/www/my-laravel-app/public
```

---

## Parameters

It accepts the following optional parameters:

- `--host`: Specifies the server host. Defaults to the configuration value.
- `--port`: Specifies the server port. Defaults to the configuration value.
- `--inputFile`: Specifies the input PHP file. Defaults to `public/index.php`.
- `--documentRoot`: Specifies the document root directory where assets like js files, css or images will be served from. Defaults to `public`.
- `--publicDocumentRoot`: Specifies the public document root directory. Defaults to `public`.
- `--logPath`: Log file path. Defaults to `logs/jacked-server.log`.
- `--logLevel`: Log level. Defaults to 100 (warning).

> More coming...

## Events

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

With this set, you can follow the coordinates on how to interact with the WebSocket server at the [Conveyor documentation](https://socketconveyor.com).

### WebSocket Authorization

To authorize with the WebSocket Server, you first need to get a token. This is done by sending an HTTP POST request to the server at the endpoint `/broadcasting/auth` with the following data:

```json
{
    "channel_name": "test-channel"
}
```

This body will define which channel this connection is authorized to connect to.

Your request must be authorized with a Bearer token. This bearer token is set at the `.env` at this moment (`JACKED_SERVER_WEBSOCKET_TOKEN`). The server will respond with a JSON object containing the `auth` key. This token at the `auth` key is the token you need to use to connect to the WebSocket server.

The token at the `auth` key in the response is a JWT token. This token is used to authenticate the WebSocket connection. The token is sent as a query parameter `token` when connecting to the WebSocket server. e.g.: `ws://127.0.0.1?token=your-token-here`. 
