<?php
namespace DigitalStars\SimpleAPI;

spl_autoload_register(function ($class_name) {
    if (!str_starts_with($class_name, 'DigitalStars\\AR\\'))
        return;

    @include __DIR__ . str_replace(['DigitalStars\\AR\\', '\\'], ['/src/', '/'], $class_name) . '.php';
});
