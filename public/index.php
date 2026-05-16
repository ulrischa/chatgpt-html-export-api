<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use ChatGptHtmlExport\HtmlExportApi;

$api = new HtmlExportApi();
$api->handleRequest();
