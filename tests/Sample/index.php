<?php

$verb = $_SERVER['REQUEST_METHOD'];

if ($verb === 'POST' && $_SERVER['CONTENT_TYPE'] === 'application/x-www-form-urlencoded') {
    echo json_encode($_POST);
    exit;
}

if ($verb === 'POST' && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    echo file_get_contents('php://input');
    exit;
}

echo "Hello World!";
