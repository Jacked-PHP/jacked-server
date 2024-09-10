
# Jacked Server


## Overview

Jacked Server is a WebServer that support HTTP and WebSocket. Jacked Server is built with PHP/OpenSwoole. It doesn't only have the traditional approach for OpenSwoole servers: it also supports FastCGI (PHP-FPM)! That makes it more reliable when powering up your PHP applications that are not ready for a [Reactor Architecture](https://openswoole.com/how-it-works).

## Quick Start

> Run the following to prepare the sample laravel that we will serve (notice that you must have all the dependencies to run laravel):
> ```shell
> composer create-project --prefer-dist laravel/laravel /var/www/laravel
> php artisan migrate
> npm install
> npm run dev
> ```

Let's jack that laravel app! First, download the Jacked Server binary:

```shell
# command placeholder
```

Move the downloaded binary to the directory that you want to serve the laravel app (or to your environment's `PATH`).

> Notice that your project must be located in a directory that is accessible by the server (www-data user), usually within `/var/www`.

Now you just need to run the server:

```shell
./jackit run
```

## Installation

Install composer package:

```shell
git clone https://github.com/Jacked-PHP/jacked-server.git
```

Copy the `.env.example` to `.env`:

```shell
cp .env.example .env
```

Navigate to that folder and run it:

```shell
./jackit run
```

This will point to the local .env located at the root directory where jacked server is. The sample server simply displays a Hello world in the browser at the location mentioned in the terminal (usually at the address http://localhost:8080).

You can customize the `.env` there for your needs, or create another one and set the path to it in the `--config` option. 

As an example, to execute this server, serving a Laravel application, you can point the server to the laravel directory through the `--config=` option:

```shell
/jackit run --config=/var/www/.env-pointing-to-laravel
```

---

## Parameters

Check the `.env.example` file for start, but following you'll find a list of all the parameters that can be set in the `.env` file:

- **JACKED_SERVER_INPUT_FILE:** The entry point of the server. e.g.: `/var/www/project/index.php`
- **JACKED_SERVER_DOCUMENT_ROOT:** The document root of the server. e.g.: `/var/www/project`
- **JACKED_SERVER_LOG_PATH:** The path to the log file. e.g.: `/var/www/project/logs/jacked-server.log`
- **JACKED_SERVER_LOG_LEVEL:** The log level of the server. e.g.: `100` (DEBUG)
- **JACKED_SERVER_FASTCGI_HOST:** The FastCGI host. e.g.: `unix:///run/php/php8.3-fpm.sock` (if it is a Unix socket) or `127.0.0.1` (if it is a TCP socket).
- **JACKED_SERVER_FASTCGI_PORT:** The FastCGI port. e.g.: `9000` (if it is a TCP socket) or `-1` (if it is a Unix socket).
- **JACKED_SERVER_WEBSOCKET_ENABLED:** Enable WebSocket. If enabled, [Socket Conveyor](https://socketconveyor.com) will be used to route WebSocket requests.
- **JACKED_SERVER_HOST:** The host of the server. e.g.: `0.0.0.0`.
- **JACKED_SERVER_PORT:** The port of the server. e.g.: `8080`.
- **JACKED_SERVER_SERVER_TYPE:** The server type. e.g.: `2` (`OpenSwoole\Server::SIMPLE_MODE` - 1 - or `OpenSwoole\Server::POOL_MODE` - 2 -).
- **JACKED_SERVER_TIMEOUT:** The timeout of the server. e.g.: `60`.
- **JACKED_SERVER_READWRITE_TIMEOUT:** The read-write timeout of the server. e.g.: `60`.
- **JACKED_SERVER_SSL_PORT:** The SSL port of the server. e.g.: `443`.
- **JACKED_SERVER_SSL_ENABLED:** Enable SSL. Accepts `true` or `false`.
- **JACKED_SERVER_SSL_CERT_FILE:** The SSL certificate file. e.g.: `/path/to/ssl-cert`.
- **JACKED_SERVER_SSL_KEY_FILE:** The SSL key file. e.g.: `/path/to/ssl-key`.
- **JACKED_SERVER_REACTOR_NUM:** The number of reactors. e.g.: `4`.
- **JACKED_SERVER_WORKER_NUM:** The number of workers. e.g.: `4`.
- **JACKED_SERVER_STATIC_ENABLED:** Enable static handler. e.g.: `true`.
- **JACKED_SERVER_STATIC_LOCATIONS:** The static handler locations. e.g.: `/imgs,/css,/js,/build`.
- **JACKED_SERVER_PID_FILE:** The PID file of the server. e.g.: `/var/www/project/jacked-server.pid`.
- **JACKED_SERVER_AUDIT_ENABLED:** Enable audit. e.g.: `false`.
- **JACKED_SERVER_WEBSOCKET_AUTH:** Enable WebSocket authorization. e.g.: `false`.
- **JACKED_SERVER_WEBSOCKET_SECRET:** The WebSocket secret. e.g.: `my-super-secret`.
- **JACKED_SERVER_WEBSOCKET_TOKEN:** The WebSocket token. e.g.: `my-token` (or some difficult hash if auth is enabled).
- **JACKED_SERVER_WEBSOCKET_USE_ACKNOWLEDGMENT:** Enable WebSocket acknowledgment. e.g.: `false`. Check the [Socket Conveyor documentation](https://socketconveyor.com) for more information.
- **JACKED_SERVER_REQUEST_INTERCEPTED_URIS:** The URIs that will be intercepted by the Jacked Server. e.g.: `/api/v1/intercepted,/api/v1/intercepted2`.
- **JACKED_SERVER_PERSISTENCE_DRIVER:** The persistence driver. e.g.: `sqlite` - the only currently supported persistence for now.
- **JACKED_SERVER_PERSISTENCE_SQLITE_DATABASE:** The SQLite database. e.g.: `:memory:`.


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

To authorize with the WebSocket Server, you first need to get a token. This is done by sending an HTTP POST request to the server at the endpoint `/broadcasting/auth`. Your request must be authorized with a Bearer token (with the following header: `Auhtorization: Bearer {token here})`. This bearer token is set at the `.env` `JACKED_SERVER_WEBSOCKET_TOKEN`. You must select the channel at the body of this request. The body has the following format:

```json
{
    "channel_name": "test-channel"
}
```

This body will define which channel this connection is authorized to connect to.

The server will respond with a JSON object containing the `auth` key. This token at the `auth` key is the token you need to use to connect to the WebSocket server.

The token at the `auth` key in the response is a JWT token. This token is used to authenticate the WebSocket connection. The token is sent as a query parameter `token` when connecting to the WebSocket server. e.g.: `ws://127.0.0.1?token=your-token-here`. 
