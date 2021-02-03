<?php

namespace Plugin;

/**
 * This file is for all the plugin helper functions. Functions should be called
 * using the namespace to prevent function conflicts with other plugins or the theme.
 */


function app($abstract = null)
{
    if ($abstract) {
        return Application::getInstance()->get($abstract);
    }
    return Application::getInstance();
}

/**
 * Dump and die the given arguments
 *
 * @param mixed ...$args
 * @return void
 */
function dd(...$args): void
{
    die(dump(...$args));
}

/**
 * Dump the given arguments
 *
 * @param mixed ...$args
 * @return void
 */
function dump(...$args): void
{
    var_dump(...$args);
}

/**
 * Include a view with optional passed parameters
 *
 * @param string $path
 * @param array $args
 * @return string
 */
function view(string $path, array $args = []): string
{
    return app('view')->make($path, $args);
}
