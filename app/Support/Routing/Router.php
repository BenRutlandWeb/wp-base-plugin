<?php

namespace Plugin\Support\Routing;

use Closure;
use Plugin\Support\Events\Dispatcher;
use Plugin\Support\Foundation\Application;
use Plugin\Support\Http\Request;

class Router
{
    /**
     * The events instance
     *
     * @var \Plugin\Support\Foundation\Application
     *
     */
    public $app;
    /**
     * The events instance
     *
     * @var \Plugin\Support\Events\Dispatcher
     */
    protected $events;

    /**
     * The router group stack
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * The current group
     *
     * @var array
     */
    protected $currentGroup = [];

    /**
     * The registered routes
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Create the router instance
     *
     * @param \Plugin\Support\Events\Dispatcher $events
     * @param \Plugin\Support\Foundation\Application $app
     */
    public function __construct(Dispatcher $events, Application $app)
    {
        $this->events = $events;
        $this->app = $app;
    }

    /**
     * Create an ajax route
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function ajax(string $uri, $action)
    {
        return $this->addRoute(new AjaxRoute('AJAX', $uri, $action));
    }

    /**
     * Create an GET route
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function get(string $uri, $action)
    {
        return $this->addRoute(new RestRoute(['GET', 'HEAD'], $uri, $action));
    }

    /**
     * Create a POST route
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function post(string $uri, $action)
    {
        return $this->addRoute(new RestRoute('POST', $uri, $action));
    }

    /**
     * Create a PUT route
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function put(string $uri, $action)
    {
        return $this->addRoute(new RestRoute('PUT', $uri, $action));
    }

    /**
     * Create a PATCH route
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function patch(string $uri, $action)
    {
        return $this->addRoute(new RestRoute('PATCH', $uri, $action));
    }

    /**
     * Create a DELETE route
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function delete(string $uri, $action)
    {
        return $this->addRoute(new RestRoute('DELETE', $uri, $action));
    }

    /**
     * Create an route with any method
     *
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function any(string $uri, $action)
    {
        return $this->addRoute(new RestRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action));
    }

    /**
     * Create an route matching the given methods
     *
     * @param array $methods
     * @param string $uri
     * @param \Closure $action
     * @return void
     */
    public function matches(array $methods, string $uri, $action)
    {
        if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        return $this->addRoute(new RestRoute($methods, $uri, $action));
    }

    /**
     * Regsiter the route in the router
     *
     * @param Plugin\Support\Routing\Route $route
     * @return void
     */
    public function addRoute(Route $route)
    {
        $this->routes[] = $route;

        if ($this->hasGroupStack()) {
            $this->mergeGroupAttributesIntoRoute($route);
        }

        return $route;
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return !empty($this->groupStack);
    }

    /**
     * Merge the group stack with the controller action.
     *
     * @param  \Plugin\Support\Routing\Route  $route
     * @return void
     */
    protected function mergeGroupAttributesIntoRoute($route)
    {
        $route->setGroupAttributes($this->getMergedGroupStack());
    }

    /**
     * Return the merged group stack
     *
     * @return array
     */
    public function getMergedGroupStack()
    {
        return [
            'middleware' => $this->getMiddleware(),
            'prefix'     => $this->getPrefix(),
            'namespace'  => $this->getNamespace(),
        ];
    }

    /**
     * Set a group middleware
     *
     * @param string|array $middleware
     * @return self
     */
    public function middleware($middleware)
    {
        $this->currentGroup['middleware'] = (array) $middleware;

        return $this;
    }

    /**
     * Get teh group middleware
     *
     * @return array
     */
    public function getMiddleware()
    {
        $routeMiddleware = $this->app->getRouteMiddleware();

        $middleware = [];

        foreach (array_column($this->groupStack, 'middleware') as $aliases) {
            foreach ($aliases as $alias) {
                if ($wares = $routeMiddleware[$alias]) {
                    foreach ((array) $wares as $ware) {
                        $middleware[] = $ware ?? null;
                    }
                }
            }
        };

        return array_unique($middleware);
    }

    /**
     * Set the group prefix
     *
     * @param string $prefix
     * @return self
     */
    public function prefix(string $prefix)
    {
        $this->currentGroup['prefix'] = trim($prefix, '/');

        return $this;
    }

    /**
     * Get the group prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return implode('/', array_column($this->groupStack, 'prefix'));
    }

    /**
     * Set the group namespace
     *
     * @param string $namespace
     * @return self
     */
    public function namespace(string $namespace)
    {
        $this->currentGroup['namespace'] = trim($namespace, '/');

        return $this;
    }

    /**
     * Get the group namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return implode('/', array_column($this->groupStack, 'namespace'));
    }

    /**
     * Define a group in the router
     *
     * @param \Closure|string $callback
     * @return void
     */
    public function group($callback)
    {
        $this->groupStack[] = $this->currentGroup;

        $this->currentGroup = [];

        $callback = $this->resolveGroupCallback($callback);

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Resolve the group callback
     *
     * @param \Closure|string $callback
     * @return \Closure
     */
    protected function resolveGroupCallback($callback)
    {
        return $callback instanceof Closure ? $callback : function ($router) use ($callback) {
            require $callback;
        };
    }

    /**
     * Dispatch the request
     *
     * @param Plugin\Support\Http\Request $request
     * @return void
     */
    public function dispatch(Request $request)
    {
        foreach ($this->routes as $route) {
            $route->setRouter($this)
                ->setContainer($this->app)
                ->dispatch($request);
        }
    }

    public function listen(string $event, $callback)
    {
        $this->events->listen($event, $callback);
    }
}
