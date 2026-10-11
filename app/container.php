<?php

use Slim\Views\PhpRenderer;

return [
    PhpRenderer::class => function () {
        return new PhpRenderer(__DIR__ . '/../templates');
    },
];
