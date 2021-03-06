<?php

namespace Symlex\Router;

use Symfony\Component\HttpFoundation\Request;
use Symlex\Router\Exception\MethodNotAllowedException;
use Symlex\Router\Exception\AccessDeniedException;

/**
 * @author Michael Mayer <michael@lastzero.net>
 * @license MIT
 */
class RestRouter extends Router
{
    public function route($routePrefix = '/api', $servicePrefix = 'controller.rest.', $servicePostfix = '')
    {
        $app = $this->app;
        $container = $this->container;

        $handler = function ($path, Request $request) use ($app, $container, $servicePrefix, $servicePostfix) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : array());
            }

            $method = $request->getMethod();

            $prefix = strtolower($method);
            $parts = explode('/', $path);

            $controller = array_shift($parts);

            $subResources = '';
            $params = array();

            $count = count($parts);

            if ($count == 0 && $prefix == 'get') {
                $prefix = 'c' . $prefix;
            }

            for ($i = 0; $i < $count; $i++) {
                $params[] = $parts[$i];

                if (isset($parts[$i + 1])) {
                    $i++;
                    $subResources .= ucfirst($parts[$i]);
                }
            }

            $params[] = $request;
            $actionName = $prefix . $subResources . 'Action';

            $controllerService = $servicePrefix . strtolower($controller) . $servicePostfix;

            $controllerInstance = $this->getController($controllerService);

            if (!method_exists($controllerInstance, $actionName)) {
                throw new MethodNotAllowedException ('Method ' . $method . ' not supported');
            }

            if (!$this->hasPermission($request)) {
                throw new AccessDeniedException ('Access denied');
            }

            $result = call_user_func_array(array($controllerInstance, $actionName), $params);

            if(!$result) {
                $httpCode = 204;
            } elseif($method == 'POST') {
                $httpCode = 201;
            } else {
                $httpCode = 200;
            }

            return $app->json($result, $httpCode);
        };

        $app->match($routePrefix . '/{path}', $handler)->assert('path', '.+');
    }
}